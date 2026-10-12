'use strict';

/* =========================================================
   TRADING AI PLATFORM
   Frontend principal
   Modo DEMO/PAPER — sem ordens reais
   ========================================================= */

const API = '/api';
const TOKEN_KEY = 'trading_auth_token';

let authMode = 'login';
let marketTimer = null;
let analysisTimer = null;
let signalTimer = null;
let dashboardLoading = false;
let marketLoading = false;
let analysisLoading = false;
let signalLoading = false;
let backtestLoading = false;

/* =========================================================
   UTILITÁRIOS
   ========================================================= */

function byId(id) {
  return document.getElementById(id);
}

function setText(id, value) {
  const el = byId(id);
  if (el) el.textContent = value == null ? '—' : String(value);
}

function formatNumber(value, digits = 2) {
  const number = Number(value);

  if (!Number.isFinite(number)) return '—';

  return number.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: digits
  });
}

function formatMoney(value, currency = 'USDT') {
  const number = Number(value);

  if (!Number.isFinite(number)) return '—';

  return formatNumber(number, 2) + ' ' + currency;
}

function formatPercent(value) {
  const number = Number(value);

  if (!Number.isFinite(number)) return '—';

  return number.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 4
  }) + '%';
}

function formatDate(value) {
  if (value == null || value === '') return '—';

  let date;

  if (typeof value === 'number' || /^\d+$/.test(String(value))) {
    const number = Number(value);
    date = new Date(number < 1000000000000 ? number * 1000 : number);
  } else {
    date = new Date(value);
  }

  if (Number.isNaN(date.getTime())) return '—';

  return date.toLocaleString('pt-BR');
}

function errorMessage(error) {
  return error && error.message
    ? error.message
    : 'Ocorreu um erro inesperado.';
}

function escapeHTML(value) {
  return String(value == null ? '' : value).replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  })[char]);
}

function showMessage(id, message) {
  setText(id, message);
}

/* =========================================================
   REQUISIÇÕES HTTP E AUTENTICAÇÃO
   ========================================================= */

async function get(path, opts = {}) {
  const headers = new Headers(opts.headers || {});
  const token = localStorage.getItem(TOKEN_KEY);

  if (token) {
    headers.set('Authorization', 'Bearer ' + token);
  }

  if (
    opts.body &&
    typeof opts.body === 'string' &&
    !headers.has('Content-Type')
  ) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(path, {
    ...opts,
    headers,
    cache: 'no-store'
  });

  const contentType = response.headers.get('content-type') || '';
  let data;

  if (contentType.includes('application/json')) {
    data = await response.json();
  } else {
    const text = await response.text();
    data = text ? { message: text } : {};
  }

  if (response.status === 401 && !path.startsWith(API + '/auth/')) {
    localStorage.removeItem(TOKEN_KEY);

    const authScreen = byId('auth-screen');

    if (authScreen) authScreen.hidden = false;

    const accountBar = byId('account-bar');

    if (accountBar) accountBar.hidden = true;
  }

  if (!response.ok) {
    throw new Error(
      data.error ||
      data.message ||
      'Erro HTTP ' + response.status
    );
  }

  return data;
}

/* =========================================================
   CARREGAMENTO DO PAINEL
   ========================================================= */

async function load() {
  if (dashboardLoading) return;

  dashboardLoading = true;

  try {
    await Promise.allSettled([
      loadDashboardStats(),
      loadRisk(),
      loadSubscription(),
      loadStrategies()
    ]);
  } finally {
    dashboardLoading = false;
  }
}

async function loadDashboardStats() {
  try {
    const data = await get(API + '/dashboard');

    if (data.mode != null) {
      setText('mode', String(data.mode).toUpperCase());
    }

    const stats = data.stats || {};

    setText(
      'stats',
      'Estratégias: ' + (stats.strategies ?? 0) +
      '\nPosições: ' + (stats.active_positions ?? 0) +
      '\nResultado da simulação: ' + (stats.paper_pnl ?? 0)
    );
  } catch (error) {
    setText('stats', 'Não foi possível carregar o painel: ' + errorMessage(error));
  }
}

async function loadRisk() {
  try {
    const risk = await get(API + '/risk');

    setText(
      'risk',
      'Máx. ordem: ' + (risk.max_order_value ?? '—') +
      '\nPerda diária: ' + (risk.max_daily_loss ?? '—') +
      '\nPosições: ' + (risk.max_open_positions ?? '—')
    );
  } catch (error) {
    setText('risk', 'Erro ao carregar risco: ' + errorMessage(error));
  }
}

