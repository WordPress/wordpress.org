FROM php:7.2-fpm

# ----- Start section copied from https://github.com/docker-library/wordpress/blob/master/php7.2/fpm/Dockerfile ----- #

# install the PHP extensions we need
RUN set -ex; \
	\
	savedAptMark="$(apt-mark showmanual)"; \
	\
	apt-get update; \
	apt-get install -y --no-install-recommends \
		libjpeg-dev \
		libpng-dev \
	; \
	\
	docker-php-ext-configure gd --with-png-dir=/usr --with-jpeg-dir=/usr; \
	docker-php-ext-install gd mysqli opcache zip; \
	\
# reset apt-mark's "manual" list so that "purge --auto-remove" will remove all build dependencies
	apt-mark auto '.*' > /dev/null; \
	apt-mark manual $savedAptMark; \
	ldd "$(php -r 'echo ini_get("extension_dir");')"/*.so \
		| awk '/=>/ { print $3 }' \
		| sort -u \
		| xargs -r dpkg-query -S \
		| cut -d: -f1 \
		| sort -u \
		| xargs -rt apt-mark manual; \
	\
	apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false; \
	rm -rf /var/lib/apt/lists/*

# set recommended PHP.ini settings
# see https://secure.php.net/manual/en/opcache.installation.php
RUN { \
		echo 'opcache.memory_consumption=128'; \
		echo 'opcache.interned_strings_buffer=8'; \
		echo 'opcache.max_accelerated_files=4000'; \
		echo 'opcache.revalidate_freq=2'; \
		echo 'opcache.fast_shutdown=1'; \
		echo 'opcache.enable_cli=1'; \
	} > /usr/local/etc/php/conf.d/opcache-recommended.ini
# https://codex.wordpress.org/Editing_wp-config.php#Configure_Error_Logging
RUN { \
		echo 'error_reporting = 4339'; \
		echo 'display_errors = Off'; \
		echo 'display_startup_errors = Off'; \
		echo 'log_errors = On'; \
		echo 'error_log = /dev/stderr'; \
		echo 'log_errors_max_len = 1024'; \
		echo 'ignore_repeated_errors = On'; \
		echo 'ignore_repeated_source = Off'; \
		echo 'html_errors = Off'; \
	} > /usr/local/etc/php/conf.d/error-logging.ini

# ----- Copied section ends ----- #

RUN apt-get update

# Download WordPress source code. We are downloading from git so that we can checkout different WordPress versions by
# just checking out the branch.
RUN apt-get install git -y
RUN mkdir -p /usr/src/public_html/ && cd /usr/src/public_html/ && git clone git://github.com/WordPress/WordPress.git mu
RUN cd /usr/src/public_html/mu && git checkout 5.2-branch
WORKDIR /usr/src/public_html

# Install and configure nginx
RUN apt-get install nginx -y
COPY nginx.conf /etc/nginx/sites-enabled/wordcamp.conf
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-www.conf

# Install and configure SSL
COPY wordcamp.ssl.conf /var/wordcamp.ssl.conf
RUN cd ~ && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout wordcamp.key -out wordcamp.crt -config /var/wordcamp.ssl.conf
RUN cp ~/wordcamp.crt /etc/ssl/certs/wordcamp.crt
RUN cp ~/wordcamp.key /etc/ssl/private/wordcamp.key

# Add script to install plugin and themes
ADD install-plugin-theme.sh /usr/src/public_html/install-plugin-theme.sh

# Install `wkhtmltopdf` for WordCamp Docs, CampTix Invoices, etc. See https://stackoverflow.com/a/38336153/1845153
RUN curl -L https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.4/wkhtmltox-0.12.4_linux-generic-amd64.tar.xz > wkhtmltox.tar.xz
RUN apt-get install libxext6 -y
RUN tar xvf wkhtmltox.tar.xz
RUN mv wkhtmltox/bin/wkhtmlto* /usr/bin/
RUN rm -rf wkhtmltox*

# Install WP-CLI
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
RUN chmod +x wp-cli.phar
RUN mv wp-cli.phar /usr/local/bin/wp
RUN apt-get install less -y
COPY wp-cli.yml /usr/src/public_html/wp-cli.yml

# Install MailCatcher.
RUN apt-get install build-essential libsqlite3-dev ruby-dev -y
RUN gem install mailcatcher --no-ri --no-rdoc
RUN apt-get install ssmtp -y
RUN sed -i -e "s|;sendmail_path =|sendmail_path = /usr/sbin/ssmtp -t |" /usr/local/etc/php/php.ini-development
RUN sed -i -e "s/smtp_port = 25/smtp_port = 1025/" /usr/local/etc/php/php.ini-development
RUN chown root:mail /etc/ssmtp/ssmtp.conf
RUN sed -i -e "s/#FromLineOverride=YES/FromLineOverride=YES/" /etc/ssmtp/ssmtp.conf
RUN sed -i -e "s/mailhub=mail/mailhub=127.0.0.1:1025/" /etc/ssmtp/ssmtp.conf
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

# Utils
RUN apt-get install vim -y

COPY wp-config.php /usr/src/public_html/wp-config.php

# Replace nginx default conf because it conflicts with wordcamp.conf
RUN mv /etc/nginx/sites-enabled/wordcamp.conf /etc/nginx/sites-enabled/default

CMD tail -f /dev/null
