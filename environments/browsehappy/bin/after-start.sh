#!/bin/bash
#
# Runs after wp-env start. Sets site name, description and activates the
# Browse Happy theme.

CONFIG="--config browsehappy/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Set site title and description to match the public site.
$WP wp option update blogname 'Browse Happy'
$WP wp option update blogdescription 'Online. Worry free. Upgrade your browser today!'

# Activate the browsehappy theme.
$WP wp theme activate "Browse Happy"

echo "Browse Happy environment ready!"