async function loadSubscription() {
  try {
    const subscription = await get(API + '/subscription');

    setText(
      'subscription',
      'Plano: ' + (subscription.plan ?? '—') +
      '\nStatus: ' + (subscription.status ?? '—')
    );
  } catch (error) {
    setText(
      'subscription',
      'Erro ao consultar assinatura: ' + errorMessage(error)
    );
  }
}

async function loadStrategies() {
  const element = byId('strategies');

  if (element) element.textContent = 'Carregando estratégias...';

  try {
    const data = await get(API + '/strategies');

    if (element) {
      element.textContent = JSON.stringify(data, null, 2);
    }
  } catch (error) {
    if (element) {
      element.textContent =
        'Erro ao carregar estratégias: ' + errorMessage(error);
    }
  }
}

/* =========================================================
   COTAÇÃO BYBIT
   ========================================================= */

async function loadMarket() {
  if (marketLoading) return;

  marketLoading = true;

  const status = byId('market-status');

  if (status) status.textContent = 'Consultando a Bybit...';

  try {
    const data = await get(
      API + '/market/ticker?symbol=BTCUSDT'
    );

    if (
      data.status !== 'ok' ||
      !Number.isFinite(Number(data.last_price)) ||
      Number(data.last_price) <= 0
    ) {
      throw new Error(
        data.message || 'A Bybit não retornou uma cotação válida.'
      );
    }

    setText('market-symbol', data.symbol || 'BTCUSDT');
    setText('market-price', formatMoney(data.last_price));
    setText(
      'market-spread',
      formatNumber(data.bid, 8) + ' / ' + formatNumber(data.ask, 8)
    );

    const change = Number(data.change_24h_percent);

    setText(
      'market-change',
      Number.isFinite(change)
        ? formatPercent(change * 100)
        : '—'
    );

    if (status) {
      status.textContent =
        'Cotação atualizada às ' +
        new Date().toLocaleTimeString('pt-BR');
    }
  } catch (error) {
    if (status) {
      status.textContent = 'Erro na cotação: ' + errorMessage(error);
    }
  } finally {
    marketLoading = false;
  }
}

/* =========================================================
   ANÁLISE TÉCNICA
   ========================================================= */

async function loadAnalysis() {
  if (analysisLoading) return;

  analysisLoading = true;

  const reason = byId('analysis-reason');

  if (reason) {
    reason.textContent = 'Calculando indicadores com dados da Bybit...';
  }

  try {
    const data = await get(
      API + '/market/analysis?symbol=BTCUSDT'
    );

    if (
      data.status !== 'ok' ||
      !data.market ||
      data.market.status !== 'ok'
    ) {
      throw new Error(
        data.market?.reason ||
        data.message ||
        'Não foi possível analisar o mercado.'
      );
    }

    const market = data.market;

    const trends = {
      bullish: 'Alta',
      bearish: 'Baixa',
      sideways: 'Lateral',
      neutral: 'Lateral'
    };

    setText(
      'analysis-trend',
      trends[market.trend] || market.trend || 'Lateral'
    );

    setText('analysis-ema9', formatNumber(market.ema9, 8));
    setText('analysis-ema21', formatNumber(market.ema21, 8));
    setText('analysis-rsi', formatNumber(market.rsi14, 4));

    setText(
      'analysis-bb-upper',
      formatNumber(market.bollinger_upper, 8)
    );

    setText(
      'analysis-bb-middle',
      formatNumber(market.bollinger_middle, 8)
    );

    setText(
      'analysis-bb-lower',
      formatNumber(market.bollinger_lower, 8)
    );

    setText(
      'analysis-bb-width',
      formatPercent(market.bollinger_width_percent)
    );

    setText('analysis-signal', market.signal || 'Sem sinal');

    if (reason) {
      reason.textContent =
        (market.reason || 'Análise concluída.') +
        ' | Candles: ' + (data.candles_count ?? '—') +
        ' | Atualizado: ' +
        new Date().toLocaleTimeString('pt-BR');
    }
  } catch (error) {
    if (reason) {
      reason.textContent = 'Erro na análise: ' + errorMessage(error);
    }
  } finally {
    analysisLoading = false;
  }
}

/* =========================================================
   HISTÓRICO DE SINAIS
   ========================================================= */

