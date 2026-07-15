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
FAQ_CONTENT='<p>Frequently asked questions for <a href="#job-posters">job posters</a> and <a href="#job-seekers">job seekers</a>.</p>

<h2 id="job-posters">Posting a job?</h2>

<dl>
<dt>What types of jobs can be posted on this site?</dt>
<dd>Only WordPress-related jobs will be published, provided they meet the criteria below.</dd>

<dt>How much does it cost to post a job listing?</dt>
<dd>There is no fee for posting a job listing.</dd>

<dt>How long will it take for my job posting to appear on the site?</dt>
<dd>All job postings are moderated prior to appearing on the site. Moderation is performed by a team of volunteers. As such, it may take as long as 24-36 hours before a job posting is approved depending on various factors.</dd>

<dt>How long will my job posting be displayed?</dt>
<dd>Your job posting will be displayed for a period of 21 days, unless you <a href="/remove-a-job/">remove it yourself</a> using the job token you are provided or you <a href="/feedback/">contact us</a> to have it removed earlier than that.</dd>

<dt>Who can I contact?</dt>
<dd>Contact us via our <a href="/feedback/">feedback form</a>.</dd>

<dt>Can I post my advertisement, site, or cool new product?</dt>
<dd>No, this site is only for posting available jobs.</dd>

<dt>How do I remove a job posting?</dt>
<dd>Your job will automatically be removed from the site after 21 days. Upon successful submission of a job to the site, a unique job token is provided to the job poster. We emphatically implore job posters (multiple times) to make note of the job token. The token can be used on our <a href="/remove-a-job/">Remove Job</a> page to immediately close the job. If you do not have the job token, you can <a href="/feedback/">contact us</a> to request closure of the job. You must submit your request from an email address you provided via the job submission form. It would also be helpful if you reference the job by link and/or title, especially if you have more than one job in the system. <em>Note: Requests made via the contact page could take as much as 72 hours or longer to fulfill.</em></dd>

<dt>Why is X feature not available?</dt>
<dd>It could be. Just leave us <a href="/feedback/">feedback</a>.</dd>

<dt>Why was my job posting not approved?</dt>
<dd>We moderate job posts on a few criteria, including:
<ul>
<li>At this time, all job postings must be in English.</li>
<li>Only actual jobs can be posted to the job board.</li>
<li>The job board is for WordPress jobs only.</li>
<li>Jobs must offer monetary compensation in exchange for work. Non-monetary compensation (exposure, trade, charity, etc) are not acceptable except in the case of verifiable non-profit organizations who <strong>clearly</strong> state their non-profit status and the fact that their job or project is not offering compensation.</li>
<li>Your job posting must have adequate information about the listing, including contact information. One or two sentences are not sufficient to explain the job or project.
<ul>
<li>For full and part time jobs, please include as many details about the position, including what exactly the applicant will be doing, what salary/hourly range you are expecting to pay, and what skills you require from applicants.</li>
<li>For projects, please be as specific as possible about the nature of your project as well as your budget. Pro bono projects for non-profits are allowed, but be up front about this in your job posting.</li>
</ul>
</li>
<li>Products and businesses listed on this job board must not infringe on the WordPress trademark. (See the <a href="https://WordPressfoundation.org/trademark-policy/">WordPress trademark policy</a>.)</li>
<li>Job posting will not be published for sites which:
<ul>
<li>Promote discrimination of any kind or hate speech</li>
<li>Promote illegal activity as defined by the laws of the United States</li>
<li>Primarily host adult content or merchandise (e.g. pornography)</li>
</ul>
</li>
<li>If your job posting involves a project that will be released publicly, it must embrace <a href="https://WordPress.org/about/license/">the same license as WordPress</a>. If distributing WordPress-derivative works (themes, plugins, WP distros), any person or business should give their users the same freedoms that WordPress itself provides. Projects must be 100% GPL or compatibly licensed.</li>
</ul>
</dd>
</dl>

<h2 id="job-seekers">Seeking a job?</h2>

<dl>
<dt>Can I post my resume?</dt>
<dd>Not at this time.</dd>

<dt>How do I contact an employer?</dt>
<dd>Employers are expected to leave their contact information on each post.</dd>

<dt>Why does an employer not respond?</dt>
<dd>It is completely up to the employer to choose to respond or ignore a request for communication.</dd>
</dl>'
create_page_if_missing 'faq' 'FAQ' "$FAQ_CONTENT"

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
