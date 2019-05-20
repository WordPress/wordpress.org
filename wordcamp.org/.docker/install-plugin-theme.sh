#!/bin/bash

# Install plugins
echo "Installing plugins"
PLUGINS="akismet buddypress bbpress jetpack wp-multibyte-patch wordpress-importer polldaddy liveblog wp-super-cache custom-content-width camptix-network-tools email-post-changes tagregator supportflow camptix-pagseguro camptix-payfast-gateway camptix-trustpay camptix-trustcard camptix-mercadopago camptix-kdcpay-gateway campt-indian-payment-gateway camptix"
cd /usr/src/public_html/wp-content/plugins
for plugin in $PLUGINS
do \
    curl https://downloads.wordpress.org/plugin/$plugin.latest-stable.zip > $plugin.zip
    unzip -u -o $plugin.zip
    rm $plugin.zip
done

# Install themes
echo "Installing themes"
THEMES="twentyten twentyeleven twentytwelve twentythirteen twentyfourteen"
cd /usr/src/public_html/wp-content/themes
for theme in $THEMES
do
    curl https://downloads.wordpress.org/theme/$theme.latest-stable.zip > $theme.zip
    unzip -u -o $theme.zip
    rm $theme.zip
done