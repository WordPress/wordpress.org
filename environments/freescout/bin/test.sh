#!/usr/bin/env bash
#
# Runs the freescout.wordpress.net module tests against the freescout-test database.
# Runs inside the app container as www-data; extra arguments go to PHPUnit.

set -euo pipefail

app=/var/www/html

# The tests delete FreeScout's cached config so the test environment applies; rebuild it when done.
trap 'php "$app/artisan" --no-interaction freescout:clear-cache > /dev/null' EXIT
php "$app/artisan" --no-interaction migrate --database=testing --force > /dev/null

"$app/vendor/bin/phpunit" --configuration /srv/wporg/phpunit.xml.dist "$@"
