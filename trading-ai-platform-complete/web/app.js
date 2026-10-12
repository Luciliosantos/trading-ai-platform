async function get(path, opts={}) {
 const headers = {...(opts.headers || {})};
 const token = localStorage.getItem('trading_auth_token');
 if (token) headers.Authorization = 'Bearer ' + token;
 const r = await fetch(path, {...opts, headers});
 const data = await r.json();
 if (!r.ok) throw new Error(data.error || data.message || 'Erro HTTP ' + r.status);
 return data;
}
async function load(){
 const d=await get('/api/dashboard'); document.getElementById('mode').textContent=d.mode.toUpperCase();
 document.getElementById('stats').textContent=`Estratégias: ${d.stats.strategies}\nPosições: ${d.stats.active_positions}\nResultado da simulação: ${d.stats.paper_pnl}`;
 const risk=await get('/api/risk'); document.getElementById('risk').textContent=`Máx. ordem: ${risk.max_order_value}\nPerda diária: ${risk.max_daily_loss}\nPosições: ${risk.max_open_positions}`;
 const s=await get('/api/subscription'); document.getElementById('subscription').textContent=`Plano: ${s.plan}\nStatus: ${s.status}`;
 loadStrategies();
}
async function loadStrategies(){const s=await get('/api/strategies');document.getElementById('strategies').textContent=JSON.stringify(s,null,2)}
async function simulate(){
  const el = document.getElementById('result');
  el.textContent = 'Processando simulação...';

  try {
    const r = await get('/api/simulate/order', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        symbol: 'BTCUSDT',
        side: 'BUY',
        quantity: 0.001,
        price: 50000
      })
    });

    if (r.status !== 'simulated') {
      el.textContent = 'Simulação não concluída.\n\n' +
        (r.message || r.reason || JSON.stringify(r, null, 2));
      return;
    }

    el.textContent =
      'SIMULAÇÃO CONCLUÍDA\n\n' +
      'Modo: DEMO (sem dinheiro real)\n' +
      'Ativo: ' + r.order.symbol + '\n' +
      'Operação: ' + r.order.side + '\n' +
      'Quantidade: ' + r.order.quantity + '\n' +
      'Preço informado: ' + r.order.price + '\n' +
      'Identificador: ' + r.id + '\n\n' +
      (r.message || 'Nenhuma ordem real foi enviada.');
  } catch (e) {
    el.textContent = 'Erro ao simular: ' + e.message;
  }
}
async function killSwitch(){const d=await get('/api/kill-switch',{method:'POST'});document.getElementById('result').textContent=JSON.stringify(d,null,2)}

// Mantém a sessão autenticada nas chamadas à API.
(() => {
  const originalFetch = window.fetch.bind(window);
  window.fetch = (input, init = {}) => {
    const url = typeof input === 'string' ? input : input.url;
    if (url.startsWith('/api/') && !url.startsWith('/api/auth/login')
        && !url.startsWith('/api/auth/register')) {
      const headers = new Headers(
        init.headers || (input instanceof Request ? input.headers : undefined)
      );
      const token = localStorage.getItem('trading_auth_token');
      if (token) headers.set('Authorization', 'Bearer ' + token);
      init = {...init, headers};
    }
    return originalFetch(input, init);
  };
})();

initAuth();

async function loadMarket() {
  const status = document.getElementById('market-status');
  status.textContent = 'Consultando a Bybit...';

  try {
    const d = await get('/api/market/ticker?symbol=BTCUSDT');

    if (d.status !== 'ok') {
      throw new Error(d.message || 'A Bybit não retornou a cotação.');
    }

    const money = v => Number(v).toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 8
    });

    document.getElementById('market-symbol').textContent = d.symbol;
    document.getElementById('market-price').textContent =
      money(d.last_price) + ' USDT';

    document.getElementById('market-spread').textContent =
      money(d.bid) + ' / ' + money(d.ask);

    document.getElementById('market-change').textContent =
      (Number(d.change_24h_percent) * 100).toFixed(2).replace('.', ',') + '%';

    status.textContent = 'Cotação atualizada: ' +
      new Date().toLocaleTimeString('pt-BR');
  } catch (e) {
    status.textContent = 'Erro na cotação: ' + e.message;
  }
}

loadMarket();
setInterval(loadMarket, 10000);


