#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."

command -v php >/dev/null
command -v composer >/dev/null
command -v node >/dev/null
command -v npm >/dev/null

php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);'
node -e 'process.exit(Number(process.versions.node.split(".")[0]) >= 20 ? 0 : 1)'
for extension in ctype fileinfo iconv mbstring pdo pdo_sqlite; do
  php -r "exit(extension_loaded('$extension') ? 0 : 1);"
done

if [ ! -f .env.local ]; then
  secret="$(php -r 'echo bin2hex(random_bytes(32));')"
  sed "s/replace-with-a-long-random-secret/$secret/" .env.local.dist > .env.local
  printf '%s\n' 'Creato .env.local con APP_SECRET casuale.'
fi

if [ -f composer.lock ]; then
  composer install --no-interaction --prefer-dist --no-scripts
else
  printf '%s\n' 'composer.lock assente: risoluzione iniziale e creazione del lock file.'
  composer update --no-interaction --prefer-dist --no-scripts
fi
composer check-platform-reqs

if [ -f package-lock.json ]; then
  npm ci
else
  printf '%s\n' 'package-lock.json assente: installazione iniziale e creazione del lock file.'
  npm install --package-lock
fi
npm run build
npm test
php scripts/doctrine-config-contract.php
php scripts/symfony-api-contract.php
php scripts/attachment-storage-contract.php
php bin/console lint:yaml config
php bin/console lint:twig templates
php bin/console cache:clear --env=dev
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:migrations:up-to-date --no-interaction
php bin/console doctrine:schema:validate

php bin/console app:user:create --role=partner --skip-if-active-partner-exists
printf '%s\n' 'Installazione completata correttamente.'
