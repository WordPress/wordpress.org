#!/bin/bash
#
# Runs after wp-env start. Installs CLI tools, sets up the theme directory,
# seeds terms/pages/users/shops, and imports themes.
#

CONFIG="--config theme-directory/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Install CLI tools needed by the theme directory (svn, unzip, etc.).
# Both containers have passwordless sudo for the host user.
WPENV="npx wp-env $CONFIG run"

# wordpress container (Debian).
echo "Installing CLI tools..."
$WPENV wordpress sudo bash -c \
	'command -v svn > /dev/null || (apt-get -qy update && apt-get -qy install subversion unzip zip) > /dev/null 2>&1'

# cli container (Alpine).
$WPENV cli sudo sh -c \
	'command -v svn > /dev/null || apk add --no-cache -q subversion unzip zip coreutils > /dev/null 2>&1'

# Activate supporting plugins. The theme-directory activation hook creates the
# commercial/getting-started/upload pages and sets the permalink structure.
echo "Activating plugins..."
$WP wp plugin activate theme-check theme-directory

# Set up permalinks (activation sets these, but be explicit for reliability).
$WP wp rewrite structure '/%postname%/' --hard

# The themes list is paginated 12 per page on production.
$WP wp option update posts_per_page 12

# Activate the frontend theme.
$WP wp theme activate wporg-themes-2024 > /dev/null 2>&1 || true

# Create the special wordpressdotorg administrator used by the directory.
if ! $WP wp user get wordpressdotorg > /dev/null 2>&1; then
	$WP wp user create wordpressdotorg wapuu@wordpress.example --role=administrator --porcelain > /dev/null 2>&1 && echo "  Created user: wordpressdotorg" || true
fi

# Create directory categories. The theme-directory plugin blocks term creation
# for anyone who is not a super admin (see wporg_themes_pre_insert_term), so run
# these as the wordpressdotorg administrator created above.
echo "Creating categories..."
$WP wp term create category 'Featured' --slug=featured --description='Featured "curated" themes.' --user=wordpressdotorg > /dev/null 2>&1 && echo "  Created category: featured" || true
$WP wp term create category 'Special Case Theme' --slug=special-case-theme --description='Special Case Themes are allowed to bypass theme-check.' --user=wordpressdotorg > /dev/null 2>&1 && echo "  Created category: special-case-theme" || true

# Seed tags from the hot_tags API (a no-op locally when it returns nothing).
$WP wp eval 'foreach ( (array) themes_api( "hot_tags" ) as $t ) { $t = (array) $t; if ( ! empty( $t["slug"] ) && ! term_exists( $t["slug"], "post_tag" ) ) { wp_insert_term( $t["name"], "post_tag", array( "slug" => $t["slug"] ) ); } }' --user=wordpressdotorg > /dev/null 2>&1 || true

# Create example commercial theme shops (only when none exist yet).
SHOP_COUNT=$($WP wp post list --post_type=theme_shop --post_status=any --format=count 2>/dev/null | tr -d '[:space:]')
if [ "${SHOP_COUNT:-0}" -eq 0 ]; then
	echo "Creating theme shops..."
	for SHOP in example.com example.net example.org; do
		SHOP_ID=$($WP wp post create --post_type=theme_shop --post_status=publish --post_title="$SHOP" --post_content="$SHOP example theme shop." --porcelain 2>/dev/null) && \
			$WP wp post meta set "$SHOP_ID" url "https://$SHOP/" > /dev/null 2>&1 && \
			echo "  Created theme shop: $SHOP" || true
	done
fi

# Import themes from wordpress.org. Runs as the wordpressdotorg administrator so
# the importer can create tags and assign the featured category (term creation
# is restricted to super admins by the theme-directory plugin).
$WP wp eval-file wp-content/env-bin/import-themes.php --user=wordpressdotorg