async function loadAnalysis() {
  const reason = document.getElementById('analysis-reason');

  try {
    reason.textContent = 'Calculando indicadores com candles da Bybit...';

    const d = await get('/api/market/analysis?symbol=BTCUSDT');

    if (d.status !== 'ok' || !d.market || d.market.status !== 'ok') {
      throw new Error(d.market?.reason || d.message || 'Não foi possível analisar o mercado.');
    }

    const m = d.market;
    const fmt = v => Number(v).toLocaleString('pt-BR', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 8
    });

    document.getElementById('analysis-trend').textContent =
      m.trend === 'bullish' ? 'Alta' :
      m.trend === 'bearish' ? 'Baixa' : 'Lateral';

    document.getElementById('analysis-ema9').textContent = fmt(m.ema9);
    document.getElementById('analysis-ema21').textContent = fmt(m.ema21);
    document.getElementById('analysis-rsi').textContent = fmt(m.rsi14);
    document.getElementById('analysis-bb-upper').textContent = fmt(m.bollinger_upper);
    document.getElementById('analysis-bb-middle').textContent = fmt(m.bollinger_middle);
    document.getElementById('analysis-bb-lower').textContent = fmt(m.bollinger_lower);
    document.getElementById('analysis-bb-width').textContent =
      Number(m.bollinger_width_percent).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 4
      }) + '%';
    document.getElementById('analysis-signal').textContent = m.signal;
    reason.textContent = m.reason + ' | Candles: ' + d.candles_count +
      ' | Atualizado: ' + new Date().toLocaleTimeString('pt-BR');

  } catch (e) {
    reason.textContent = 'Erro na análise: ' + e.message;
  }
}

loadAnalysis();
setInterval(loadAnalysis, 60000);


async function loadSignalHistory() {
  const tbody = document.getElementById('signal-history');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="6">Consultando histórico...</td></tr>';

  try {
    const d = await get('/api/market/signals?symbol=BTCUSDT&limit=20');

    if (d.status !== 'ok') {
      throw new Error(d.message || 'Falha ao consultar histórico.');
    }

    if (!d.signals || d.signals.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6">Nenhum sinal registrado ainda.</td></tr>';
      return;
    }

    tbody.innerHTML = d.signals.map(s => {
      const date = new Date(s.candle_time || s.created_at).toLocaleString('pt-BR');
      const price = Number(s.price).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 8
      });
      const rsi = s.rsi14 == null ? '-' : Number(s.rsi14).toLocaleString('pt-BR');
      const trend = s.trend === 'bullish' ? 'Alta' :
                    s.trend === 'bearish' ? 'Baixa' : 'Lateral';

      return `<tr>
        <td>${date}</td>
        <td>${s.symbol}</td>
        <td>${price}</td>
        <td>${trend}</td>
        <td>${rsi}</td>
        <td><b>${s.signal}</b></td>
      </tr>`;
    }).join('');
  } catch (e) {
    tbody.innerHTML = '<tr><td colspan="6">Erro: ' + e.message + '</td></tr>';
  }
}


loadSignalHistory();
setInterval(loadSignalHistory, 60000);


// Backtest histórico: simulação apenas, sem enviar ordens reais.
async function runBacktest() {
  const form = document.getElementById('backtest-form');
  const button = document.getElementById('bt-run');
  const status = document.getElementById('backtest-status');
  const results = document.getElementById('backtest-results');
  if (!form || !button || !status || !results) return;

  const balance = Number(document.getElementById('bt-balance').value);
  const feePercent = Number(document.getElementById('bt-fee').value);

  if (!Number.isFinite(balance) || balance <= 0 ||
      !Number.isFinite(feePercent) || feePercent < 0 || feePercent > 5) {
    status.textContent = 'Informe saldo positivo e taxa entre 0 e 5%.';
    return;
  }

  button.disabled = true;
  results.hidden = true;
  status.textContent = 'Buscando candles e executando o backtest...';

  try {
    const data = await get('/api/backtest', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        symbol: document.getElementById('bt-symbol').value,
        timeframe: document.getElementById('bt-timeframe').value,
        strategy: document.getElementById('bt-strategy').value,
        limit: Number(document.getElementById('bt-limit').value),
        initial_balance: balance,
        fee_rate: feePercent / 100
      })
    });

    if (!data || data.status !== 'ok') {
      throw new Error(data?.message || data?.error || JSON.stringify(data));
    }

    const money = v => Number(v).toLocaleString('pt-BR', {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
    const percent = v => Number(v).toLocaleString('pt-BR', {
      minimumFractionDigits: 2, maximumFractionDigits: 4
    }) + '%';

    document.getElementById('bt-final').textContent = money(data.final_balance) + ' USDT';
    document.getElementById('bt-net').textContent = money(data.net_result) + ' USDT';
    document.getElementById('bt-return').textContent = percent(data.return_percent);
    document.getElementById('bt-trades').textContent = data.closed_trades;
    document.getElementById('bt-winrate').textContent = percent(data.win_rate_percent);
    document.getElementById('bt-drawdown').textContent = percent(data.max_drawdown_percent);

    const tbody = document.getElementById('bt-trade-rows');
    const trades = Array.isArray(data.trades) ? data.trades : [];
    tbody.innerHTML = trades.length ? trades.map(t => {
      const date = ts => new Date(Number(ts)).toLocaleString('pt-BR');
      return `<tr>
        <td>${date(t.entry_timestamp)}</td>
        <td>${date(t.exit_timestamp)}</td>
        <td>${money(t.entry_price)}</td>
        <td>${money(t.exit_price)}</td>
        <td>${money(t.net_pnl)}</td>
        <td>${t.result}</td>
      </tr>`;
    }).join('') : '<tr><td colspan="6">Nenhuma operação encerrada neste período.</td></tr>';

    results.hidden = false;
    status.textContent =
      `Teste concluído: ${data.symbol}, timeframe ${data.timeframe}, ` +
      `${data.candles_tested} candles. Modo de simulação; ordens reais desativadas.`;
  } catch (e) {
    status.textContent = 'Não foi possível executar o backtest: ' + e.message;
  } finally {
    button.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('backtest-form');
  if (form) form.addEventListener('submit', event => {
    event.preventDefault();
    runBacktest();
  });
});


