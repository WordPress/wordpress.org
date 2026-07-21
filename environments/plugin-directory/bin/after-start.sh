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
echo "Installing CLI tools..."
$WPENV wordpress sudo bash -c \
	'command -v svn > /dev/null || (apt-get -qy update && apt-get -qy install subversion unzip zip) > /dev/null 2>&1'

# cli container (Alpine).
$WPENV cli sudo sh -c \
	'command -v svn > /dev/null || apk add --no-cache -q subversion unzip zip coreutils > /dev/null 2>&1'

# Set up permalinks.
$WP wp rewrite structure '/%postname%/' --hard

# Create pages that exist on wordpress.org/plugins.
#
# Idempotent: match by slug and update, otherwise create. after-start.sh runs on
# every `wp-env start`, and a plain `wp post create` would append -2, -3, … to the
# slug each time, leaving the canonical slug pointing at a stale, empty page.
echo "Creating pages..."

# ensure_page <title> <slug> <content> [parent_id]; prints the page ID.
ensure_page() {
	local title="$1" slug="$2" content="$3" parent="$4" id
	local args=( --post_type=page --post_status=publish --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain )
	[ -n "$parent" ] && args+=( --post_parent="$parent" )
	id=$($WP wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null | tr -d '\r\n ')
	if [ -n "$id" ]; then
		$WP wp post update "$id" --post_content="$content" > /dev/null 2>&1
	else
		id=$($WP wp post create "${args[@]}" 2>/dev/null | tr -d '\r\n ')
	fi
	printf '%s' "$id"
}

# Parent: /developers/
DEVELOPERS_ID=$(ensure_page 'Developer Information' 'developers' '' '')

if [ -n "$DEVELOPERS_ID" ]; then
	# Children of /developers/
	ensure_page 'Add your Plugin' 'add' '' "$DEVELOPERS_ID" > /dev/null
	ensure_page 'Readme Validator' 'readme-validator' '[readme-validator]' "$DEVELOPERS_ID" > /dev/null
	ensure_page 'Block Plugin Checker' 'block-plugin-validator' '[block-validator]' "$DEVELOPERS_ID" > /dev/null
	ensure_page 'Release Management' 'releases' '[release-confirmation]' "$DEVELOPERS_ID" > /dev/null
	echo "  Pages ready under /developers/"
fi

# Create stub database tables that exist outside WordPress on production.
$WP wp db import wp-content/env-bin/database-tables.sql

# Create browse section terms with proper display names.
echo "Creating browse sections..."
declare -A SECTIONS=(
	[featured]="Featured"
	[popular]="Popular"
	[beta]="Beta"
	[blocks]="Block-Enabled"
	[new]="New"
	[updated]="Recently Updated"
	[favorites]="Favorites"
	[dashboard-widgets]="Dashboard Widgets"
)
for SLUG in "${!SECTIONS[@]}"; do
	NAME="${SECTIONS[$SLUG]}"
	$WP wp term create plugin_section "$NAME" --slug="$SLUG" > /dev/null 2>&1 && echo "  Created section: $NAME ($SLUG)" || true
done

# Import plugins from wordpress.org.
$WP wp eval-file wp-content/env-bin/import-plugins.php
