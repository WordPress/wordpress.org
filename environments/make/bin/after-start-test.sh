#!/bin/bash
#
# Runs after wp-env start for the test environment.
# Installs PHPUnit 11 and Yoast polyfills in the test container, and creates the
# stub tables that live outside WordPress on production.
#

set -euo pipefail

CONFIG="--config make/.wp-env.test.json"
RUN="npx wp-env $CONFIG run cli"

echo "Installing PHPUnit 11 and polyfills..."
$RUN composer global require -W phpunit/phpunit:^11.0 2>&1
$RUN composer require --dev yoast/phpunit-polyfills:^4.0 --working-dir=/wordpress-phpunit 2>&1

# The file only uses CREATE TABLE IF NOT EXISTS, so re-importing it on every
# start is safe.
echo "Creating stub database tables..."
npx wp-env $CONFIG run cli -- wp db import wp-content/env-bin/database-tables.sql 2>&1
$RUN -- wp db import wp-content/env-bin/database-tables.sql 2>&1
