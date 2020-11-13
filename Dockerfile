FROM php:7.1-apache
MAINTAINER Rob Zeeman <rob.zeeman@di.huc.knaw.nl>
EXPOSE 80 443
COPY --chown=www-data:www-data  ./src/site/* /var/www/html/
COPY --chown=www-data:www-data  ./src/site/static/ /var/www/html/static/
COPY --chown=www-data:www-data  ./src/service/ /var/www/html/isidore_service/

RUN  apt-get update && apt-get install -y \
            libc-client-dev \
            libkrb5-dev \
            libpq-dev \
            vim \
        && docker-php-ext-install pdo_mysql mysqli pdo_pgsql \
        && apachectl restart

RUN a2enmod rewrite