let authMode = 'login';

function toggleAuthMode() {
  authMode = authMode === 'login' ? 'register' : 'login';
  document.getElementById('auth-name').hidden = authMode !== 'register';
  document.getElementById('auth-name').required = authMode === 'register';
  document.getElementById('auth-password').autocomplete =
    authMode === 'login' ? 'current-password' : 'new-password';
  document.getElementById('auth-subtitle').textContent =
    authMode === 'login' ? 'Entre para acessar sua conta.' :
    'Crie sua conta para começar.';
  document.getElementById('auth-submit').textContent =
    authMode === 'login' ? 'Entrar' : 'Criar conta';
  document.getElementById('auth-toggle').textContent =
    authMode === 'login' ? 'Ainda não tenho conta' : 'Já tenho conta';
  document.getElementById('auth-message').textContent = '';
}

async function initAuth() {
  const token = localStorage.getItem('trading_auth_token');
  if (token) {
    try {
      const data = await get('/api/auth/me');
      if (data.status === 'ok' && data.user) {
        showAuthenticatedUser(data.user);
        load();
        return;
      }
    } catch (e) {
      localStorage.removeItem('trading_auth_token');
    }
  }
  document.getElementById('auth-screen').hidden = false;
  document.getElementById('account-bar').hidden = true;
}

function showAuthenticatedUser(user) {
  document.getElementById('auth-screen').hidden = true;
  document.getElementById('account-bar').hidden = false;
  document.getElementById('account-name').textContent =
    'Conta: ' + (user.name || user.email || 'Cliente');
}

document.getElementById('auth-form').addEventListener('submit', async function(e) {
  e.preventDefault();
  const message = document.getElementById('auth-message');
  const button = document.getElementById('auth-submit');
  button.disabled = true;
  message.textContent = 'Processando...';

  try {
    const email = document.getElementById('auth-email').value.trim();
    const password = document.getElementById('auth-password').value;
    let data;

    if (authMode === 'register') {
      const name = document.getElementById('auth-name').value.trim();
      data = await get('/api/auth/register', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({name, email, password})
      });

      if (data.status !== 'ok') {
        throw new Error(data.error || 'Não foi possível criar a conta.');
      }

      document.getElementById('auth-screen').hidden = true;
      document.getElementById('register-success').hidden = false;
      document.getElementById('register-success').style.display = 'flex';
      message.textContent = '';
      document.getElementById('auth-email').value = email;
      document.getElementById('register-success-enter').onclick = function() {
        const modal = document.getElementById('register-success');
        modal.hidden = true;
        modal.style.display = 'none';

        const screen = document.getElementById('auth-screen');
        screen.hidden = false;
        screen.style.display = 'block';

        authMode = 'login';
        document.getElementById('auth-subtitle').textContent =
          'Entre para acessar sua conta.';
        document.getElementById('auth-name').hidden = true;
        document.getElementById('auth-name').required = false;
        document.getElementById('auth-password').autocomplete = 'current-password';
        document.getElementById('auth-submit').textContent = 'Entrar';
        document.getElementById('auth-toggle').hidden = false;
        document.getElementById('auth-toggle').textContent = 'Criar uma conta';
        document.getElementById('auth-email').value = email;
        document.getElementById('auth-password').value = '';
        document.getElementById('auth-message').textContent =
          'Conta criada! Digite sua senha para entrar.';
      };
      return;
    }

    data = await get('/api/auth/login', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({email, password})
    });

    if (data.status !== 'ok' || !data.token) {
      throw new Error(data.error || 'Login não concluído.');
    }

    localStorage.setItem('trading_auth_token', data.token);
    showAuthenticatedUser(data.user || {});
    load();
  } catch (err) {
    message.textContent = err.message || 'Não foi possível concluir a operação.';
  } finally {
    button.disabled = false;
  }
});

async function logoutAccount() {
  try {
    await get('/api/auth/logout', {method: 'POST'});
  } catch (e) {}
  localStorage.removeItem('trading_auth_token');
  document.getElementById('account-bar').hidden = true;
  document.getElementById('auth-screen').hidden = false;
  document.getElementById('auth-password').value = '';
  document.getElementById('auth-message').textContent = 'Sessão encerrada.';
}