async function loadSignalHistory() {
  const tbody = byId('signal-history');

  if (!tbody || signalLoading) return;

  signalLoading = true;
  tbody.textContent = '';

  const loadingRow = document.createElement('tr');
  const loadingCell = document.createElement('td');

  loadingCell.colSpan = 6;
  loadingCell.textContent = 'Consultando histórico...';
  loadingRow.appendChild(loadingCell);
  tbody.appendChild(loadingRow);

  try {
    const data = await get(
      API + '/market/signals?symbol=BTCUSDT&limit=20'
    );

    if (data.status !== 'ok') {
      throw new Error(data.message || 'Falha ao consultar histórico.');
    }

    if (!Array.isArray(data.signals) || data.signals.length === 0) {
      tbody.textContent = '';

      const row = document.createElement('tr');
      const cell = document.createElement('td');

      cell.colSpan = 6;
      cell.textContent = 'Nenhum sinal registrado ainda.';

      row.appendChild(cell);
      tbody.appendChild(row);
      return;
    }

    tbody.textContent = '';

    for (const signal of data.signals) {
      const row = document.createElement('tr');

      const trend =
        signal.trend === 'bullish' ? 'Alta' :
        signal.trend === 'bearish' ? 'Baixa' :
        'Lateral';

      const values = [
        formatDate(signal.candle_time || signal.created_at),
        signal.symbol || '—',
        formatNumber(signal.price, 8),
        trend,
        signal.rsi14 == null ? '—' : formatNumber(signal.rsi14, 4),
        signal.signal || '—'
      ];

      for (const value of values) {
        const cell = document.createElement('td');
        cell.textContent = String(value);
        row.appendChild(cell);
      }

      tbody.appendChild(row);
    }
  } catch (error) {
    tbody.textContent = '';

    const row = document.createElement('tr');
    const cell = document.createElement('td');

    cell.colSpan = 6;
    cell.textContent = 'Erro ao consultar histórico: ' + errorMessage(error);

    row.appendChild(cell);
    tbody.appendChild(row);
  } finally {
    signalLoading = false;
  }
}

/* =========================================================
   SIMULAÇÃO DEMO
   ========================================================= */

async function simulate() {
  const element = byId('result');

  if (element) {
    element.textContent = 'Consultando preço atual da Bybit...';
  }

  try {
    const market = await get(
      API + '/market/ticker?symbol=BTCUSDT'
    );

    const price = Number(market.last_price);

    if (
      market.status !== 'ok' ||
      !Number.isFinite(price) ||
      price <= 0
    ) {
      throw new Error('Não foi possível obter um preço válido.');
    }

    if (element) {
      element.textContent = 'Processando simulação DEMO...';
    }

    const data = await get(API + '/simulate/order', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        symbol: 'BTCUSDT',
        side: 'BUY',
        quantity: 0.001,
        price
      })
    });

    if (data.status !== 'simulated') {
      throw new Error(
        data.message ||
        data.reason ||
        'O backend não confirmou a simulação.'
      );
    }

    const order = data.order || {};

    if (element) {
      element.textContent =
        'SIMULAÇÃO CONCLUÍDA\n\n' +
        'Modo: DEMO/PAPER\n' +
        'Ativo: ' + (order.symbol || 'BTCUSDT') + '\n' +
        'Operação: ' + (order.side || 'BUY') + '\n' +
        'Quantidade: ' + (order.quantity ?? 0.001) + '\n' +
        'Preço utilizado: ' + formatMoney(price) + '\n' +
        'Valor estimado: ' + formatMoney(price * 0.001) + '\n' +
        'Identificador: ' + (data.id || 'Não informado') + '\n\n' +
        (data.message || 'Nenhuma ordem real foi enviada.');
    }

    await loadDashboardStats();
  } catch (error) {
    if (element) {
      element.textContent = 'Erro na simulação: ' + errorMessage(error);
    }
  }
}

/* =========================================================
   KILL SWITCH
   ========================================================= */

async function killSwitch() {
  const confirmed = window.confirm(
    'Deseja acionar o kill switch? O backend receberá uma solicitação de emergência.'
  );

  if (!confirmed) return;

  const element = byId('result');

  if (element) {
    element.textContent = 'Enviando solicitação de emergência...';
  }

  try {
    const data = await get(API + '/kill-switch', {
      method: 'POST'
    });

    if (element) {
      element.textContent =
        'RESPOSTA DO KILL SWITCH\n\n' +
        JSON.stringify(data, null, 2);
    }
  } catch (error) {
    if (element) {
      element.textContent =
        'Não foi possível confirmar o kill switch: ' +
        errorMessage(error);
    }
  }
}

