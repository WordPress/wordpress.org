#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, creates the jobposter user, pages, job categories, and sample jobs.
#

CONFIG="--config jobs/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Set site title and description to match the public site.
$WP wp option update blogname 'WordPress Jobs'
$WP wp option update blogdescription 'WordPress related Job Postings'

# Set up permalinks.
$WP wp rewrite structure '/%postname%/' --hard

# Clear the classic sidebar's default widgets to match production; the reset only works while it is registered.
$WP wp theme activate jobswp > /dev/null
$WP wp widget reset sidebar-1 > /dev/null 2>&1 || true

# Activate the block theme.
$WP wp theme activate wporg-jobs-2026

# The plugin files every submitted job under this account; the form errors without it.
if $WP wp user get jobposter --field=ID > /dev/null 2>&1; then
	echo "User jobposter already exists, skipping..."
else
	if $WP wp user create jobposter jobposter@example.org --role=subscriber --porcelain > /dev/null; then
		echo "Created user: jobposter"
	else
		echo "Warning: could not create the jobposter user; the Post a Job form will not work without it."
	fi
fi

# Create the pages the theme expects from jobs/pages/ (if they don't already exist).
echo "Creating pages..."
PAGES="wp-content/env-pages"
create_page_if_missing() {
	local slug="$1"
	local title="$2"
	local template="${3:-}"
	local file="${4:-}"
	local args=( --post_type=page --post_status=publish --post_author=1 --post_title="$title" --post_name="$slug" --porcelain )

	EXISTING=$($WP wp post list --post_type=page --name="$slug" --format=count 2>/dev/null)
	if [ "$EXISTING" -gt 0 ] 2>/dev/null; then
		echo "  Page /$slug/ already exists, skipping..."
		return
	fi

	if [ -n "$file" ]; then
		args=( "$PAGES/$file" "${args[@]}" )
	fi
	PAGE_ID=$($WP wp post create "${args[@]}" 2>/dev/null) || return
	if [ -n "$template" ]; then
		$WP wp post meta update "$PAGE_ID" _wp_page_template "$template" > /dev/null 2>&1
	fi
	echo "  Created page: /$slug/"
}
create_page_if_missing 'post-a-job' 'Post a Job' 'page-wide'
create_page_if_missing 'remove-a-job' 'Remove a Job' 'page-wide' 'remove-a-job.html'
create_page_if_missing 'feedback' 'Feedback' 'page-wide' 'feedback.html'
create_page_if_missing 'faq' 'FAQ' '' 'faq.html'
create_page_if_missing 'home' 'Home' 'page-landing' 'home.html'

# Show the Home page on the front page.
HOME_ID=$($WP wp post list --post_type=page --name=home --field=ID 2>/dev/null)
if [ -n "$HOME_ID" ]; then
	$WP wp option update show_on_front page > /dev/null
	$WP wp option update page_on_front "$HOME_ID" > /dev/null
fi

# Create job categories.
echo "Creating job categories..."
declare -A CATEGORIES=(
	[contributor]="Contributor"
	[design]="Design"
	[development]="Development"
	[general]="General"
	[migration]="Migration"
	[performance]="Performance"
	[plugin-development]="Plugin Development"
	[support]="Support"
	[theme-customization]="Theme Customization"
	[translation]="Translation"
	[writing]="Writing"
)
for SLUG in "${!CATEGORIES[@]}"; do
	NAME="${CATEGORIES[$SLUG]}"
	$WP wp term create job_category "$NAME" --slug="$SLUG" > /dev/null 2>&1 && echo "  Created category: $NAME ($SLUG)" || true
done

# Create sample job posts so the homepage is not empty (skip if already seeded).
EXISTING=$($WP wp post list --post_type=job --posts_per_page=1 --format=count 2>/dev/null)
if [ "$EXISTING" -gt 0 ] 2>/dev/null; then
	echo "Sample jobs already exist, skipping..."
else
	echo "Creating sample jobs..."

	# Note: --tax_input for wp post create is supported in entity-command v2.8.6+,
	# but the wp-env bundled WP-CLI may not include it yet. Terms are assigned
	# separately via wp post term set for compatibility.
	JOB1=$($WP wp post create --post_type=job --post_status=publish \
		--post_title='Senior WordPress Developer' \
		--post_content='We are looking for an experienced WordPress developer to join our team. You will be responsible for building custom plugins and themes.' \
		'--meta_input={"jobtype":"ft","location":"Remote","company":"Starter Corp","howtoapply":"https://example.com/apply","howtoapply_method":"web"}' \
		--porcelain 2>/dev/null) && echo "  Created job: Senior WordPress Developer" || true
	[ -n "$JOB1" ] && $WP wp post term set "$JOB1" job_category development > /dev/null 2>&1

	JOB2=$($WP wp post create --post_type=job --post_status=publish \
		--post_title='WordPress Theme Designer' \
		--post_content='Seeking a creative designer with WordPress theme development experience. Must have strong CSS and design skills.' \
		'--meta_input={"jobtype":"ppt","location":"New York, NY","company":"Design Studio","howtoapply":"jobs@designstudio.example.com","howtoapply_method":"email"}' \
		--porcelain 2>/dev/null) && echo "  Created job: WordPress Theme Designer" || true
	[ -n "$JOB2" ] && $WP wp post term set "$JOB2" job_category design > /dev/null 2>&1

	JOB3=$($WP wp post create --post_type=job --post_status=publish \
		--post_title='Plugin Support Specialist' \
		--post_content='Help our users get the most out of our WordPress plugins. Provide technical support and write documentation.' \
		'--meta_input={"jobtype":"pt","location":"Remote","company":"Plugin Inc","howtoapply":"https://plugininc.example.com/careers","howtoapply_method":"web"}' \
		--porcelain 2>/dev/null) && echo "  Created job: Plugin Support Specialist" || true
	[ -n "$JOB3" ] && $WP wp post term set "$JOB3" job_category support > /dev/null 2>&1
fi

echo "Jobs environment ready!"
