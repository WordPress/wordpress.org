FROM wordpress:5.0.3-fpm

# Move WordPress files to Working Directory
RUN mv /usr/src/wordpress /usr/src/public_html
WORKDIR /usr/src/public_html

# Install and configure nginx
RUN apt-get update
RUN apt-get install nginx -y
COPY nginx.conf /etc/nginx/sites-enabled/wordcamp.conf
COPY php-fpm.conf /usr/local/etc/php-fpm.d/zz-www.conf

# Install and configure SSL
COPY wordcamp.ssl.conf /var/wordcamp.ssl.conf
RUN cd ~ && openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout wordcamp.key -out wordcamp.crt -config /var/wordcamp.ssl.conf
RUN cp ~/wordcamp.crt /etc/ssl/certs/wordcamp.crt
RUN cp ~/wordcamp.key /etc/ssl/private/wordcamp.key

# Install wkhtmltopdf for camptix invoices. See https://stackoverflow.com/a/38336153/1845153
RUN curl -L https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.4/wkhtmltox-0.12.4_linux-generic-amd64.tar.xz > wkhtmltox.tar.xz
RUN tar xvf wkhtmltox.tar.xz
RUN mv wkhtmltox/bin/wkhtmlto* /usr/bin/

# Install cli
RUN curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
RUN chmod +x wp-cli.phar
RUN mv wp-cli.phar /usr/local/bin/wp
RUN apt-get install less -y

# Install Mail catcher
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

CMD tail -f /dev/null