/* =========================================================
   BACKTEST HISTÓRICO
   ========================================================= */

async function runBacktest() {
  const form = byId('backtest-form');
  const button = byId('bt-run');
  const status = byId('backtest-status');
  const results = byId('backtest-results');

  if (!form || !button || !status || !results || backtestLoading) {
    return;
  }

  const balance = Number(byId('bt-balance')?.value);
  const feePercent = Number(byId('bt-fee')?.value);
  const limit = Number(byId('bt-limit')?.value);

  if (!Number.isFinite(balance) || balance <= 0) {
    status.textContent = 'Informe um saldo inicial maior que zero.';
    return;
  }

  if (
    !Number.isFinite(feePercent) ||
    feePercent < 0 ||
    feePercent > 5
  ) {
    status.textContent = 'Informe uma taxa entre 0 e 5%.';
    return;
  }

  if (![50, 100, 200].includes(limit)) {
    status.textContent = 'Selecione uma quantidade válida de candles.';
    return;
  }

  backtestLoading = true;
  button.disabled = true;
  results.hidden = true;
  status.textContent = 'Buscando candles e executando backtest...';

  try {
    const data = await get(API + '/backtest', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        symbol: byId('bt-symbol').value,
        timeframe: byId('bt-timeframe').value,
        strategy: byId('bt-strategy').value,
        limit,
        initial_balance: balance,
        fee_rate: feePercent / 100
      })
    });

    if (!data || data.status !== 'ok') {
      throw new Error(
        data?.message ||
        data?.error ||
        'O backtest não foi concluído.'
      );
    }

    setText('bt-final', formatMoney(data.final_balance));
    setText('bt-net', formatMoney(data.net_result));
    setText('bt-return', formatPercent(data.return_percent));
    setText('bt-trades', data.closed_trades ?? 0);
    setText('bt-winrate', formatPercent(data.win_rate_percent));
    setText('bt-drawdown', formatPercent(data.max_drawdown_percent));

    const tbody = byId('bt-trade-rows');

    if (tbody) {
      tbody.textContent = '';

      const trades = Array.isArray(data.trades) ? data.trades : [];

      if (trades.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');

        cell.colSpan = 6;
        cell.textContent = 'Nenhuma operação encerrada neste período.';

        row.appendChild(cell);
        tbody.appendChild(row);
      } else {
        for (const trade of trades) {
          const row = document.createElement('tr');

          const values = [
            formatDate(trade.entry_timestamp),
            formatDate(trade.exit_timestamp),
            formatNumber(trade.entry_price, 8),
            formatNumber(trade.exit_price, 8),
            formatMoney(trade.net_pnl),
            trade.result || '—'
          ];

          for (const value of values) {
            const cell = document.createElement('td');
            cell.textContent = String(value);
            row.appendChild(cell);
          }

          tbody.appendChild(row);
        }
      }
    }

    results.hidden = false;

    status.textContent =
      'Backtest concluído. Ativo: ' + (data.symbol || '—') +
      ' | Intervalo: ' + (data.timeframe || '—') +
      ' | Candles: ' + (data.candles_tested ?? '—') +
      '. Simulação histórica, sem ordens reais.';
  } catch (error) {
    status.textContent =
      'Não foi possível executar o backtest: ' + errorMessage(error);
  } finally {
    button.disabled = false;
    backtestLoading = false;
  }
}

/* =========================================================
   LOGIN E CADASTRO
   ========================================================= */

function toggleAuthMode() {
  authMode = authMode === 'login' ? 'register' : 'login';

  const name = byId('auth-name');
  const password = byId('auth-password');

  if (name) {
    name.hidden = authMode !== 'register';
    name.required = authMode === 'register';
  }

  if (password) {
    password.autocomplete =
      authMode === 'login' ? 'current-password' : 'new-password';
  }

  setText(
    'auth-subtitle',
    authMode === 'login'
      ? 'Entre para acessar sua conta.'
      : 'Crie sua conta para começar.'
  );

  setText(
    'auth-submit',
    authMode === 'login' ? 'Entrar' : 'Criar conta'
  );

  setText(
    'auth-toggle',
    authMode === 'login' ? 'Ainda não tenho conta' : 'Já tenho conta'
  );

  setText('auth-message', '');
}

