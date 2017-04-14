<?php
/**
 * Data for the Planning Checklist.
 *
 * @package WordCamp\Mentors
 */

namespace WordCamp\Mentors\Tasks;
defined( 'WPINC' ) || die();

use WordCamp\Mentors;

/**
 * Define the task categories for the Planning Checklist.
 *
 * @since 1.0.0
 *
 * @return array
 */
function get_task_category_data() {
	return array(
		'after-party'     => esc_html__( 'After Party', 'wordcamporg' ),
		'audio-video'     => esc_html__( 'Audio/Video', 'wordcamporg' ),
		'budget'          => esc_html__( 'Budget', 'wordcamporg' ),
		'committee'       => esc_html__( 'Committee', 'wordcamporg' ),
		'contributor-day' => esc_html__( 'Contributor Day', 'wordcamporg' ),
		'design'          => esc_html__( 'Design', 'wordcamporg' ),
		'food'            => esc_html__( 'Food', 'wordcamporg' ),
		'lead'            => esc_html__( 'Lead', 'wordcamporg' ),
		'registration'    => esc_html__( 'Registration', 'wordcamporg' ),
		'speaker'         => esc_html__( 'Speaker', 'wordcamporg' ),
		'sponsor'         => esc_html__( 'Sponsor', 'wordcamporg' ),
		'swag'            => esc_html__( 'Swag', 'wordcamporg' ),
		'volunteer'       => esc_html__( 'Volunteer', 'wordcamporg' ),
		'web'             => esc_html__( 'Web', 'wordcamporg' ),
	);
}

/**
 * Define the tasks for the Planning Checklist.
 *
 * @since 1.0.0
 *
 * @return array
 */
