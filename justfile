
start: wipe bootstrap

bootstrap: prereqs build up keygen db

prereqs:
    [ -e .env ] || cp .env.example .env
    mkdir -p .cache
    docker network create traefik  >/dev/null 2>&1 || true
    docker network create fair-net >/dev/null 2>&1 || true

build:
    docker compose build
    bin/dcrun composer install
    bin/dcrun yarn
    bin/dcrun yarn run build

up:
    docker compose up -d

keygen:
    bin/dcrun php artisan key:generate

db:
    bin/dcrun meta/bin/reset-database
    bin/dcrun meta/bin/reset-testing-database

wipe: clean
    docker compose down --volumes --remove-orphans

clean:
    rm -rf vendor node_modules .cache