function showAuthenticatedUser(user) {
  const screen = byId('auth-screen');
  const bar = byId('account-bar');

  if (screen) screen.hidden = true;
  if (bar) bar.hidden = false;

  setText(
    'account-name',
    'Conta: ' + (user.name || user.email || 'Cliente')
  );
}

async function initAuth() {
  const token = localStorage.getItem(TOKEN_KEY);

  if (token) {
    try {
      const data = await get(API + '/auth/me');

      if (data.status === 'ok' && data.user) {
        showAuthenticatedUser(data.user);
        await load();
        return;
      }
    } catch (error) {
      localStorage.removeItem(TOKEN_KEY);
    }
  }

  if (byId('auth-screen')) byId('auth-screen').hidden = false;
  if (byId('account-bar')) byId('account-bar').hidden = true;
}

async function handleAuthSubmit(event) {
  event.preventDefault();

  const message = byId('auth-message');
  const button = byId('auth-submit');

  if (!message || !button) return;

  button.disabled = true;
  message.textContent = 'Processando...';

  try {
    const email = byId('auth-email').value.trim();
    const password = byId('auth-password').value;

    let data;

    if (authMode === 'register') {
      const name = byId('auth-name').value.trim();

      data = await get(API + '/auth/register', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, email, password })
      });

      if (data.status !== 'ok') {
        throw new Error(
          data.error || data.message || 'Não foi possível criar a conta.'
        );
      }

      const modal = byId('register-success');

      if (modal) {
        modal.hidden = false;
        modal.style.display = 'flex';
      }

      message.textContent = '';

      const enterButton = byId('register-success-enter');

      if (enterButton) {
        enterButton.onclick = () => {
          if (modal) {
            modal.hidden = true;
            modal.style.display = 'none';
          }

          const screen = byId('auth-screen');

          if (screen) {
            screen.hidden = false;
            screen.style.display = '';
          }

          authMode = 'login';

          if (byId('auth-name')) {
            byId('auth-name').hidden = true;
            byId('auth-name').required = false;
          }

          if (byId('auth-password')) {
            byId('auth-password').value = '';
            byId('auth-password').autocomplete = 'current-password';
          }

          setText('auth-subtitle', 'Entre para acessar sua conta.');
          setText('auth-submit', 'Entrar');
          setText('auth-toggle', 'Criar uma conta');

          if (byId('auth-toggle')) {
            byId('auth-toggle').hidden = false;
          }

          if (byId('auth-email')) {
            byId('auth-email').value = email;
          }

          setText(
            'auth-message',
            'Conta criada! Digite sua senha para entrar.'
          );
        };
      }

      return;
    }

    data = await get(API + '/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });

    if (data.status !== 'ok' || !data.token) {
      throw new Error(
        data.error || data.message || 'Login não concluído.'
      );
    }

    localStorage.setItem(TOKEN_KEY, data.token);

    showAuthenticatedUser(data.user || {});
    message.textContent = '';

    await load();
  } catch (error) {
    message.textContent = errorMessage(error);
  } finally {
    button.disabled = false;
  }
}

async function logoutAccount() {
  try {
    await get(API + '/auth/logout', { method: 'POST' });
  } catch (error) {
    // O logout local continua mesmo se o backend não tiver essa rota.
  }

  localStorage.removeItem(TOKEN_KEY);

  if (byId('account-bar')) byId('account-bar').hidden = true;

  if (byId('auth-screen')) {
    byId('auth-screen').hidden = false;
    byId('auth-screen').style.display = '';
  }

  if (byId('auth-password')) byId('auth-password').value = '';

  setText('auth-message', 'Sessão encerrada.');
}

/* =========================================================
   INICIALIZAÇÃO
   ========================================================= */

document.addEventListener('DOMContentLoaded', () => {
  const authForm = byId('auth-form');

  if (authForm) {
    authForm.addEventListener('submit', handleAuthSubmit);
  }

  const backtestForm = byId('backtest-form');

  if (backtestForm) {
    backtestForm.addEventListener('submit', event => {
      event.preventDefault();
      runBacktest();
    });
  }

  // Mantém as funções existentes e atualiza os dados periodicamente.
  loadMarket();
  loadAnalysis();
  loadSignalHistory();

  if (marketTimer) clearInterval(marketTimer);
  if (analysisTimer) clearInterval(analysisTimer);
  if (signalTimer) clearInterval(signalTimer);

  marketTimer = setInterval(loadMarket, 10000);
  analysisTimer = setInterval(loadAnalysis, 60000);
  signalTimer = setInterval(loadSignalHistory, 60000);

  initAuth();
});