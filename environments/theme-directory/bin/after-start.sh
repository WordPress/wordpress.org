#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, creates pages, and imports themes.
#

CONFIG="--config theme-directory/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"
WPENV="npx wp-env $CONFIG run"

# Install CLI tools.
echo "Installing CLI tools..."
$WPENV wordpress sudo bash -c \
	'command -v unzip > /dev/null || (apt-get -qy update && apt-get -qy install unzip zip) > /dev/null 2>&1'

$WPENV cli sudo sh -c \
	'command -v unzip > /dev/null || apk add --no-cache -q unzip zip coreutils > /dev/null 2>&1'

# Set up permalinks and tag base.
$WP wp rewrite structure '/%postname%/' --hard
$WP wp rewrite flush --hard

# Activate the theme.
$WP wp theme activate wporg-themes-2024

# Create pages that exist on wordpress.org/themes.
echo "Creating pages..."
$WP wp post create --post_type=page --post_status=publish --post_title='Commercial' --post_name='commercial' --porcelain > /dev/null 2>&1 && echo "  Created page: /commercial/" || true
$WP wp post create --post_type=page --post_status=publish --post_title='Getting Started' --post_name='getting-started' --porcelain > /dev/null 2>&1 && echo "  Created page: /getting-started/" || true
$WP wp post create --post_type=page --post_status=publish --post_title='Upload' --post_name='upload' --post_content='[wporg-themes-upload]' --porcelain > /dev/null 2>&1 && echo "  Created page: /upload/" || true

# Create stub database tables that exist outside WordPress on production.
$WP wp db import wp-content/env-bin/database-tables.sql

# Create business model terms.
echo "Creating taxonomy terms..."
$WP wp term create theme_business_model "Commercial" --slug=commercial > /dev/null 2>&1 && echo "  Created term: commercial" || true
$WP wp term create theme_business_model "Community" --slug=community > /dev/null 2>&1 && echo "  Created term: community" || true

# Import themes from wordpress.org.
$WP wp eval-file wp-content/env-bin/import-themes.php
