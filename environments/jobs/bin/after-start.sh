#!/bin/bash
#
# Runs after wp-env start. Sets up permalinks, creates pages, job categories, and sample jobs.
#

CONFIG="--config jobs/.wp-env.json"
WP="npx wp-env $CONFIG run cli --"

# Set site title and description to match the public site.
$WP wp option update blogname 'WordPress Jobs'
$WP wp option update blogdescription 'WordPress related Job Postings'

# Set up permalinks.
$WP wp rewrite structure '/%postname%/' --hard

# Activate the jobswp theme.
$WP wp theme activate jobswp

# Create pages that exist on jobs.wordpress.net (if they don't already exist).
echo "Creating pages..."
$WP wp post create --post_type=page --post_status=publish --post_title='Post a Job' --post_name='post-a-job' --porcelain > /dev/null 2>&1 && echo "  Created page: /post-a-job/" || true
$WP wp post create --post_type=page --post_status=publish --post_title='Remove a Job' --post_name='remove-a-job' --porcelain > /dev/null 2>&1 && echo "  Created page: /remove-a-job/" || true

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
		'--meta_input={"jobtype":"ft","location":"Remote","company":"Starter Corp"}' \
		--porcelain 2>/dev/null) && echo "  Created job: Senior WordPress Developer" || true
	[ -n "$JOB1" ] && $WP wp post term set "$JOB1" job_category development > /dev/null 2>&1

	JOB2=$($WP wp post create --post_type=job --post_status=publish \
		--post_title='WordPress Theme Designer' \
		--post_content='Seeking a creative designer with WordPress theme development experience. Must have strong CSS and design skills.' \
		'--meta_input={"jobtype":"ppt","location":"New York, NY","company":"Design Studio"}' \
		--porcelain 2>/dev/null) && echo "  Created job: WordPress Theme Designer" || true
	[ -n "$JOB2" ] && $WP wp post term set "$JOB2" job_category design > /dev/null 2>&1

	JOB3=$($WP wp post create --post_type=job --post_status=publish \
		--post_title='Plugin Support Specialist' \
		--post_content='Help our users get the most out of our WordPress plugins. Provide technical support and write documentation.' \
		'--meta_input={"jobtype":"pt","location":"Remote","company":"Plugin Inc"}' \
		--porcelain 2>/dev/null) && echo "  Created job: Plugin Support Specialist" || true
	[ -n "$JOB3" ] && $WP wp post term set "$JOB3" job_category support > /dev/null 2>&1
fi

echo "Jobs environment ready!"
