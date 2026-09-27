# Trading AI Platform

Plataforma modular para automação de trading, estratégias configuráveis, IA, notícias,
backtest, paper trading, copy trading, arbitragem, assinaturas e múltiplos conectores.

> A versão inicial é segura por padrão: `TRADING_MODE=paper`.
> Nenhum conector real é ativado automaticamente.

## Stack

- PHP 8.3 API/Core
- Python 3.12 AI service
- Python Telegram Bot
- PostgreSQL
- Redis
- Nginx
- Docker Compose
- Frontend web responsivo

## Estrutura

- `backend/` API, domínio, banco, risco, estratégias e conectores
- `telegram-bot/` bot Telegram
- `ai-service/` análise de IA e notícias
- `web/` painel web cliente/admin
- `connectors/` contratos e conectores
- `workers/` jobs assíncronos
- `infra/` Docker/Nginx
- `docs/` arquitetura, segurança, API e roadmap

## Instalação

```bash
cp .env.example .env
docker compose up -d --build
```

URLs locais:
- Painel: http://localhost
- API: http://localhost/api/health
- IA: http://localhost:8000/health

## Telegram

Preencha `TELEGRAM_BOT_TOKEN` no `.env` e reinicie:

```bash
docker compose up -d --build telegram-bot
```

Comandos:
`/start`, `/painel`, `/estrategias`, `/nova`, `/backtest`, `/risco`,
`/posicoes`, `/assinatura`, `/simular`, `/stop`.

## Segurança

Nunca commit `.env`, API keys, tokens ou dados de clientes.
Use chaves de exchange sem permissão de saque sempre que a plataforma permitir.
O AI service nunca envia ordens diretamente: toda execução passa por Strategy ->
Risk Manager -> Order Manager -> Connector.

## Produção

Antes de operar dinheiro real:
1. configurar HTTPS;
2. trocar senhas padrão;
3. configurar secrets seguros;
4. validar cada connector em sandbox/paper;
5. ativar auditoria e alertas;
6. testar kill switch;
7. revisar requisitos legais, regulatórios e termos de cada plataforma.
