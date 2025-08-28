#!/bin/sh

cd "$(dirname "$0")" || exit 1

git pull origin main
#composer install --no-interaction --no-dev --prefer-dist
# migrate.php ...
