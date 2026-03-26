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

# Remove default widgets to match production (sidebar only has the hardcoded Position Types list).
echo "Clearing default sidebar widgets..."
$WP wp widget reset sidebar-1 > /dev/null 2>&1 || true

# Create pages that exist on jobs.wordpress.net (if they don't already exist).
echo "Creating pages..."
create_page_if_missing() {
	local slug="$1"
	local title="$2"
	local content="${3:-}"
	EXISTING=$($WP wp post list --post_type=page --name="$slug" --format=count 2>/dev/null)
	if [ "$EXISTING" -gt 0 ] 2>/dev/null; then
		echo "  Page /$slug/ already exists, skipping..."
	else
		if [ -n "$content" ]; then
			$WP wp post create --post_type=page --post_status=publish --post_author=1 --post_title="$title" --post_name="$slug" --post_content="$content" --porcelain > /dev/null 2>&1 && echo "  Created page: /$slug/" || true
		else
			$WP wp post create --post_type=page --post_status=publish --post_author=1 --post_title="$title" --post_name="$slug" --porcelain > /dev/null 2>&1 && echo "  Created page: /$slug/" || true
		fi
	fi
}
create_page_if_missing 'post-a-job' 'Post a Job'
create_page_if_missing 'remove-a-job' 'Remove a Job'
create_page_if_missing 'feedback' 'Feedback' '<p>We are sorry, but we do not have any additional information about job postings other than what was posted to the site.</p><p>If you are writing about your already-submitted job posting, please be sure to provide the email address you specified when you filled out the job form.</p><form method="post"><p><label for="feedback-name">Name *</label><input type="text" id="feedback-name" name="name" required /></p><p><label for="feedback-email">Email *</label><input type="email" id="feedback-email" name="email" required /></p><p><label for="feedback-subject">Subject *</label><input type="text" id="feedback-subject" name="subject" required /></p><p><label for="feedback-message">Message *</label><textarea id="feedback-message" name="message" rows="8" required></textarea></p><p><input type="submit" value="Send Feedback" /></p></form>'
create_page_if_missing 'faq' 'FAQ' '<h2>General</h2><dl><dt>What is this site?</dt><dd>This is a job board for WordPress-related jobs. Anyone can post a job opening or browse available positions.</dd><dt>How long do job postings stay up?</dt><dd>Job postings remain active for 21 days from the date of approval, after which they are automatically removed.</dd><dt>How much does it cost to post a job?</dt><dd>Posting a job is completely free.</dd></dl><h2>For Employers</h2><dl><dt>What kinds of jobs can I post?</dt><dd>Any job that is directly related to WordPress. This includes development, design, support, writing, translation, and more.</dd><dt>What is NOT acceptable for a job posting?</dt><dd>Jobs that are not related to WordPress, jobs that require payment from applicants, and jobs offering illegally low compensation are not acceptable.</dd><dt>How do I remove my job posting?</dt><dd>When you submit a job, you receive a job token. Use that token on the Remove a Job page to remove your listing at any time.</dd></dl>'

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
