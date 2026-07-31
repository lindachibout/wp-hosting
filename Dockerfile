FROM composer:2 AS composer

FROM php:8.3-apache

RUN apt-get update && apt-get install -y libzip-dev unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

RUN a2enmod rewrite \
    && echo '<Directory /var/www/html>AllowOverride All</Directory>' \
       >> /etc/apache2/apache2.conf

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Cloud Run injecte $PORT au démarrage — Apache doit écouter dessus (start.sh
# fait la substitution au lancement du conteneur, pas au build).
ENV PORT=8080
EXPOSE 8080

CMD ["/start.sh"]
