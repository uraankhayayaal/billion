FROM php:8.4-cli-alpine as build
RUN apk update && \
    apk add --no-cache bash build-base gcc autoconf libmcrypt-dev brotli-dev \
    g++ make openssl-dev \
    php-openssl \
    php-bcmath \
    php-curl \
    php-tokenizer \
    php-json \
    php-xml \
    php-zip \
    php-mbstring \
    php-brotli \
    php-pcntl \
    && pecl install redis swoole \
    && docker-php-ext-enable redis swoole
COPY ./php.ini /usr/local/etc/php/php.ini
RUN docker-php-ext-configure pcntl --enable-pcntl \
    && docker-php-ext-install pcntl pdo pdo_mysql
WORKDIR /app
RUN curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin
ENV COMPOSER_CACHE_DIR=/tmp/.composer/cache

FROM build as dev
RUN apk add --no-cache npm \
    && npm install -g --save-dev chokidar
CMD ["php", "artisan", "octane:start", "--host=0.0.0.0", "--watch"]

FROM build as prod
ADD . .
RUN chown -Rf www-data:www-data /app
USER www-data
RUN --mount=type=cache,target=/tmp/.composer/cache,gid=82,uid=82 composer install -o -n --no-progress --no-dev
RUN php artisan octane:install \
    && php artisan key:generate \
    && php artisan config:cache
USER root
CMD ["php", "artisan", "octane:start", "--host=0.0.0.0"]