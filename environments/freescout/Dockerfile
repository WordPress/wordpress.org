# syntax=docker/dockerfile:1
# FreeScout for local development of the freescout.wordpress.net modules: nginx and PHP-FPM, as FreeScout recommends.
FROM php:8.3-fpm

RUN apt-get update \
	&& apt-get install -y --no-install-recommends curl git libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libzip-dev nginx procps unzip \
	&& docker-php-ext-configure gd --with-freetype --with-jpeg \
	&& docker-php-ext-install -j"$( nproc )" gd intl pcntl pdo_mysql zip \
	&& rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

COPY nginx.conf /etc/nginx/sites-enabled/freescout.conf
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Attachments up to nginx's client_max_body_size.
RUN printf 'upload_max_filesize = 20M\npost_max_size = 20M\n' > /usr/local/etc/php/conf.d/wporg-freescout.ini

# Accepts a branch or release tag. Unlike a `git clone` step, ADD checks the ref on every build, so a branch that moved
# is fetched again instead of coming from the build cache.
ARG FREESCOUT_REF=dist
ADD --keep-git-dir=false https://github.com/freescout-help-desk/freescout.git#${FREESCOUT_REF} /var/www/html

# dist's bundled vendor/ is stripped down and lacks PHPUnit, so it's reinstalled from the release's lock file, with dev
# dependencies. FreeScout's own CI ignores the lock's outdated PHP constraint too.
ENV COMPOSER_ALLOW_SUPERUSER=1
WORKDIR /var/www/html
RUN rm -rf vendor \
	&& composer install --no-interaction --no-progress --ignore-platform-req=php \
	&& touch .env \
	&& chown -R www-data:www-data /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/wporg-freescout-entrypoint
ENTRYPOINT [ "wporg-freescout-entrypoint" ]
