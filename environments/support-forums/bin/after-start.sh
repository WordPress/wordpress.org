#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, creates pages, and imports plugins.
#

set -euo pipefail

SETUP_VALIDATION_FILE="/var/www/html/.wp-env-setup-complete"

CONFIG="--config support-forums/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Check if the setup has already been completed.
if $WP bash -c "[ -f '$SETUP_VALIDATION_FILE' ]"; then
  echo "Setup already completed. Skipping first-run environment configuration."
  exit 0
fi

###
# Create multisite framework
###
echo "Setting up multisite framework..."
$WP wp core multisite-convert

# Network enable the themes
$WP wp theme enable wporg-support-2024

# Create the plugins site.
echo "Creating sub-sites..."
$WP wp site create --slug=plugins --title=Plugins

# Create the site.
$WP wp site create --slug=themes --title=Themes

# WordPress, per now, does not properly identify port numbers in multisite URLs when creating
# sites. Because of this, when we set it up using `wp-env`, followed by a `wp core multisite-convert`
# from the WP-CLI package, strange things happen.
# This search-replace is in place to undo the weirdness, and make the environment workable.
echo "Fixing multisite URLs..."
$WP wp search-replace 'localhost:8888/:8888' 'localhost:8888' --skip-plugins --skip-themes --all-tables
$WP wp search-replace 'localhost8888' 'localhost:8888' --skip-plugins --skip-themes --all-tables

###
# Create the various users we will need
###
echo "Creating users..."

# Add a forum keymaster account (User ID 2).
$WP wp user create keymaster keymaster@example.com --role=subscriber --user_pass=password

# Add a forum moderator account (User ID 3).
$WP wp user create moderator moderator@example.com --role=subscriber --user_pass=password

# Add a plugin author (User ID 4).
$WP wp user create pluginauthor plugin-author@example.com --role=subscriber --user_pass=password

# Add a plugin contributor (User ID 5).
$WP wp user create plugincontributor plugin-contributor@example.com --role=subscriber --user_pass=password

# Add a plugin support representative (User ID 6).
$WP wp user create pluginsupport plugin-support@example.com --role=subscriber --user_pass=password

# Add a theme author (User ID 7).
$WP wp user create themeauthor theme-author@example.com --role=subscriber --user_pass=password

# Add a site support representative (user ID 8) - Currently unused, but added in anticipation.
$WP wp user create themesupport theme-support@example.com --role=subscriber --user_pass=password

# Add a forum visitor
$WP wp user create visitor visitor@example.com --role=subscriber --user_pass=password

###
# Set up the `plugins` sub-site with associated content.
###

echo "Setting up minimum required plugins sub-site..."

# Add site-specific plugins.
$WP wp plugin install jetpack --activate --url=localhost:8888/plugins
$WP wp plugin activate plugin-directory --url=localhost:8888/plugins

# Add the plugin-related roles to this subsite.
$WP wp user set-role pluginauthor subscriber --url=localhost:8888/plugins
$WP wp user set-role plugincontributor subscriber --url=localhost:8888/plugins
$WP wp user set-role pluginsupport subscriber --url=localhost:8888/plugins

# Add `Hello Dolly` as a plugin.
$WP wp post create --post_type=plugin --post_status=publish --post_author=4 --post_title='Hello Dolly' --post_date='2022-08-20 01:00:00' --post_modified='2022-08-20 01:00:00' --post_modified_gmt='2022-08-20 01:00:00' --url=localhost:8888/plugins

###
# Set up the `themes` sub-site with associated content.
###

echo "Setting up minimum required themes sub-site..."

# Set up the site identity.
$WP wp option update blogname 'WordPress.org Forums'

# Add site-specific plugins.
$WP wp plugin activate theme-directory --url=localhost:8888/themes

# Add the theme-related roles to this subsite.
$WP wp user set-role themeauthor subscriber --url=localhost:8888/themes
$WP wp user set-role themesupport subscriber --url=localhost:8888/themes

###
# Set up the primary network site, the forums, with associated content.
###

echo "Setting up forums site..."

# Add all roles to the forums.
$WP wp user set-role keymaster bbp_keymaster
$WP wp user set-role moderator bbp_moderator
$WP wp user set-role pluginauthor bbp_participant
$WP wp user set-role plugincontributor bbp_participant
$WP wp user set-role pluginsupport bbp_participant
$WP wp user set-role themeauthor bbp_participant
$WP wp user set-role themesupport bbp_participant
$WP wp user set-role visitor bbp_participant

# Add the initial set of forums.
$WP wp post create --post_type=forum --post_status=publish --post_title='Installing WordPress' --post_content='If you encounter any problems while setting up WordPress.' --post_date='2022-08-20 01:00:00' --post_modified='2022-08-20 01:00:00' --post_modified_gmt='2022-08-20 01:00:00'
$WP wp post create --post_type=forum --post_status=publish --post_title='Fixing WordPress' --post_content='For any problems encountered after setting up WordPress.' --post_date='2022-08-20 01:00:00' --post_modified='2022-08-20 01:00:00' --post_modified_gmt='2022-08-20 01:00:00'
$WP wp post create --post_type=forum --post_status=publish --post_title='Plugins' --post_content='Forum for plugin-specific support topics (hidden on WordPress.org).' --post_date='2022-08-20 01:00:00' --post_modified='2022-08-20 01:00:00' --post_modified_gmt='2022-08-20 01:00:00'
$WP wp post create --post_type=forum --post_status=publish --post_title='Themes' --post_content='Forum for theme-specific support topics (hidden on WordPress.org).' --post_date='2022-08-20 01:00:00' --post_modified='2022-08-20 01:00:00' --post_modified_gmt='2022-08-20 01:00:00'
$WP wp post create --post_type=forum --post_status=publish --post_title='Reviews' --post_content='Forum for plugin and theme reviews (hidden on WordPress.org).' --post_date='2022-08-20 01:00:00' --post_modified='2022-08-20 01:00:00' --post_modified_gmt='2022-08-20 01:00:00'

# Activate the forum theme.
$WP wp theme activate wporg-support-2024

echo "Setting up network-wide plugins..."

# Activate network-wide plugins
$WP wp plugin activate wporg-two-factor --network

# Setup, and flush rewrite rules.
echo "Setting up permalinks..."
$WP wp rewrite structure '/%postname%/'
$WP wp rewrite flush

$WP bash -c "touch '$SETUP_VALIDATION_FILE'"
