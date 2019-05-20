#!/bin/bash

# Install plugins
echo "Installing plugins"
wp --allow-root --url=wordcamp.test plugin install akismet buddypress bbpress jetpack wp-multibyte-patch wordpress-importer polldaddy liveblog wp-super-cache custom-content-width camptix-network-tools email-post-changes tagregator supportflow camptix-pagseguro camptix-payfast-gateway camptix-trustpay camptix-trustcard camptix-mercadopago camptix-kdcpay-gateway campt-indian-payment-gateway camptix

# Install themes
echo "Installing themes"
wp --allow-root --url=wordcamp.test theme install twentyten twentyeleven twentytwelve twentythirteen twentyfourteen
