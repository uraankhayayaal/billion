FROM php:8.2-fpm-alpine as build
RUN apk update && \
    apk add --no-cache bash build-base gcc autoconf libmcrypt-dev \
    g++ make openssl-dev \
    php-openssl \
    php-bcmath \
    php-curl \
    php-tokenizer \
    php-json \
    php-xml \
    php-zip \
    php-mbstring
RUN pecl install redis \
    && docker-php-ext-enable redis
COPY ./php.ini /usr/local/etc/php/conf.d/base.ini
COPY ./php-fpm.ini /usr/local/etc/php-fpm.d/www.conf
WORKDIR /app

FROM build as compose
RUN curl -sS https://getcomposer.org/installer | php -- --filename=composer --install-dir=/usr/local/bin
RUN apk add --no-cache git
RUN git config --global url."https://{GITHUB_API_TOKEN}:@github.com/".insteadOf "https://github.com/"
COPY ./composer.json /app/composer.json
COPY ./composer.lock /app/composer.lock

FROM compose as dev_deps
RUN apk add --no-cache linux-headers \
    && pecl install xdebug-3.3.2 \
    && docker-php-ext-enable xdebug
COPY ./xdebug.ini /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
RUN composer install

FROM dev_deps
CMD php-fpm