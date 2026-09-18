#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks and seeds the forum network.
#
# wp-env creates the network and its .htaccess itself (the "multisite" key in
# .wp-env.json), so this only fills the network with content.
#

set -euo pipefail

CONFIG="--config support-forums/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Read the URL back rather than assuming the port, which WP_ENV_PORT and
# `wp-env start --auto-port` both change.
SITE_URL="$( $WP wp option get siteurl --skip-plugins --skip-themes | tr -d '[:space:]' )"

# Reapplied on every start because wp-env resets the structure whenever it
# reconfigures WordPress. No --hard: it is a no-op on multisite.
echo "Setting up permalinks..."
$WP wp rewrite structure '/%postname%/'

# Create the stub tables that live outside WordPress on production.
echo "Creating stub database tables..."
$WP wp db import wp-content/env-bin/database-tables.sql

# Seed sub-sites, users, roles and forums. Idempotent — the seed gates itself
# on the wporg_support_env_seeded option, which `npm run support:refresh` clears.
$WP wp eval-file wp-content/env-bin/seed.php

# Seed each sub-site in its own request, now that the plugins seed.php activated
# there are loaded and have registered their post types and taxonomies.
for SUBSITE in plugins themes rosetta; do
	$WP wp eval-file wp-content/env-bin/seed-site.php --url="$SITE_URL/$SUBSITE"
done

echo "Support Forums environment ready!"
