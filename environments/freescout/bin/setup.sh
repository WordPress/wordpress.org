#!/usr/bin/env bash
#
# Prepares the local FreeScout: app key, database, admin, mailboxes, modules, and sample email.
# Idempotent; runs inside the app container as www-data on every `npm run freescout:start`.

set -euo pipefail

app=/var/www/html

if [ ! -s "$app/storage/app-key" ]; then
	php -r 'echo "base64:" . base64_encode( random_bytes( 32 ) );' > "$app/storage/app-key"
fi

# Switching branches can remove modules FreeScout still has cached, and then nothing boots; empty the file cache directly.
rm -rf "$app"/storage/framework/cache/data/*

# Drop config cached with outdated environment values; the full cache clear needs a migrated database.
php "$app/artisan" --no-interaction config:clear > /dev/null
php "$app/artisan" --no-interaction migrate --force
php /srv/env/bin/seed.php
touch "$app/storage/.installed"

# FreeScout's installer links public/storage, which serves user photos and attachments.
if [ ! -e "$app/public/storage" ]; then
	php "$app/artisan" --no-interaction storage:link > /dev/null
fi

# Switch on every module, as an admin would in production. Installed modules keep their public/modules/ link, and get
# new migrations from freescout:after-app-update below.
for manifest in "$app"/Modules/*/module.json; do
	[ -f "$manifest" ] || continue
	# module:enable looks modules up by their name in module.json, which can differ from the folder.
	# shellcheck disable=SC2016 # $argv is PHP, not shell.
	IFS=$'\t' read -r name alias < <( php -r '$m = json_decode( file_get_contents( $argv[1] ) ); echo $m->name, "\t", $m->alias, "\n";' "$manifest" )

	if [ "$alias" = wporgwebhooks ] && [[ "$WPORG_API_URL" != http://mock-api:* ]]; then
		# webhook.php counts events from agents it doesn't know, so local ones would end up in production's stats.
		php "$app/artisan" --no-interaction module:disable "$name" > /dev/null
		# Events queued against the mock would go out with the real URL and secret, which the job reads when it runs.
		# shellcheck disable=SC2016 # $app is PHP, not shell.
		php -r 'require "/var/www/html/vendor/autoload.php"; $app = require "/var/www/html/bootstrap/app.php"; $app->make( Illuminate\Contracts\Console\Kernel::class )->bootstrap(); DB::table( "jobs" )->where( "payload", "like", "%WPOrgWebhooks%" )->delete();'
		echo "$name is off: it would send local conversations to WordPress.org."
		continue
	fi

	php "$app/artisan" --no-interaction module:enable "$name"

	if [ ! -L "$app/public/modules/$alias" ]; then
		php "$app/artisan" --no-interaction freescout:module-install "$alias"
		# module-install reports a module it can't find, but still exits with 0.
		if [ ! -L "$app/public/modules/$alias" ]; then
			echo "Could not install $name ($alias)." >&2
			exit 1
		fi
	fi
done

# What production runs after every deploy.
php "$app/artisan" --no-interaction freescout:after-app-update

# The admin logs in as "admin" at the mock WordPress.org login.
if [ -f "$app/Modules/WPOrgSSO/module.json" ]; then
	php "$app/artisan" --no-interaction wporgsso:connect admin@wordpress.test admin > /dev/null
fi

# Deliver the sample emails once; FreeScout's scheduler fetches them within a minute.
if [ ! -f "$app/storage/.wporg-fixtures-sent" ]; then
	for eml in /srv/env/fixtures/*.eml; do
		rcpt="$( sed -n 's/^To: .*<\(.*\)>.*/\1/p; s/^To: \([^<]*@[^ ]*\)$/\1/p' "$eml" | head -n 1 )"
		# GreenMail has no healthcheck, so it may still be starting.
		curl --silent --show-error --retry 10 --retry-connrefused --retry-delay 1 --url smtp://greenmail:3025 --mail-from sender@example.com --mail-rcpt "$rcpt" --upload-file "$eml"
	done
	touch "$app/storage/.wporg-fixtures-sent"
fi

# Lets the entrypoint start the scheduler, now that the modules the queue worker may run are settled.
touch /tmp/wporg-setup-done

echo
if [ -f "$app/Modules/WPOrgSSO/module.json" ]; then
	echo "FreeScout: ${APP_URL}  (log in as \"admin\" at the mock WordPress.org login)"
else
	echo "FreeScout: ${APP_URL}  (admin@wordpress.test / password)"
fi
echo "Outgoing mail: http://127.0.0.1:${MAILPIT_PORT:-8891}"
