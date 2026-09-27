# Billing

O domínio de cobrança usa planos, subscriptions e webhook.

O adaptador de pagamento deve validar:
- assinatura do webhook;
- idempotência;
- valor;
- cliente;
- plano;
- data de expiração.

Não habilitar recursos premium somente porque o cliente chamou um endpoint.
