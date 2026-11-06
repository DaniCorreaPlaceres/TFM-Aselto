FROM php:8.5-fpm-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libzip-dev \
    && docker-php-ext-install intl pdo_mysql zip bcmath \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/php.ini /usr/local/etc/php/conf.d/aselto.ini
COPY docker/php/entrada.sh /usr/local/bin/entrada

RUN chmod +x /usr/local/bin/entrada

ENTRYPOINT ["entrada"]
CMD ["php-fpm"]
