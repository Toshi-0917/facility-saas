set shell := ["bash", "-cu"]

up:
    docker compose up -d

down:
    docker compose down

restart:
    just down
    just up

build:
    docker compose build

ps:
    docker compose ps

bash:
    docker compose exec app bash

art cmd:
    docker compose exec app php artisan {{cmd}}

migrate:
    docker compose exec app php artisan migrate

seed:
    docker compose exec app php artisan db:seed

test:
    docker compose exec app php artisan test

tinker:
    docker compose exec app php artisan tinker

npm script:
    docker compose exec app npm run {{script}}

npm-install:
    docker compose exec app npm install

logs-app:
    docker compose logs -f app

logs-web:
    docker compose logs -f web

logs-db:
    docker compose logs -f db

logs-cache:
    docker compose logs -f cache

prune:
    docker system prune -f
