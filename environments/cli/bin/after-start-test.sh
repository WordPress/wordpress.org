#!/bin/bash
#
# Runs after wp-env start for the test environment.
# Installs PHPUnit 11 and Yoast polyfills in the test container.
#

CONFIG="--config cli/.wp-env.test.json"
RUN="npx wp-env $CONFIG run tests-cli"

echo "Installing PHPUnit 11 and polyfills..."
$RUN composer global require -W phpunit/phpunit:^11.0 2>&1
$RUN composer require --dev yoast/phpunit-polyfills:^4.0 --working-dir=/wordpress-phpunit 2>&1
