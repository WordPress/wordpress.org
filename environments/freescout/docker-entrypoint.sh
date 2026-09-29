#!/usr/bin/env bash
#
# Runs nginx, PHP-FPM, and FreeScout's scheduler, which production would run from cron.
# They share this container so they see the same module symlinks; the scheduler also starts the queue worker.

set -euo pipefail

runuser -u www-data -- bash -c '
	while true; do
		php /var/www/html/artisan schedule:run > /dev/null 2>&1 || true
		sleep 60
	done
' &

php-fpm --daemonize
exec nginx -g 'daemon off;'
