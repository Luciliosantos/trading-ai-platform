FROM php:8.3-cli
RUN docker-php-ext-install pdo pdo_pgsql
WORKDIR /app
COPY . .
CMD ["php","worker.php"]