function get_task_data() {
	/**
	 * When adding or editing items, be sure to update the value of the DATA_VERSION constant in
	 * the wordcamp-mentors.php file with the current YYYYMMDD timestamp (include hours and
	 * minutes if necessary).
	 *
	 * The task data keys are randomized strings instead of sequential and/or contextual because:
	 * - The order of the tasks could change, in which case having out-of-order sequential numbers
	 *   could be confusing.
	 * - The wording, category, or other properties of a task could change, in which case a key
	 *   string based on these properties could be confusing.
	 *
	 * When adding new task items, randomized key strings can be created here:
	 * http://textmechanic.com/text-tools/randomization-tools/random-string-generator/
	 */
	return array(
		't5o8' => array(
			'title'   => __( 'Apply to organize your local WordCamp', 'wordcamporg' ),
			'excerpt' => __( 'You must fill out a new application each year.', 'wordcamporg' ),
			'cat'     => array( 'lead' ),
			'link'    => array(
				'text' => __( 'Apply', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/become-an-organizer/',
			),
		),
		'22ix' => array(
			'title'   => __( 'Recruit your organizing team', 'wordcamporg' ),
			'excerpt' => __( 'Recruit a full organizing team from within your community.', 'wordcamporg' ),
			'cat'     => array( 'lead' ),
			'link'    => array(
				'text' => __( 'Build your team', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/the-organizing-team/',
			),
		),
		'v2cu' => array(
			'title'   => __( 'Explore venue options', 'wordcamporg' ),
			'excerpt' => __( 'Look into venues that may work for your event. Ask for suggestions from your team and meetup.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
			'link'    => array(
				'text' => __( 'Venue Information', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/venue-and-date/',
			),
		),
		'8pb0' => array(
			'title'   => __( 'Update budget page', 'wordcamporg' ),
			'excerpt' => __( 'Add your camp\'s budget information to the budget tool located in your dashboard.', 'wordcamporg' ),
			'cat'     => array( 'budget' ),
			'link'    => array(
				'text' => __( 'Budget and Finances', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/budget-and-finances/',
			),
		),
		'jv29' => array(
			'title'   => __( 'Start design and branding', 'wordcamporg' ),
			'excerpt' => __( 'Start thinking about and implementing Design process/branding.', 'wordcamporg' ),
			'cat'     => array( 'design' ),
		),
		'o1rt' => array(
			'title'   => __( 'Brainstorming speaker topics and ideas', 'wordcamporg' ),
			'excerpt' => __( 'Brainstorm and discuss with your team topics/requested speaker topics.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
			'link'    => array(
				'text' => __( 'Speakers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/speakers/',
			),
		),
		'7j6f' => array(
			'title'   => __( 'Venue walk-through', 'wordcamporg' ),
			'excerpt' => __( 'Before you lock-in your venue have a walk through with your leads and wranglers to ensure this venue will be accessible and functional for all areas of the event.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
			'link'    => array(
				'text' => __( 'Venue Information', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/venue-and-date/',
			),
		),
		'siq8' => array(
			'title'   => __( 'Choose a Date', 'wordcamporg' ),
			'excerpt' => __( 'Consider holidays, other events in your city, and other WordCamps in your region. Be sure to have your budget approved and have a contract or agreement for the venue signed by WordCamp Central before you announce your dates.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
			'link'    => array(
				'text' => __( 'Pick a date', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/venue-and-date/',
			),
		),
		'lkcs' => array(
			'title'   => __( 'Get quotes from vendors', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'swag' ),
		),
		'5j0m' => array(
			'title'   => __( 'Select caterer and menu for event', 'wordcamporg' ),
			'excerpt' => __( 'Ensure that you accomodate all dietary requirements.', 'wordcamporg' ),
			'cat'     => array( 'food' ),
			'link'    => array(
				'text' => __( 'Food and Beverage', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/food-and-beverage/',
			),
		),
		'n7rk' => array(
			'title'   => __( 'Budget approval', 'wordcamporg' ),
			'excerpt' => __( 'Build your budget and submit it for review and approval.', 'wordcamporg' ),
			'cat'     => array( 'budget' ),
			'link'    => array(
				'text' => __( 'Budget review and approval', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/budget-and-finances/',
			),
		),
		'f087' => array(
			'title'   => __( 'Confirm your venue', 'wordcamporg' ),
			'excerpt' => __( 'Make sure you get a contract, agreement or confirmation email. In order to move your WordCamp to scheduled you need some sort of written confirmation that the venue is reserved for your dates. ', 'wordcamporg' ),
			'cat'     => array( 'lead' ),
			'link'    => array(
				'text' => __( 'Lock in your venue', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/venue-and-date/',
			),
		),
		'asdf' => array(
			'title'   => __( 'Contributor day venue', 'wordcamporg' ),
			'excerpt' => __( 'If you\'re hosting a contributor day and it will not be held at your primary venue, find a location now.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
		),
		'75sp' => array(
			'title'   => __( 'Find after party and speaker event venues', 'wordcamporg' ),
			'excerpt' => __( 'Please keep in mind that all venues should be open and welcoming to everyone. No age restricted venues. Keep in mind ease of access from your event venue.', 'wordcamporg' ),
			'cat'     => array( 'after-party' ),
			'link'    => array(
				'text' => __( 'Parties', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/parties/',
			),
		),
		'vbbj' => array(
			'title'   => __( 'Setup and design your site', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'web' ),
			'link'    => array(
				'text' => __( 'Site setup and design', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/web-presence/#website',
			),
		),
		'krv3' => array(
			'title'   => __( 'Launch website design', 'wordcamporg' ),
			'excerpt' => __( 'Launch site design and take site out of "Coming Soon" mode.', 'wordcamporg' ),
			'cat'     => array( 'web' ),
		),
		'inv6' => array(
			'title'   => __( 'Announce your event', 'wordcamporg' ),
			'excerpt' => __( 'Get the word out to the community!', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
			'link'    => array(
				'text' => __( 'Publicity', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/publicity/',
			),
		),
		'3vf4' => array(
			'title'   => __( 'Set sponsorship levels', 'wordcamporg' ),
			'excerpt' => __( 'Identify internal sponsorship levels and what each level includes.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
			'link'    => array(
				'text' => __( 'Local sponsorshps', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/fundraising/local-wordcamp-sponsorship/',
			),
		),
		'erjt' => array(
			'title'   => __( 'Create documents and templates', 'wordcamporg' ),
			'excerpt' => __( 'Create/use email templates for sponsorship, volunteers, speakers, etc.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
			'link'    => array(
				'text' => __( 'Helpful documents and templates', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/helpful-documents-and-templates/',
			),
		),
		'5uqp' => array(
			'title'   => __( 'Reach out to prior sponsors', 'wordcamporg' ),
			'excerpt' => __( 'Reach out to sponsors from previous years. If this is your event\'s first year reach out to sponsors from past WordCamps in your region.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
			'link'    => array(
				'text' => __( 'Local Sponsors', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/fundraising/local-wordcamp-sponsorship/',
			),
		),
		'9tsp' => array(
			'title'   => __( 'Open call for sponsors', 'wordcamporg' ),
			'excerpt' => __( 'Having already reached out to prior sponsors with your completed sponsor packet, it\'s now time to publically open your call for sponsors on your site. At this time you can also reach out to sponsors who have supported other WordCamps in your area.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
			'link'    => array(
				'text' => __( 'Fundraising', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/fundraising/',
			),
		),
		'u1rl' => array(
			'title'   => __( 'Vet sponsor applicants', 'wordcamporg' ),
			'excerpt' => __( 'Vet all sponsor applicants to ensure they meet expectations of the WordPress Open Source Project with GPL and Trademark.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
			'link'    => array(
				'text' => __( 'GPL primer', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/gpl-primer/',
			),
		),
		'ibzq' => array(
			'title'   => __( 'Open call for speakers', 'wordcamporg' ),
			'excerpt' => __( 'Have web wrangler add call for speakers on site.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
			'link'    => array(
				'text' => __( 'Speakers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/speakers/',
			),
		),
		'wccy' => array(
			'title'   => __( 'Speaker recruitment and Speaker Applicant outreach', 'wordcamporg' ),
			'excerpt' => __( 'Reach out to known/wanted community speakers and community groups. Encourage those from commonly-under represented groups to apply. Invite a keynote or featured speaker if applicable. ', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
			'link'    => array(
				'text' => __( 'Speakers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/speakers/',
			),
		),
		'3b1k' => array(
			'title'   => __( 'Determine what volunteers roles are needed', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'volunteer' ),
			'link'    => array(
				'text' => __( 'Volunteers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/volunteers/',
			),
		),
		'rko5' => array(
			'title'   => __( 'Open call for volunteers', 'wordcamporg' ),
			'excerpt' => __( 'Have web wrangler add call for volunteers on website including descriptions.', 'wordcamporg' ),
			'cat'     => array( 'volunteer' ),
			'link'    => array(
				'text' => __( 'Volunteers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/volunteers/',
			),
		),
		'uw8g' => array(
			'title'   => __( 'Open ticket sales', 'wordcamporg' ),
			'excerpt' => __( 'Set up your tickets -- make sure you ask for dietary preferences and t-shirt sizes (if providing t-shirts). Also ask in registration if attendees require any special acoomodation to attend.', 'wordcamporg' ),
			'cat'     => array( 'registration' ),
			'link'    => array(
				'text' => __( 'Selling Tickets', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/selling-tickets/',
			),
		),
		'qi9n' => array(
			'title'   => __( 'Anonymize speaker submissions (remove gender specific information, names)', 'wordcamporg' ),
			'excerpt' => __( 'Prepare the speaker submissions and prepare the speaker selection team.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'co30' => array(
			'title'   => __( 'Review anonymized speaker submissions with your speaker panel', 'wordcamporg' ),
			'excerpt' => __( 'After the team reviews all submissions, speaker lead should review rejected talks to ensure you\'re not discarding a spectacular speaker who didn\'t present well in application.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
			'link'    => array(
				'text' => __( 'Speakers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/speakers/',
			),
		),
		'eu82' => array(
			'title'   => __( 'Send out update email to speaker applicants', 'wordcamporg' ),
			'excerpt' => __( 'Let speaker applicants know their submissions were received and give them an idea of when they will hear back from the team.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'm2sc' => array(
			'title'   => __( 'Contact sponsors for details', 'wordcamporg' ),
			'excerpt' => __( 'Contact sponsors to gather information needed for site and signage. If they qualify for a table or to hand out swag, let them know where to ship merchandise and what their space will be like. Provide them with coupon codes and remind them how many free tickets they receive.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
		),
		'k9k0' => array(
			'title'   => __( 'Start fulfilling sponsor level benefits', 'wordcamporg' ),
			'excerpt' => __( 'If you promised blog posts, tweets, or signage make sure that you\'re scheduling these in compliance with the offerings per level.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
		),
		'fucx' => array(
			'title'   => __( 'Finalize swag decision and place order', 'wordcamporg' ),
			'excerpt' => __( 'By now you should have a swag vendor, place order and ensure it will ship in time for your event. Order custom stickers from StickerGiant.', 'wordcamporg' ),
			'cat'     => array( 'swag' ),
			'link'    => array(
				'text' => __( 'Custom Swag', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/swag/',
			),
		),
		'cxpj' => array(
			'title'   => __( 'Makes plans for volunteer orientation', 'wordcamporg' ),
			'excerpt' => __( 'Decide if you will do an in person, video, or text orientation for volunteers and plan accordingly.', 'wordcamporg' ),
			'cat'     => array( 'volunteer' ),
			'link'    => array(
				'text' => __( 'Volunteers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/volunteers/',
			),
		),
		'uutz' => array(
			'title'   => __( 'Request video kit from WordCamp Central', 'wordcamporg' ),
			'excerpt' => __( 'If you haven\'t hired a videographer, please let WordCamp Central know how many tracks you have and where the camera kits should be received.', 'wordcamporg' ),
			'cat'     => array( 'audio-video' ),
			'link'    => array(
				'text' => __( 'Video', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/video/',
			),
		),
		'mkj1' => array(
			'title'   => __( 'Email selected speakers', 'wordcamporg' ),
			'excerpt' => __( 'Contact selected speakers. Ask them to sign the speaker agreement and AV release. Remind them you\'ll need to review their slides 2 weeks prior to WordCamp. Give them their coupon code for a free ticket. Determine if they have any special AV requirements and relay that info to your AV person.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'c00l' => array(
			'title'   => __( 'Email remaining speaker applicants', 'wordcamporg' ),
			'excerpt' => __( 'Make sure that you notify applicants who were not selected BEFORE you announce your speakers publicly.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'84y3' => array(
			'title'   => __( 'Publish/Announce speakers', 'wordcamporg' ),
			'excerpt' => __( 'Also ask speakers to promote on their social channels.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'sfrb' => array(
			'title'   => __( 'Begin posting original content', 'wordcamporg' ),
			'excerpt' => __( 'In order to drive traffic to the site and keep your followers engaged it\'s a good idea to publish more than just announcements. Try WordCamp stories, speaker profiles, community involvement stories, etc.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
		),
		'l931' => array(
			'title'   => __( 'Publish/Announce sessions and schedule', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'speaker' ),
		),
		'p0ns' => array(
			'title'   => __( 'Order speaker gifts', 'wordcamporg' ),
			'excerpt' => __( 'Speaker gifts are not required but if it\'s in your budget and you plan to do something you should order at least 6-weeks in advance. Don\'t spend too much on this, remember we should always use funds on what will benefit attendees most.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'03kv' => array(
			'title'   => __( 'Send a pre-Camp event invite (if having)', 'wordcamporg' ),
			'excerpt' => __( 'If you\'re hosting a pre-camp event (e.g., a speaker event) make sure you invite the appropriate folks (e.g., speakers, sponsors, volunteers).', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
			'link'    => array(
				'text' => __( 'Parties', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/parties/',
			),
		),
		'srgu' => array(
			'title'   => __( 'Publish sponsor posts', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'sponsor' ),
		),
		'bc1e' => array(
			'title'   => __( 'Design name badges', 'wordcamporg' ),
			'excerpt' => __( 'Review the guidlines for creating badges before finalizing your design.', 'wordcamporg' ),
			'cat'     => array( 'design' ),
			'link'    => array(
				'text' => __( 'Create WordCamp Badges', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/wordcamp-name-badge-templates/',
			),
		),
		'e0uc' => array(
			'title'   => __( 'Design signage', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'design' ),
		),
		'ws2p' => array(
			'title'   => __( 'Confirm swag order from WordCamp Central', 'wordcamporg' ),
			'excerpt' => __( '6 weeks out - WordPress stickers, buttons, and lanyards will come from WordCamp Central. Confirm your shipping address and number of attendees when Central reaches out.', 'wordcamporg' ),
			'cat'     => array( 'swag' ),
			'link'    => array(
				'text' => __( 'Swag', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/swag/',
			),
		),
		'2u4x' => array(
			'title'   => __( 'Offer speaker mentorship', 'wordcamporg' ),
			'excerpt' => __( '6 weeks out - Especially with less experienced speakers offer them mentorship or the chance to share their talk in advance (via Google Hangouts, speaker training).', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
			'link'    => array(
				'text' => __( 'Speaking at a WordCamp', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/speakers/speaking-at-a-wordcamp/',
			),
		),
		'tpvw' => array(
			'title'   => __( 'Create backup plans including backup speaker', 'wordcamporg' ),
			'excerpt' => __( 'If a speaker gets sick, do you have someone who can fill in on the same topic? For out of town speakers, what if they miss their flights? Have one backup speaker per track. Back up speakers should not be rejected applicants.', 'wordcamporg' ),
			'cat'     => array( 'lead' ),
		),
		'r3ge' => array(
			'title'   => __( 'Remind speakers to send slides', 'wordcamporg' ),
			'excerpt' => __( 'Remind speakers that you will need their slides by two weeks before the event. If they haven\'t signed the speaker agreement and A/V release, request those.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'ckzp' => array(
			'title'   => __( 'Complete volunteer schedule and confirm volunteers.', 'wordcamporg' ),
			'excerpt' => __( '4 weeks out - Email volunteers with details of their role, provide coupon code, and ask them to sign the volunteer agreement.', 'wordcamporg' ),
			'cat'     => array( 'volunteer' ),
			'link'    => array(
				'text' => __( 'Volunteers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/volunteers/',
			),
		),
		'q97z' => array(
			'title'   => __( 'Review speaker slides', 'wordcamporg' ),
			'excerpt' => __( '2 weeks out - Collect slides from speakers and review as a team. This helps avoid any inappropriate content in the presentations (WordCamps should be family-friendly, with no swearing or discriminatory jokes/comments), and also helps you catch any misspellings, fauxgos, or other problems in the slides. Check to make sure they properly camel case WordPress and WordCamp.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
		),
		'c46e' => array(
			'title'   => __( 'Confirm catering ', 'wordcamporg' ),
			'excerpt' => __( 'Confirm catering menu for any meals or snacks you have arranged based on attendee numbers. Ensure delivery/ pickup is scheudled. Please do not run out of coffee. At this time confirm menus for any ancillary events (ie: contributor day, speaker event, after party, etc.) you may be having.', 'wordcamporg' ),
			'cat'     => array( 'food' ),
			'link'    => array(
				'text' => __( 'Food and Beverage', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/food-and-beverage/',
			),
		),
		'nhws' => array(
			'title'   => __( 'Finalize name badge order', 'wordcamporg' ),
			'excerpt' => __( 'Send your badge files off to the printer. Make sure they\'ll have them ready a day or two before your event.', 'wordcamporg' ),
			'cat'     => array( 'design' ),
		),
		'f1ln' => array(
			'title'   => __( 'Order signage', 'wordcamporg' ),
			'excerpt' => __( 'Make sure to include additional wayfinding signage and confirm with sponsor coordinator that you\'re meeting sponsor level requirements.', 'wordcamporg' ),
			'cat'     => array( 'design' ),
		),
		'h641' => array(
			'title'   => __( 'Email Speakers', 'wordcamporg' ),
			'excerpt' => __( 'Email a final confirmation to your speakers. Include the date and time of their talk, when and with whom they should check in the day they\'re speaking, what the av setup is, and any other details you feel they should know. If you\'re hosting a speaker event make sure they have that information as well.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'svit' => array(
			'title'   => __( 'Email Sponsors', 'wordcamporg' ),
			'excerpt' => __( 'Email a final confirmation to your sponsors. Include date and time of their load-in, when they can arrive day of the event, where they will setup and who their on site contact will be. If they\'re invited to your speaker event confirm those details with them as well.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
		),
		'2iiq' => array(
			'title'   => __( 'Test and prepare equipment from camera kit (if using)', 'wordcamporg' ),
			'excerpt' => __( 'Check out your kits to make sure everything is there, the batteries are charged, and you or your video coordiantor understnad how to use them.', 'wordcamporg' ),
			'cat'     => array( 'audio-video' ),
			'link'    => array(
				'text' => __( 'Video Quick Start Guide', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/video/quick-start/',
			),
		),
		'w3l6' => array(
			'title'   => __( 'Send a pre-event attendee email', 'wordcamporg' ),
			'excerpt' => __( '2-days prior - Send an email to all attendees (including speakers, sponsors, and volunteers) with important information they will need for attendance. Include information about parking, wifi, menus, and registration. If there will be any known traffic disruptions due to events or construction let them know there may be traffic delays. Welcome them to your WordCamp.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
		),
		'gmue' => array(
			'title'   => __( 'Close registration', 'wordcamporg' ),
			'excerpt' => __( '1-day prior - If you\'re not offering walk-in tickets, close registration.', 'wordcamporg' ),
			'cat'     => array( 'registration' ),
		),
		'yzu9' => array(
			'title'   => __( 'Sort swag', 'wordcamporg' ),
			'excerpt' => __( 'e.g., fold and sort t-shirts, stuff bags, generally prepare swag and badges to be distributed.', 'wordcamporg' ),
			'cat'     => array( 'swag' ),
		),
		'rhgc' => array(
			'title'   => __( 'Final venue walkthrough', 'wordcamporg' ),
			'excerpt' => __( 'Ensure you have contact info for technical and facilities. Count the signage, observe the flow, order additional signage as needed. Locate/setup volunteer stations in venue.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
		),
		'hscb' => array(
			'title'   => __( 'Volunteer Training', 'wordcamporg' ),
			'excerpt' => __( '1-day prior or early day-of - Make sure your volunteers have all the information they will need to carry out their tasks. Let them know that if someone asks a question to which they do not know the answer it\'s best to say "I don\'t know, but let\'s find out" and ask an organizer.', 'wordcamporg' ),
			'cat'     => array( 'volunteer' ),
			'link'    => array(
				'text' => __( 'Volunteers', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/planning-details/volunteers/',
			),
		),
		'20g7' => array(
			'title'   => __( 'Hold pre-camp event (e.g., speaker/sponsor dinner)', 'wordcamporg' ),
			'excerpt' => '',
			'cat'     => array( 'committee' ),
		),
		'hkv5' => array(
			'title'   => __( 'A/V setup', 'wordcamporg' ),
			'excerpt' => __( 'Whether it\'s volunteers or venue staff, make sure the A/V setup is complete prior to the start of your event day.', 'wordcamporg' ),
			'cat'     => array( 'audio-video' ),
		),
		'gyc3' => array(
			'title'   => __( 'Have a WordCamp!', 'wordcamporg' ),
			'excerpt' => __( 'Be present. Breathe. Enjoy the event!', 'wordcamporg' ),
			'cat'     => array( 'volunteer' ),
			'link'    => array(
				'text' => __( 'During WordCamp', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/during-wordcamp/',
			),
		),
		'jccc' => array(
			'title'   => __( 'Upload videos to WordPress.tv', 'wordcamporg' ),
			'excerpt' => __( 'You\'ll need to do a small amount of post-production and upload your WordCamp videos to WordPress.tv.', 'wordcamporg' ),
			'cat'     => array( 'audio-video' ),
			'link'    => array(
				'text' => __( 'Post-Production', 'wordcamporg' ),
				'url'  => 'https://make.wordpress.org/community/handbook/wordcamp-organizer/video/after-the-event-post-production/',
			),
		),
		'gnws' => array(
			'title'   => __( 'Ship camera kits (if using WordCamp Central kits)', 'wordcamporg' ),
			'excerpt' => __( 'If you used WordCamp Central\'s camera kits it\'s time to ship them on to the next event or back to their base for service. Someone for WordCamp Central will reach out to you with instructions.', 'wordcamporg' ),
			'cat'     => array( 'audio-video' ),
		),
		'f8k2' => array(
			'title'   => __( 'Close out your budget ', 'wordcamporg' ),
			'excerpt' => __( 'Make sure all payment requests are submitted and balance your budget within two weeks of your event. If you have any questions reach out to support@wordcamp.org.', 'wordcamporg' ),
			'cat'     => array( 'budget' ),
		),
		'r95r' => array(
			'title'   => __( 'Send attendee survey', 'wordcamporg' ),
			'excerpt' => __( 'Send your attendees, speakers, sponsors, and volunteers a post-event email recapping the event, sharing any information they may need, thanking them, and asking them to fill out the WordCamp attendee survey.', 'wordcamporg' ),
			'cat'     => array( 'lead' ),
			'link'    => array(
				'text' => __( 'Attendee Survey', 'wordcamporg' ),
				'url'  => 'https://central.wordcamp.org/wordcamp-attendee-survey/',
			),
		),
		'uu96' => array(
			'title'   => __( 'Thank you emails and request feedback', 'wordcamporg' ),
			'excerpt' => __( 'Thank your sponsors for their generous support and ask them if they have any feedback for future events.', 'wordcamporg' ),
			'cat'     => array( 'sponsor' ),
		),
		'uqrx' => array(
			'title'   => __( 'Thank you emails and request feedback', 'wordcamporg' ),
			'excerpt' => __( 'Thank your speakers for their participation and ask them if they have any feedback for future events.', 'wordcamporg' ),
			'cat'     => array( 'speaker' ),
		),
		'1hc1' => array(
			'title'   => __( 'Thank you emails and request feedback', 'wordcamporg' ),
			'excerpt' => __( 'Thank your volunteers for helping to make the event possible and ask them if they have any feedback for future events.', 'wordcamporg' ),
			'cat'     => array( 'volunteer' ),
		),
		'fl69' => array(
			'title'   => __( 'Fill out your WordCamp debrief', 'wordcamporg' ),
			'excerpt' => __( 'We\'d love to hear how you feel the event went - what were your proudest moments and your greatest disappointments? We\'ve created a WordCamp Debrief survey so we can get all the details of how things went with your illustrious event.', 'wordcamporg' ),
			'cat'     => array( 'committee' ),
			'link'    => array(
				'text' => __( 'Debrief', 'wordcamporg' ),
				'url'  => 'http://wordcampcentral.polldaddy.com/s/wordcamp-debrief',
			),
		),
	);
}

/**
 * Handle a POST request to reset the task data.
 *
 * @since 1.0.0
 *
 * @return void
 */
function handle_tasks_reset() {
	// The base redirect URL.
	$redirect_url = add_query_arg( array(
		'page' => Mentors\PREFIX . '-planning-checklist',
	), admin_url( 'index.php' ) );

	if ( ! isset( $_POST[ Mentors\PREFIX . '-tasks-reset-nonce' ] ) ||
	     ! wp_verify_nonce( $_POST[ Mentors\PREFIX . '-tasks-reset-nonce' ], Mentors\PREFIX . '-tasks-reset' ) ) {
		$status_code = 'invalid-nonce';
	} elseif ( ! current_user_can( Mentors\MENTOR_CAP ) ) {
		$status_code = 'insufficient-permissions';
	} else {
		$status_code = _reset_tasks();
	}

	$redirect_url = add_query_arg( 'status', $status_code, $redirect_url );

	wp_safe_redirect( esc_url_raw( $redirect_url ) );
}

add_action( 'admin_post_' . Mentors\PREFIX . '-tasks-reset', __NAMESPACE__ . '\handle_tasks_reset' );

/**
 * Reset the list of task posts and their related taxonomy terms.
 *
 * @access private
 *
 * @since 1.0.0
 *
 * @return string Status code
 */
function _reset_tasks() {
	$results = array();

	// Delete existing tasks.
	$existing_tasks = get_posts( array(
		'post_type'      => Mentors\PREFIX . '_task',
		'post_status'    => array_keys( get_task_statuses() ),
		'posts_per_page' => 999,
	) );

	foreach ( $existing_tasks as $existing_task ) {
		$results[] = wp_delete_post( $existing_task->ID, true );
	}

	// Delete existing categories.
	$existing_categories = get_terms( array(
		'taxonomy'   => Mentors\PREFIX . '_task_category',
		'hide_empty' => false,
	) );

	foreach ( $existing_categories as $existing_category ) {
		$results[] = wp_delete_term( $existing_category->term_id, Mentors\PREFIX . '_task_category' );
	}

	// Create new categories.
	$new_category_data = get_task_category_data();

	foreach ( $new_category_data as $slug => $label ) {
		$results[] = wp_insert_term( $slug, Mentors\PREFIX . '_task_category' );
	}

	// Create new tasks.
	$new_task_data = get_task_data();
	$order = 0;

	foreach ( $new_task_data as $l10n_id => $data ) {
		$order += 10;

		$args = array(
			'post_type'   => Mentors\PREFIX . '_task',
			'post_status' => Mentors\PREFIX . '_task_pending',
			'post_title'  => $l10n_id,
			'menu_order'  => $order,
			'meta_input'  => array(
				Mentors\PREFIX . '-data-version' => Mentors\DATA_VERSION,
			),
		);

		$post_id = wp_insert_post( $args );

		$results[] = wp_set_object_terms( $post_id, $data['cat'], Mentors\PREFIX . '_task_category' );
	}

	if ( in_array( false, $results, true ) ||
	     ! empty( array_filter( $results, function( $i ) { return $i instanceof \WP_Error; } ) ) ) {
		return 'reset-errors';
	}

	return 'reset-success';
}

/**
 * Insert translated strings into REST response for tasks.
 *
 * The strings are translated here instead of when the task posts are inserted so that
 * they remain translatable if mentors and/or organizers who are viewing the Planning Checklist
 * have a different locale than the one used when the task data was set up.
 *
 * @since 1.0.0
 *
 * @param \WP_REST_Response $response The response object to be sent.
 * @param \WP_Post          $post     The post in the response object.
 *
 * @return \WP_REST_Response
 */
function localize_task( $response, $post ) {
	$l10n_id = $post->post_title;
	$task_data = get_task_data();

	if ( isset( $task_data[ $l10n_id ] ) ) {
		$parsed_data = wp_parse_args( $task_data[ $l10n_id ], array(
			'title'   => '',
			'excerpt' => '',
			'cat'     => array(),
			'link'    => array(
				'text' => '',
				'url'  => '',
			),
		) );

		$response->data['title']['rendered']   = apply_filters( 'the_title', $parsed_data['title'] );
		$response->data['excerpt']['rendered'] = wp_kses( $parsed_data['excerpt'], array() );
		$response->data['helpLink']['text']    = wp_kses( $parsed_data['link']['text'], array() );
		$response->data['helpLink']['url']     = esc_url( $parsed_data['link']['url'] );
	} else {
		$response->data['title']['rendered'] = esc_html__( 'Unknown task.', 'wordcamporg' );
	}

	$raw_modified = $response->data['modified'];
	$response->data['modified'] = array(
		'raw'      => $raw_modified,
		'relative' => sprintf(
			/* translators: Time since an event has occurred. */
			esc_html__( '%s ago', 'wordcamporg' ),
			human_time_diff( strtotime( $raw_modified ), current_time( 'timestamp' ) )
		),
	);

	return $response;
}

add_filter( 'rest_prepare_' . Mentors\PREFIX . '_task', __NAMESPACE__ . '\localize_task', 10, 2 );

/**
 * Insert translated strings into REST response for task categories.
 *
 * The strings are translated here instead of when the task posts are inserted so that
 * they remain translatable if mentors and/or organizers who are viewing the Planning Checklist
 * have a different locale than the one used when the task data was set up.
 *
 * @since 1.0.0
 *
 * @param \WP_REST_Response $response The response object to be sent.
 * @param \WP_Term          $item     The term in the response object.
 *
 * @return \WP_REST_Response
 */
function localize_task_category( $response, $item ) {
	$task_category_data = get_task_category_data();

	if ( isset( $task_category_data[ $item->slug ] ) ) {
		$response->data['name'] = $task_category_data[ $item->slug ];
	}

	return $response;
}

add_filter( 'rest_prepare_' . Mentors\PREFIX . '_task_category', __NAMESPACE__ . '\localize_task_category', 10, 2 );

/**
 * Record the username of the user updating the task post.
 *
 * @since 1.0.0
 *
 * @param \WP_Post $post The task post currently being updated.
 */
function update_last_modifier( $post ) {
	$user = wp_get_current_user();

	if ( $user instanceof \WP_User ) {
		update_post_meta( $post->ID, Mentors\PREFIX . '-last-modifier', $user->user_login );
	}
}

add_action( 'rest_insert_' . Mentors\PREFIX . '_task', __NAMESPACE__ . '\update_last_modifier' );
