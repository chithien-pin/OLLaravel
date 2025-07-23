# Orange Project Makefile

.PHONY: help setup start stop restart logs status backup restore clean flutter flutter-web build-apk build-ios

help: ## Show this help message
	@echo "🍊 Orange Project Commands"
	@echo ""
	@echo "Setup Commands:"
	@echo "  setup     - Setup entire project (backend + flutter)"
	@echo "  start     - Start all Docker services"
	@echo "  stop      - Stop all Docker services"
	@echo "  restart   - Restart all Docker services"
	@echo ""
	@echo "Development Commands:"
	@echo "  logs      - Show Docker logs"
	@echo "  status    - Check system status"
	@echo "  flutter   - Run Flutter app"
	@echo "  flutter-web - Run Flutter web"
	@echo "  build-apk - Build Android APK"
	@echo "  build-ios - Build iOS app"
	@echo ""
	@echo "Database Commands:"
	@echo "  backup    - Create database backup"
	@echo "  restore   - Restore database from backup"
	@echo ""
	@echo "Utility Commands:"
	@echo "  clean     - Clean all containers and volumes"
	@echo "  help      - Show this help"

setup: ## Setup entire project
	@echo "🚀 Setting up Orange Project..."
	./setup.sh
	./setup_flutter.sh

start: ## Start all Docker services
	@echo "🚀 Starting services..."
	docker-compose up -d

stop: ## Stop all Docker services
	@echo "🛑 Stopping services..."
	docker-compose down

restart: ## Restart all Docker services
	@echo "🔄 Restarting services..."
	docker-compose restart

logs: ## Show Docker logs
	@echo "📋 Showing logs..."
	docker-compose logs -f

status: ## Check system status
	@echo "🔍 Checking status..."
	./check_status.sh

flutter: ## Run Flutter app
	@echo "📱 Running Flutter app..."
	cd Orange && flutter run

flutter-web: ## Run Flutter web
	@echo "🌐 Running Flutter web..."
	cd Orange && flutter run -d chrome

build-apk: ## Build Android APK
	@echo "📱 Building APK..."
	cd Orange && flutter build apk

build-ios: ## Build iOS app
	@echo "🍎 Building iOS app..."
	cd Orange && flutter build ios

backup: ## Create database backup
	@echo "🗄️ Creating backup..."
	./backup_db.sh backup

restore: ## Restore database from backup
	@if [ -z "$(FILE)" ]; then \
		echo "❌ Please specify backup file: make restore FILE=backup_file.sql"; \
		exit 1; \
	fi
	@echo "🔄 Restoring from $(FILE)..."
	./backup_db.sh restore $(FILE)

clean: ## Clean all containers and volumes
	@echo "🧹 Cleaning everything..."
	docker-compose down -v
	docker system prune -f
	@echo "✅ Cleaned"

# Laravel specific commands
migrate: ## Run Laravel migrations
	@echo "🔄 Running migrations..."
	docker-compose exec backend php artisan migrate

seed: ## Run Laravel seeders
	@echo "🌱 Running seeders..."
	docker-compose exec backend php artisan db:seed

fresh: ## Fresh migration with seed
	@echo "🔄 Fresh migration..."
	docker-compose exec backend php artisan migrate:fresh --seed

clear: ## Clear Laravel caches
	@echo "🧹 Clearing caches..."
	docker-compose exec backend php artisan config:clear
	docker-compose exec backend php artisan route:clear
	docker-compose exec backend php artisan view:clear
	docker-compose exec backend php artisan cache:clear

# Container access
backend: ## Access backend container
	@echo "🔧 Accessing backend container..."
	docker-compose exec backend bash

db: ## Access MySQL database
	@echo "🗄️ Accessing database..."
	docker-compose exec mysql mysql -u orange_user -porange_pass orange_db

redis: ## Access Redis
	@echo "🔴 Accessing Redis..."
	docker-compose exec redis redis-cli 