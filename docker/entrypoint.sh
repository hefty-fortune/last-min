#!/bin/sh
set -eu

cd /app

if [ -f composer.json ]; then
  mkdir -p /app/vendor
  needs_install=0

  if [ ! -f /app/vendor/autoload.php ]; then
    needs_install=1
  elif [ -f composer.lock ]; then
    current_lock_hash="$(sha1sum composer.lock | awk '{print $1}')"
    installed_lock_hash="$(cat /app/vendor/.composer.lock.hash 2>/dev/null || true)"

    if [ "$current_lock_hash" != "$installed_lock_hash" ]; then
      needs_install=1
    fi
  fi

  if [ "$needs_install" -eq 1 ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist

    if [ -f composer.lock ]; then
      sha1sum composer.lock | awk '{print $1}' > /app/vendor/.composer.lock.hash
    else
      rm -f /app/vendor/.composer.lock.hash
    fi
  else
    echo "Composer dependencies already up to date."
  fi
fi

echo "Running migrations..."
php bin/migrate.php

echo "Starting PHP server..."
exec "$@"