FROM php:7.1-apache
MAINTAINER Rob Zeeman <rob.zeeman@di.huc.knaw.nl>
EXPOSE 80 443
COPY --chown=www-data:www-data  ./src/site/* /var/www/html/
COPY --chown=www-data:www-data  ./src/site/static/ /var/www/html/static/
COPY --chown=www-data:www-data  ./src/service/ /var/www/html/isidore_service/


RUN apk update && \
    apk upgrade && \
    apk add \
    php7 php7-apache2 \
    php7-phar php7-json php7-zip php7-xml php7-xmlwriter php7-dom php7-curl php7-mbstring php7-sqlite3 php7-pdo_sqlite \
    php7-pgsql php7-pdo_pgsql php7-openssl php7-tokenizer php7-simplexml php7-ctype php7-iconv php7-xmlreader php7-pdo \
    php7-session \
    git curl zip jq postgresql-client


RUN a2enmod rewrite