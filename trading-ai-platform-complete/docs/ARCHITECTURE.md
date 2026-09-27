# Arquitetura

Fluxo de execução:
Strategy -> AI/Market Context -> Risk Manager -> Order Manager -> Connector -> Platform.

O serviço de IA NÃO envia ordens diretamente.

## Multi-plataforma

O core trabalha com capabilities. Cada connector declara o que suporta.

## Módulos

- Telegram
- Web panel
- Billing/subscriptions
- Strategy Engine
- Backtest
- Paper Trading
- AI
- News
- Copy Trading
- Arbitrage
- Risk
- Connectors
- Audit
- Kill Switch
