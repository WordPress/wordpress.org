#!/bin/bash
#
# Runs after wp-env start. Sets up the site like WordPress.tv and imports its videos once.
#

CONFIG="--config wordpress-tv/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

$WP wp option update blogname 'WordPress.tv'
$WP wp option update blogdescription 'WordPress-related videos curated and moderated by the WordPress.org community'
$WP wp rewrite structure '/%year%/%monthnum%/%day%/%postname%/' --hard
# The theme registers the taxonomies the import assigns.
$WP wp theme activate wptv-27 || exit 1

# Anonymous submissions are filed under this account.
if ! $WP wp user get anonvideoupload --field=ID > /dev/null 2>&1; then
	$WP wp user create anonvideoupload anonvideoupload@example.org --role=author --porcelain > /dev/null && echo "Created user: anonvideoupload"
fi

$WP wp eval-file wp-content/env-bin/import-videos.php
