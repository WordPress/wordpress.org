#!/bin/bash

# Ideally we'd want this to run automatically during setup, but we cannot because everything in wp-content folder will
# be overwritten when we mount the `wp-content` directory from volume at docker start.

# Install plugins
echo "Installing plugins"
wp --allow-root plugin install akismet buddypress bbpress jetpack wp-multibyte-patch wordpress-importer polldaddy pwa liveblog wp-super-cache custom-content-width camptix-network-tools email-post-changes tagregator supportflow camptix-pagseguro camptix-payfast-gateway camptix-trustpay camptix-trustcard camptix-mercadopago camptix-kdcpay-gateway campt-indian-payment-gateway camptix classic-editor

# Install themes
echo "Installing themes"
wp --allow-root theme install twentyten twentyeleven twentytwelve twentythirteen twentyfourteen twentyfifteen twentysixteen twentyseventeen twentynineteen p2
