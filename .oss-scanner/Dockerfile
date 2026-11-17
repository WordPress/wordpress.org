# WordPress.org Meta for Anthropic's OSS Scanner: every local environment in environments/, built into one image.
#
# /src                  the wordpress.org git checkout (the repository named in project.yaml), owned by www-data, as
#                       wp-env's host user owns it
# /srv/sites/<site>     a WordPress for each environments/*/.wp-env*.json, each served by Apache on its own port
# /var/www/html         FreeScout, with the freescout.wordpress.net modules, on port 8890
#
# The work is in .oss-scanner/bin: wporg-provision builds the sites the way wp-env would, and `wporg start` starts
# MariaDB, Apache and FreeScout's services. threat_model.md says how to use them.
FROM php:8.4-apache-trixie

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1 \
    COMPOSER_MEMORY_LIMIT=-1 \
    WP_CLI_ALLOW_ROOT=1

# The PHP extensions are the official WordPress image's, plus pcntl and pdo_mysql for FreeScout. Java runs GreenMail.
# MariaDB's redo log is cut from 96 MB before the package creates it: the image carries a copy in every layer that writes
# to the database.
RUN mkdir -p /etc/mysql/mariadb.conf.d \
    && printf '[mariadbd]\ninnodb_log_file_size=16M\n' > /etc/mysql/mariadb.conf.d/99-wporg.cnf \
    && apt-get update && apt-get install -y --no-install-recommends \
      ca-certificates curl git jq less libnss-wrapper procps rsync subversion sudo unzip zip \
      mariadb-server mariadb-client openjdk-21-jre-headless python3 python3-yaml \
      libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev libavif-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp --with-avif \
    && docker-php-ext-install -j"$(nproc)" bcmath exif gd intl mysqli pcntl pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node runs the npm scripts in environments/, as environments/.nvmrc asks.
COPY --from=node:24-trixie-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:24-trixie-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s ../lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s ../lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && node --version && npm --version

RUN curl -fsSL -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp \
    && wp --info

# wp-env's PHP settings, with errors logged (WP_DEBUG still shows them on pages, as it does under wp-env), and OPcache
# picking up a patch on the next request.
RUN { echo 'memory_limit=512M'; \
      echo 'upload_max_filesize=1G'; \
      echo 'post_max_size=1G'; \
      echo 'error_reporting=E_ALL'; \
      echo 'display_errors=Off'; \
      echo 'log_errors=On'; \
      echo 'error_log=/var/log/php-error.log'; \
      echo 'opcache.validate_timestamps=1'; \
      echo 'opcache.revalidate_freq=0'; \
    } > /usr/local/etc/php/conf.d/oss-scanner.ini \
    && touch /var/log/php-error.log && chmod 666 /var/log/php-error.log

# Every site's vhost is added by wporg-provision. Plugins and themes are links into /src, so Apache follows them there.
RUN a2enmod rewrite headers expires \
    && a2dissite 000-default \
    && printf '%s\n' 'ServerName localhost' \
         '<Directory /srv/sites>' '  Options -MultiViews +FollowSymLinks' '  AllowOverride All' '  Require all granted' '</Directory>' \
         '<Directory /var/www/html>' '  Options -MultiViews +FollowSymLinks' '  AllowOverride All' '  Require all granted' '</Directory>' \
         '<Directory /src>' '  Require all granted' '</Directory>' \
         '<Directory /opt/wporg>' '  Require all granted' '</Directory>' \
         > /etc/apache2/conf-available/wporg.conf \
    && a2enconf wporg \
    && : > /etc/apache2/ports.conf

# Commands in a site run as www-data, the web server's user, with sudo, as wp-env's host user has. www-data owns /src,
# so git has to be told root may use it too.
RUN echo 'www-data ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/www-data \
    && git config --system --add safe.directory /src \
    && mkdir -p /opt/wporg/home /opt/wporg/composer-cache /srv/sites \
    && chown www-data:www-data /opt/wporg/home /opt/wporg/composer-cache

# scanner contract: the checkout lives inside the image, at /src
COPY --chown=www-data:www-data . /src
WORKDIR /src

# wp-env is replaced by .oss-scanner/bin/wp-env, which `npx wp-env` and the npm scripts find in node_modules/.bin.
RUN ln -s /src/.oss-scanner/bin/wporg /src/.oss-scanner/bin/wporg-provision /src/.oss-scanner/bin/wp-env /usr/local/bin/ \
    && mkdir -p /src/environments/node_modules/.bin \
    && ln -s ../../../.oss-scanner/bin/wp-env /src/environments/node_modules/.bin/wp-env \
    && chown -R www-data:www-data /src/environments/node_modules

# PHPUnit and PHPCS for the standalone suites and coding standards, from the root composer.json.
RUN composer install --no-progress && chown -R www-data:www-data /src/vendor

# Build FreeScout, then every WordPress site. This downloads WordPress, plugins and themes, and imports content from
# the live WordPress.org, so it has to happen here, while the network is up. A site that fails is marked failed rather
# than failing the build, so the others still get scanned.
RUN wporg start && { wporg-provision freescout || true; } && wporg stop
RUN wporg start && wporg-provision && wporg stop
