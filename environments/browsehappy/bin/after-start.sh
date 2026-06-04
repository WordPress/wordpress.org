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
# wp-env uses the last path segment as the theme slug, so the slug is 'public_html'
# rather than 'browsehappy' (from "themes": ["../browsehappy.com/public_html"]).
# This theme is not pushed to the svn repo in wp-content/themes as per other sites.
$WP wp theme activate public_html

echo "Browse Happy environment ready!"