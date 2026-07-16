#!/bin/bash
#
# Runs after wp-env start for the test environment.
# Installs PHPUnit 11 and Yoast polyfills, and creates the stub tables tests read.
#

CONFIG="--config plugin-directory/.wp-env.test.json"
RUN="npx wp-env $CONFIG run tests-cli"

echo "Installing PHPUnit 11 and polyfills..."
$RUN composer global require -W phpunit/phpunit:^11.0 2>&1
$RUN composer require --dev yoast/phpunit-polyfills:^4.0 --working-dir=/wordpress-phpunit 2>&1

# Create stub database tables that exist outside WordPress on production.
echo "Creating stub database tables..."
$RUN -- wp db import wp-content/env-bin/database-tables.sql
