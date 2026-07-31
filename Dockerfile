FROM composer:2 AS composer

FROM php:8.3-apache

RUN apt-get update && apt-get install -y libzip-dev unzip \
    && docker-php-ext-install mysqli pdo pdo_mysql zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

ENV COMPOSER_ALLOW_SUPERUSER=1
# Désactive le téléchargement/l'installation en parallèle — race condition
# connue de composer (DirectoryNotFoundException / "aborted by another
# package operation") sur les machines de build à beaucoup de vCPU comme
# Cloud Build. Confirmé en inspectant le binaire composer : ces deux
# variables existent réellement et contrôlent son parallélisme interne.
ENV COMPOSER_MAX_PARALLEL_HTTP=1
ENV COMPOSER_MAX_PARALLEL_PROCESSES=1

COPY composer.json composer.lock ./
# Le parallélisme désactivé ci-dessus (vérifié dans le code source de
# composer : ProcessExecutor::resetMaxJobs() lit bien ces variables à la
# construction) n'a pas suffi à éliminer la race condition en pratique sur
# Cloud Build — retry en ceinture-et-bretelles.
RUN for i in 1 2 3; do \
      composer install --no-dev --optimize-autoloader --no-interaction && break; \
      echo "composer install a échoué (tentative $i/3), nouvel essai..." >&2; \
      rm -rf vendor wordpress; \
      sleep 5; \
    done

COPY . .

# WordPress core est installé dans wordpress/ (cf. composer.json), pas à la
# racine — wp-config.php reste au niveau du projet, WP le trouve tout seul
# un niveau au-dessus de son propre répertoire (comportement standard).
RUN a2enmod rewrite \
    && sed -i 's#/var/www/html#/var/www/html/wordpress#g' /etc/apache2/sites-available/000-default.conf \
    && echo '<Directory /var/www/html/wordpress>AllowOverride All</Directory>' \
       >> /etc/apache2/apache2.conf

COPY docker/start.sh /start.sh
RUN chmod +x /start.sh

# Cloud Run injecte $PORT au démarrage — Apache doit écouter dessus (start.sh
# fait la substitution au lancement du conteneur, pas au build).
ENV PORT=8080
EXPOSE 8080

CMD ["/start.sh"]
