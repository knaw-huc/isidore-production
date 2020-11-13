FROM php:7.1-apache
MAINTAINER Rob Zeeman <rob.zeeman@di.huc.knaw.nl>
EXPOSE 80 443
COPY --chown=www-data:www-data  ./src/site/* /var/www/html/
COPY --chown=www-data:www-data  ./src/site/static/ /var/www/html/static/
COPY --chown=www-data:www-data  ./src/service/ /var/www/html/isidore_service/


ENV APCU_VERSION 5.1.7
RUN buildDeps=" \
        libicu-dev \
        zlib1g-dev \
        libsqlite3-dev \
        libpq-dev \
    " \
    && apt-get update \
    && apt-get install -y --no-install-recommends \
        $buildDeps \
        libicu52 \
        zlib1g \
        sqlite3 \
        git \
        php5-pgsql \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install \
        intl \
        mbstring \
        pdo_mysql \
        pdo_pgsql \
        pdo \
        pgsql \
        curl \
        pdo_sqlite \
    && apt-get purge -y --auto-remove $buildDeps
RUN pecl install \
        apcu-$APCU_VERSION \
        xdebug \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-enable --ini-name 05-opcache.ini \
        opcache \
    && docker-php-ext-enable --ini-name 20-apcu.ini \
        apcu
RUN a2enmod rewrite