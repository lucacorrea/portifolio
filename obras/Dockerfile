FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libsqlite3-dev \
    && rm -rf /var/lib/apt/lists/* \
    && a2enmod headers rewrite \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql pdo_sqlite \
    && sed -ri 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
WORKDIR /var/www
COPY . /var/www/
RUN mkdir -p storage/uploads storage/logs && chown -R www-data:www-data storage && chmod 750 storage storage/uploads storage/logs
EXPOSE 80
