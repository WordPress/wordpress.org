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
# every `wp-env start`; a plain `wp post create` created a duplicate page with a
# -2, -3, … slug each time, while the canonical page kept its original content.
echo "Creating pages..."

# ensure_page <title> <slug> <content> [parent_id]; prints the page ID.
#
# Reconciles title, content and parent so a page left over from an earlier run is
# corrected rather than just topped up. Returns non-zero if wp-cli fails, so the
# caller can stop instead of seeding a half-built site.
ensure_page() {
	local title="$1" slug="$2" content="$3" parent="$4" id
	local args=( --post_type=page --post_status=publish --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain )
	[ -n "$parent" ] && args+=( --post_parent="$parent" )

	# Assign first, strip second. Piping straight into `tr` would report the
	# pipeline's status — always tr's success — and hide a wp-cli failure.
	# --posts_per_page=1 so a slug collision can't concatenate two IDs into one.
	if ! id=$( $WP wp post list --post_type=page --post_status=any --name="$slug" \
		--posts_per_page=1 --field=ID ); then
		echo "  ERROR: could not query page '$slug' — is the database up?" >&2
		return 1
	fi
	id=$( printf '%s' "$id" | tr -d '\r\n ' )

	if [ -n "$id" ]; then
		if ! $WP wp post update "$id" --post_title="$title" --post_content="$content" \
			--post_parent="${parent:-0}" > /dev/null; then
			echo "  ERROR: failed to update page '$slug' (ID $id)" >&2
			return 1
		fi
		# Progress goes to stderr; stdout carries the page ID back to the caller.
		echo "  Updated page: $slug" >&2
	else
		if ! id=$( $WP wp post create "${args[@]}" ); then
			echo "  ERROR: failed to create page '$slug'" >&2
			return 1
		fi
		id=$( printf '%s' "$id" | tr -d '\r\n ' )
		echo "  Created page: $slug" >&2
	fi

	printf '%s' "$id"
}

# Parent: /developers/
DEVELOPERS_ID=$(ensure_page 'Developer Information' 'developers' '' '') || exit 1

# Children of /developers/
ensure_page 'Add your Plugin' 'add' '' "$DEVELOPERS_ID" > /dev/null || exit 1
ensure_page 'Readme Validator' 'readme-validator' '[readme-validator]' "$DEVELOPERS_ID" > /dev/null || exit 1
ensure_page 'Block Plugin Checker' 'block-plugin-validator' '[block-validator]' "$DEVELOPERS_ID" > /dev/null || exit 1
ensure_page 'Release Management' 'releases' '[release-confirmation]' "$DEVELOPERS_ID" > /dev/null || exit 1

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
