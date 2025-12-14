# prudential-to-moneyforward
# for local development.

.DEFAULT_GOAL := help

help: ## ヘルプを表示
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

setup: ## 初期セットアップ
	docker compose build
	docker compose run --rm php composer install
	test -f .env || cp -n .env.example .env
	@if [ "`grep '^APP_KEY=.\+' .env`" ]; then \
			echo ; \
		elif [ "`grep '^APP_KEY=' .env`" ]; then \
			docker compose run --rm php php artisan key:generate ; \
		else \
			echo "APP_KEY=" >> .env ; \
			docker compose run --rm php php artisan key:generate ; \
		fi
	@echo
	@echo done.

install: ## 依存関係をインストール
	git submodule update --init
	docker compose build
	docker compose run --rm php composer install
	docker compose run --rm php php artisan clear-compiled
	npm install
	npm run dev

clear: ## キャッシュクリア
	docker compose run --rm php sh -c "\
		composer dump-autoload --optimize ; \
		php artisan clear-compiled ; \
		php artisan view:clear"
	rm -rf storage/app/*
#	rm -rf storage/logs/*
	rm -rf storage/debugbar/*
	git checkout storage
	docker compose run --rm php php artisan cache:clear

up: ## Dockerコンテナ起動
	docker compose up -d --build

down: ## Dockerコンテナ停止
	docker compose down

php: ## PHPコンテナにシェル接続
	docker compose exec php sh

selenium: ## SeleniumコンテナにVNC接続
# password 'secret'
	open vnc://localhost:15910

logs: ## Dockerコンテナのログをtail表示
	docker compose logs -f

log: ## Laravelログをtail表示
	tail -f ./storage/logs/*

test: ## テスト実行
	docker compose exec php php artisan test

# cs-fixer
fix-diff: ## コード整形の差分確認
	docker compose run --rm php ./vendor/bin/php-cs-fixer fix --dry-run --diff -v

fix-v: ## コード整形の詳細確認
	docker compose run --rm php ./vendor/bin/php-cs-fixer fix --dry-run -v

fix: ## コード整形を実行
	docker compose run --rm php ./vendor/bin/php-cs-fixer fix -v

#
# tasks
#

sample-inspire: ## インスピレーションメッセージを表示
	docker compose exec php php artisan inspire

####

crawl: ## プルデンシャルからマネーフォワードへ自動更新
	docker compose exec php php artisan ptm:crawl-prudential-to-moneyforward
