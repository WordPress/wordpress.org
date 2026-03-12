#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, creates pages, and imports plugins.
#

CONFIG="--config plugin-directory/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Install CLI tools needed by the plugin directory (svn, unzip, etc.).
# Both containers have passwordless sudo for the host user.
WPENV="npx wp-env $CONFIG run"

# wordpress container (Debian).
$WPENV wordpress sudo bash -c \
	'command -v svn > /dev/null || (apt-get -qy update && apt-get -qy install subversion unzip zip)'

# cli container (Alpine).
$WPENV cli sudo sh -c \
	'command -v svn > /dev/null || apk add --no-cache subversion unzip zip coreutils'

# Set up permalinks.
$WP wp rewrite structure '/%postname%/' --hard

# Create pages that exist on wordpress.org/plugins (if they don't already exist).
# Parent: /developers/
$WP wp post create --post_type=page --post_status=publish --post_title='Developer Information' --post_name='developers' --porcelain 2>/dev/null || true
DEVELOPERS_ID=$($WP wp post list --post_type=page --name=developers --field=ID 2>/dev/null)

if [ -n "$DEVELOPERS_ID" ]; then
	# Children of /developers/
	$WP wp post create --post_type=page --post_status=publish --post_title='Add your Plugin' --post_name='add' --post_parent=$DEVELOPERS_ID --porcelain 2>/dev/null || true
	$WP wp post create --post_type=page --post_status=publish --post_title='Readme Validator' --post_content='[readme-validator]' --post_name='readme-validator' --post_parent=$DEVELOPERS_ID --porcelain 2>/dev/null || true
	$WP wp post create --post_type=page --post_status=publish --post_title='Block Plugin Checker' --post_content='[block-validator]' --post_name='block-plugin-validator' --post_parent=$DEVELOPERS_ID --porcelain 2>/dev/null || true
	$WP wp post create --post_type=page --post_status=publish --post_title='Release Management' --post_content='[release-confirmation]' --post_name='releases' --post_parent=$DEVELOPERS_ID --porcelain 2>/dev/null || true
fi

# Create stub database tables that exist outside WordPress on production.
$WP wp db import wp-content/env-bin/database-tables.sql

# Create browse section terms.
for SECTION in featured popular beta blocks new favorites; do
	$WP wp term create plugin_section "$SECTION" --slug="$SECTION" 2>/dev/null || true
done

# Import plugins from wordpress.org.
$WP wp eval-file wp-content/env-bin/import-plugins.php
