.PHONY: help up stop restart down

help:
	@echo "Available commands:"
	@echo "  make up       - start MySQL container"
	@echo "  make stop     - stop MySQL container"
	@echo "  make restart  - restart MySQL container"
	@echo "  make down   - remove container (keeps data volume)"

up:
	docker compose up -d

stop:
	docker compose stop

restart:
	docker compose restart

down:
	docker compose down
