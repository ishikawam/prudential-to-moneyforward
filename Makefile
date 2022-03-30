# prudential-to-moneyforward
# for local development.

dummy:
	@echo prudential-to-moneyforward Makefile.

setup:
	docker-compose build
	docker-compose run php composer install
	test -f .env || cp -n .env.example .env
	@if [ "`grep '^APP_KEY=.\+' .env`" ]; then \
			echo ; \
		elif [ "`grep '^APP_KEY=' .env`" ]; then \
			docker-compose run php php artisan key:generate ; \
		else \
			echo "APP_KEY=" >> .env ; \
			docker-compose run php php artisan key:generate ; \
		fi
	@echo
	@echo done.

install:
	git submodule update --init
	docker-compose build
	docker-compose run php composer install
	docker-compose run php php artisan clear-compiled
	npm install
	npm run dev

clear:
	docker-compose run php sh -c "\
		composer dump-autoload --optimize ; \
		php artisan clear-compiled ; \
		php artisan view:clear"
	rm -rf storage/app/*
#	rm -rf storage/logs/*
	rm -rf storage/debugbar/*
	git checkout storage
	docker-compose run php php artisan cache:clear

up:
	docker-compose up -d --build

down:
	docker-compose down

php:
	docker-compose exec php sh

selenium:
# password 'secret'
	open vnc://localhost:15910

logs:
	docker-compose logs -f

log:
	tail -f ./storage/logs/*

test:
	docker-compose exec php php artisan test

# cs-fixer
fix-diff:
	docker-compose run php ./vendor/bin/php-cs-fixer fix --dry-run --diff -v

fix-v:
	docker-compose run php ./vendor/bin/php-cs-fixer fix --dry-run -v

fix:
	docker-compose run php ./vendor/bin/php-cs-fixer fix -v

#
# tasks
#

sample-inspire:
	docker-compose exec php php artisan inspire

####

crawl:
	docker-compose exec php php artisan ptm:crawl-prudential-to-moneyforward
