# Deploy

1. Instale Docker e Docker Compose.
2. Clone o repositório.
3. `cp .env.example .env`
4. Configure secrets.
5. `docker compose up -d --build`
6. Teste `/api/health`.
7. Teste o painel.
8. Configure Telegram.
9. Só depois configure integrações de trading.

Produção:
- domínio + DNS
- HTTPS
- firewall
- backups PostgreSQL
- monitoramento
- secrets manager
- logs centralizados
