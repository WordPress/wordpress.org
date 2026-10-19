#!/usr/bin/env bash
#
# Runs nginx, PHP-FPM, and FreeScout's scheduler, which production would run from cron.
# They share this container so they see the same module symlinks; the scheduler also starts the queue worker.

set -euo pipefail

# The scheduler starts the queue worker, which must wait until setup.sh has decided which modules are on.
rm -f /tmp/wporg-setup-done
runuser -u www-data -- bash -c '
	until [ -f /tmp/wporg-setup-done ]; do
		sleep 1
	done
	while true; do
		php /var/www/html/artisan schedule:run > /dev/null 2>&1 || true
		sleep 60
	done
' &

php-fpm --daemonize
exec nginx -g 'daemon off;'
