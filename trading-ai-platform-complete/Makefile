up:
	docker compose up -d --build

down:
	docker compose down

logs:
	docker compose logs -f --tail=100

status:
	docker compose ps

health:
	curl -fsS http://localhost/api/health

telegram:
	docker compose logs -f telegram-bot
