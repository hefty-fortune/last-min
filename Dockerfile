FROM php:8.3-cli

WORKDIR /app

RUN docker-php-ext-install pdo pdo_pgsql

COPY . /app

EXPOSE 8080

CMD ["sh", "-c", "php bin/migrate.php && php -S 0.0.0.0:8080 -t public"]
