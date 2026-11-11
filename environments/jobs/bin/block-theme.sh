#!/bin/bash
#
# Switches the running jobs site to the block theme: activates wporg-jobs-blocks and wporg-jobs-2026, and fills the pages the theme expects from jobs/pages/.
#

CONFIG="--config jobs/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# jobs/pages/, as the cli container sees it.
PAGES="wp-content/env-pages"

$WP wp plugin activate wporg-jobs-blocks
$WP wp theme activate wporg-jobs-2026

# Creates the page if it is missing, then sets its content and template.
set_page() {
	local slug="$1"
	local title="$2"
	local template="$3"
	local file="$4"

	PAGE_ID=$($WP wp post list --post_type=page --name="$slug" --field=ID 2>/dev/null)
	if [ -z "$PAGE_ID" ]; then
		PAGE_ID=$($WP wp post create --post_type=page --post_status=publish --post_author=1 --post_title="$title" --post_name="$slug" --porcelain 2>/dev/null)
	fi
	if [ -z "$PAGE_ID" ]; then
		echo "  Warning: could not set up /$slug/."
		return 1
	fi

	if [ -n "$file" ]; then
		$WP wp post update "$PAGE_ID" "$PAGES/$file" > /dev/null
	fi
	if [ -n "$template" ]; then
		$WP wp post meta update "$PAGE_ID" _wp_page_template "$template" > /dev/null
	else
		$WP wp post meta delete "$PAGE_ID" _wp_page_template > /dev/null 2>&1
	fi
	echo "  Set up page: /$slug/"
}

echo "Setting up pages..."
set_page 'faq' 'FAQ' '' 'faq.html'
set_page 'feedback' 'Feedback' 'page-wide' 'feedback.html'
set_page 'post-a-job' 'Post a Job' 'page-wide' ''
set_page 'remove-a-job' 'Remove a Job' 'page-wide' 'remove-a-job.html'

if set_page 'home' 'Home' 'page-landing' 'home.html'; then
	$WP wp option update show_on_front page > /dev/null
	$WP wp option update page_on_front "$PAGE_ID" > /dev/null
fi

echo "Block theme ready. Starting the environment again switches back to the classic theme."
