<?php
/**
 * Adds sample agents and conversations, and sample data for the premium modules that are copied in.
 *
 * The helpdesk's setup is real: the mailboxes with custom fields, and the names of tags, teams, folders, saved
 * replies and workflows, come from HelpScout. People, conversations and texts are made up.
 *
 * Idempotent; run by setup.sh, after the modules are switched on. It finds what it added before by name or subject.
 * Threads and tags are added without FreeScout's events, so nothing is emailed or sent on, and no workflow runs.
 *
 * @package WordPressdotorg\FreeScout\Environment
 */

declare( strict_types = 1 );

namespace WordPressdotorg\FreeScout\Environment\SampleData;

use App\Conversation;
use App\Customer;
use App\Folder;
use App\Mailbox;
use App\Thread;
use App\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel;
use Modules\ApiWebhooks\Entities\ApiKey;
use Modules\ApiWebhooks\Entities\Webhook;
use Modules\CustomFields\Entities\CustomField;
use Modules\CustomFolders\Providers\CustomFoldersServiceProvider;
use Modules\Mentions\Providers\MentionsServiceProvider;
use Modules\Reports\Providers\ReportsServiceProvider;
use Modules\SavedReplies\Entities\SavedReply;
use Modules\Tags\Entities\Tag;
use Modules\Teams\Providers\TeamsServiceProvider;
use Modules\Workflows\Entities\Workflow;

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make( Kernel::class )->bootstrap();

/**
 * Hosts a local FreeScout runs on. Anything else could be production.
 */
const LOCAL_HOSTS = array( '127.0.0.1', 'localhost', '[::1]' );

/**
 * Mailboxes seed.php doesn't add, by name. Nothing fetches their email; they're here for their custom fields.
 *
 * @return array<string, string> Email addresses.
 */
function mailbox_data(): array {
	return array(
		'Learn WordPress' => 'learn@wordpress.test',
	);
}

/**
 * Sample agents, by email.
 *
 * @return array<string, array{0: string, 1: string}> First and last names.
 */
function agent_data(): array {
	return array(
		'priya@wordpress.test' => array( 'Priya', 'Shah' ),
		'marco@wordpress.test' => array( 'Marco', 'Rossi' ),
		'lena@wordpress.test'  => array( 'Lena', 'Fischer' ),
		'tomas@wordpress.test' => array( 'Tomás', 'García' ),
	);
}

/**
 * Sample senders, by email.
 *
 * @return array<string, array{0: string, 1: string}> First and last names.
 */
function customer_data(): array {
	return array(
		'jamie@example.com'  => array( 'Jamie', 'Okafor' ),
		'sofia@example.com'  => array( 'Sofia', 'Lindqvist' ),
		'kenji@example.com'  => array( 'Kenji', 'Watanabe' ),
		'amara@example.com'  => array( 'Amara', 'Nwosu' ),
		'lucas@example.com'  => array( 'Lucas', 'Moreau' ),
		'hannah@example.com' => array( 'Hannah', 'Becker' ),
		'diego@example.com'  => array( 'Diego', 'Fernández' ),
		'mei@example.com'    => array( 'Mei', 'Chen' ),
	);
}

/**
 * Sample conversations.
 *
 * Threads are [ who, body, hours after the conversation started ]: who is "customer", "agent" (the assignee) or
 * "note". Closed conversations close an hour after their last thread. Tags, custom field values (by option name) and
 * teams are added when their modules are there.
 *
 * @return array<int, array<string, mixed>>
 */
function conversation_data(): array {
	return array(
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Plugin submission: Simple Event Calendar',
			'customer' => 'jamie@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 54,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'plugin-review', 'review', 'approved' ),
			'threads'  => array(
				array( 'customer', 'Hi, I submitted Simple Event Calendar last week. Is there anything you need from me?', 0 ),
				array( 'agent', 'Thanks for your patience. We’re reviewing it now and will get back to you with any issues.', 20 ),
				array( 'note', 'Code looks clean. Only a missing text domain in two strings.', 26 ),
				array( 'agent', 'Your plugin is approved. You’ll get an email with SVN access shortly.', 30 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Security issue in Contact Form Builder',
			'customer' => 'sofia@example.com',
			'agent'    => 'marco@wordpress.test',
			'days_ago' => 51,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'notice' ),
			'threads'  => array(
				array( 'customer', 'Contact Form Builder 2.3 doesn’t check nonces when saving forms. Any logged-in user can overwrite them.', 0 ),
				array( 'note', 'Confirmed on 2.3. Closing the plugin until the author fixes it.', 4 ),
				array( 'agent', 'Thanks for the report. We’ve closed the plugin and contacted the author.', 6 ),
				array( 'customer', 'Great, thank you for the quick response.', 30 ),
				array( 'agent', 'Version 2.3.1 fixes it, and the plugin is open again.', 200 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Trademark in plugin name: “WooCommerce Turbo Checkout”',
			'customer' => 'kenji@example.com',
			'agent'    => 'lena@wordpress.test',
			'days_ago' => 47,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'plugin-review', 'review' ),
			'threads'  => array(
				array( 'customer', 'Why was my plugin name rejected? Lots of plugins use WooCommerce in their name.', 0 ),
				array( 'agent', 'Names can’t start with someone else’s trademark. “Turbo Checkout for WooCommerce” is fine.', 9 ),
				array( 'customer', 'Understood, I’ll resubmit with that name.', 15 ),
				array( 'agent', 'Thanks! Go ahead and we’ll pick it up.', 18 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Please close my plugin',
			'customer' => 'amara@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 44,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'I no longer maintain Quick Share Buttons. Can you close it?', 0 ),
				array( 'agent', 'Done. It’s closed, and the slug stays reserved.', 5 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Can I change my plugin’s slug?',
			'customer' => 'lucas@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 40,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'I picked a bad slug. Can it be changed before I commit any code?', 0 ),
				array( 'agent', 'Yes, as long as nothing is committed. What should it be?', 3 ),
				array( 'customer', 'lucas-seo-tools, please.', 5 ),
				array( 'agent', 'Changed. Your SVN URL is updated.', 8 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'SVN commit rejected',
			'customer' => 'hannah@example.com',
			'agent'    => 'marco@wordpress.test',
			'days_ago' => 36,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'Every commit fails with “403 Forbidden”. My password works on the website.', 0 ),
				array( 'agent', 'SVN needs the SVN password from your profile, not your account password.', 2 ),
				array( 'customer', 'That was it. Thanks!', 4 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Plugin submission: Recipe Cards Lite',
			'customer' => 'diego@example.com',
			'agent'    => 'lena@wordpress.test',
			'days_ago' => 33,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'plugin-review', 'review', 'rejected', 'guidelines' ),
			'threads'  => array(
				array( 'customer', 'Submitted Recipe Cards Lite. Let me know if anything is wrong.', 0 ),
				array( 'agent', 'The plugin shows upsell notices on every admin page, and bundles a font that isn’t GPL compatible. Please fix both and reply.', 48 ),
				array( 'customer', 'I removed the font but the notices are how I make money.', 70 ),
				array( 'agent', 'Notices have to be dismissible and limited to your own pages. We can’t approve it as it is.', 75 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Transfer plugin ownership',
			'customer' => 'mei@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 29,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'The original author of Image Optimizer Pro asked me to take it over. How do we do that?', 0 ),
				array( 'agent', 'The current owner can add you as a committer and then transfer it from the Advanced view.', 12 ),
				array( 'customer', 'Done, thanks.', 40 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Security issue in Gallery Slider Pro',
			'customer' => 'kenji@example.com',
			'agent'    => 'marco@wordpress.test',
			'days_ago' => 24,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'notice' ),
			'threads'  => array(
				array( 'customer', 'Gallery Slider Pro echoes the “slide” parameter without escaping. Proof of concept attached.', 0 ),
				array( 'agent', 'Thanks, we’ve forwarded it to the author.', 3 ),
				array( 'note', 'Author replied within a day. Fix is in 4.1.2.', 30 ),
				array( 'agent', 'The author released 4.1.2 with a fix. Thanks again.', 32 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Re: Test your plugins with WordPress 6.9',
			'customer' => 'hannah@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 20,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'reply-to-new-wp' ),
			'threads'  => array(
				array( 'customer', 'Tested with the release candidate and everything works. Do I need to change anything?', 0 ),
				array( 'agent', 'Just bump “Tested up to” in your readme. No new release needed.', 22 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'When will my plugin be reviewed?',
			'customer' => 'sofia@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 19,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'review' ),
			'threads'  => array(
				array( 'customer', 'It’s been ten days since I submitted. Is something wrong?', 0 ),
				array( 'agent', 'Nothing’s wrong, the queue is just long. You’ll hear from us soon.', 26 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Plugin submission: AI Content Writer',
			'customer' => 'lucas@example.com',
			'agent'    => 'marco@wordpress.test',
			'days_ago' => 14,
			'status'   => Conversation::STATUS_PENDING,
			'tags'     => array( 'plugin-review', 'review' ),
			'threads'  => array(
				array( 'customer', 'Submitting AI Content Writer. It needs an API key from our service.', 0 ),
				array( 'agent', 'Please document what data is sent to your service, and link its terms in the readme.', 28 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Plugin submission: Accessible Tabs',
			'customer' => 'hannah@example.com',
			'agent'    => 'lena@wordpress.test',
			'days_ago' => 12,
			'status'   => Conversation::STATUS_PENDING,
			'tags'     => array( 'plugin-review', 'review', 'new' ),
			'threads'  => array(
				array( 'customer', 'Submitting Accessible Tabs, a small block for keyboard-friendly tabs.', 0 ),
				array( 'agent', 'Nice plugin. Please prefix your function names, and we’ll approve it.', 30 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Report: plugin copies my code',
			'customer' => 'lucas@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 8,
			'status'   => Conversation::STATUS_ACTIVE,
			'tags'     => array( 'guidelines' ),
			'threads'  => array(
				array( 'customer', 'Easy Popups is a copy of my plugin with the names changed and my copyright removed.', 0 ),
				array( 'agent', 'Thanks for letting us know. We’re comparing the two now.', 6 ),
				array( 'customer', 'Here’s a diff that shows it, if it helps.', 20 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Report: fake reviews on Quick SEO',
			'customer' => 'diego@example.com',
			'agent'    => null,
			'team'     => 'Plugin Reports',
			'days_ago' => 5,
			'status'   => Conversation::STATUS_ACTIVE,
			'tags'     => array( 'guidelines' ),
			'threads'  => array(
				array( 'customer', 'Quick SEO’s five-star reviews all came in on the same day, from brand new accounts.', 0 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Re: Test your plugins with WordPress 6.9',
			'customer' => 'jamie@example.com',
			'agent'    => null,
			'days_ago' => 3,
			'status'   => Conversation::STATUS_ACTIVE,
			'tags'     => array( 'reply-to-new-wp' ),
			'threads'  => array(
				array( 'customer', 'My calendar block breaks in the editor with 6.9. Is there a migration guide?', 0 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Security issue in Booking Manager',
			'customer' => 'amara@example.com',
			'agent'    => null,
			'team'     => 'Plugin Security',
			'days_ago' => 2,
			'status'   => Conversation::STATUS_ACTIVE,
			'threads'  => array(
				array( 'customer', 'Booking Manager lets subscribers export every booking through admin-ajax.php.', 0 ),
			),
		),
		array(
			'mailbox'  => 'Plugins',
			'subject'  => 'Cheap SEO backlinks for your site',
			'customer' => 'mei@example.com',
			'agent'    => null,
			'days_ago' => 1,
			'status'   => Conversation::STATUS_SPAM,
			'threads'  => array(
				array( 'customer', 'Buy 10,000 backlinks today and rank first on every search engine.', 0 ),
			),
		),
		array(
			'mailbox'  => 'Themes',
			'subject'  => 'Reported theme: Coastline loads fonts from a CDN',
			'customer' => 'jamie@example.com',
			'agent'    => 'lena@wordpress.test',
			'days_ago' => 52,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'reported-theme' ),
			'threads'  => array(
				array( 'customer', 'Coastline loads Google Fonts from a CDN without asking. Isn’t that against the guidelines?', 0 ),
				array( 'agent', 'Thanks, it is. We’ve asked the author to bundle the fonts.', 30 ),
				array( 'customer', 'Thanks!', 32 ),
			),
		),
		array(
			'mailbox'  => 'Themes',
			'subject'  => 'Upload fails: “The theme name already exists”',
			'customer' => 'sofia@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 43,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'I get “The theme name already exists” but I can’t find a theme with my name.', 0 ),
				array( 'agent', 'A theme with that slug was closed years ago, and slugs aren’t reused. Please pick another name.', 7 ),
			),
		),
		array(
			'mailbox'  => 'Themes',
			'subject'  => 'Reported theme: Harbor menu doesn’t work',
			'customer' => 'hannah@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 34,
			'status'   => Conversation::STATUS_CLOSED,
			'tags'     => array( 'reported-theme', 'support' ),
			'threads'  => array(
				array( 'customer', 'The mobile menu in Harbor doesn’t open on my site. Please fix it.', 0 ),
				array( 'agent', 'This address is for guideline issues. Please ask in Harbor’s support forum, where the author can help.', 4 ),
			),
		),
		array(
			'mailbox'  => 'Themes',
			'subject'  => 'Pattern removed from the directory',
			'customer' => 'kenji@example.com',
			'agent'    => 'lena@wordpress.test',
			'days_ago' => 26,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'My “Pricing table” pattern disappeared from the Pattern Directory. Why?', 0 ),
				array( 'note', 'It used an image from a stock site without a license.', 2 ),
				array( 'agent', 'Its images need a GPL-compatible license. Swap them and submit it again.', 3 ),
			),
		),
		array(
			'mailbox'  => 'Themes',
			'subject'  => 'Security issue in Minimal Folio',
			'customer' => 'diego@example.com',
			'agent'    => null,
			'team'     => 'Themes',
			'days_ago' => 9,
			'status'   => Conversation::STATUS_ACTIVE,
			'tags'     => array( 'reported-theme' ),
			'threads'  => array(
				array( 'customer', 'Minimal Folio’s contact template prints the search query without escaping.', 0 ),
			),
		),
		array(
			'mailbox'  => 'Themes',
			'subject'  => 'Theme update not showing up',
			'customer' => 'lucas@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 1,
			'status'   => Conversation::STATUS_ACTIVE,
			'threads'  => array(
				array( 'customer', 'I uploaded version 1.4 of Coastline two days ago but sites still see 1.3.', 0 ),
				array( 'agent', 'Updates go live once the reviewer approves them. Yours is next in line.', 3 ),
				array( 'customer', 'Okay, thanks for checking.', 6 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Certificate for the Beginner WordPress User course',
			'customer' => 'hannah@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 55,
			'status'   => Conversation::STATUS_CLOSED,
			'fields'   => array(
				'WordPress.org username' => 'hannahbecker',
			),
			'threads'  => array(
				array( 'customer', 'I finished every lesson of the Beginner WordPress User course, but my profile doesn’t show it as completed.', 0 ),
				array( 'agent', 'One quiz was still marked as in progress. It’s complete now, and the course shows on your profile.', 18 ),
				array( 'customer', 'It’s there now, thank you!', 22 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Lesson plan feedback',
			'customer' => 'diego@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 48,
			'status'   => Conversation::STATUS_CLOSED,
			'fields'   => array(
				'WordPress.org username' => 'diegofernandez',
			),
			'threads'  => array(
				array( 'customer', 'The block themes lesson plan links to a page that no longer exists.', 0 ),
				array( 'agent', 'Thanks for spotting it. The link is fixed.', 8 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Recording of last week’s online meetup',
			'customer' => 'sofia@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 41,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'I missed the online meetup about site editing. Will there be a recording?', 0 ),
				array( 'note', 'Recording is still being captioned; should be up by Friday.', 3 ),
				array( 'agent', 'Yes, it’ll be on Learn WordPress by Friday, with captions.', 4 ),
				array( 'customer', 'Found it, thanks.', 80 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Translating a course into Spanish',
			'customer' => 'diego@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 35,
			'status'   => Conversation::STATUS_CLOSED,
			'fields'   => array(
				'WordPress.org username' => 'diegofernandez',
			),
			'threads'  => array(
				array( 'customer', 'I’d like to translate the Developing Your First Plugin course into Spanish. How do I start?', 0 ),
				array( 'agent', 'Wonderful! Translations go through the Training team’s GitHub repository; open an issue there and we’ll set up the course for you.', 12 ),
				array( 'customer', 'Issue opened.', 30 ),
				array( 'agent', 'Thanks, the team has picked it up there.', 36 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Tutorial submission: Building a custom block pattern',
			'customer' => 'amara@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 27,
			'status'   => Conversation::STATUS_CLOSED,
			'fields'   => array(
				'WordPress.org username' => 'amaranwosu',
			),
			'threads'  => array(
				array( 'customer', 'I recorded a tutorial on building block patterns. Where do I send it?', 0 ),
				array( 'agent', 'Thanks! Please submit it through the tutorial form, so a reviewer can take a look.', 6 ),
				array( 'note', 'Submission received; assigned to a reviewer.', 30 ),
				array( 'agent', 'Your tutorial is published. Thanks for contributing!', 140 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Workshop video has no sound',
			'customer' => 'mei@example.com',
			'agent'    => 'priya@wordpress.test',
			'days_ago' => 19,
			'status'   => Conversation::STATUS_CLOSED,
			'threads'  => array(
				array( 'customer', 'The second half of the Query Loop workshop has no sound.', 0 ),
				array( 'agent', 'Thanks, we’ve replaced the video with a fixed one.', 26 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Can I reopen my tutorial submission?',
			'customer' => 'kenji@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 13,
			'status'   => Conversation::STATUS_PENDING,
			'fields'   => array(
				'WordPress.org username' => 'kenjiw',
				'Reopen Date'            => 'days:27',
			),
			'threads'  => array(
				array( 'customer', 'I couldn’t finish my tutorial in time. Could it be reopened in a few weeks?', 0 ),
				array( 'agent', 'Sure. We’ll reopen it then; just reply here when you’re ready.', 9 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Pausing my course draft',
			'customer' => 'lucas@example.com',
			'agent'    => 'tomas@wordpress.test',
			'days_ago' => 9,
			'status'   => Conversation::STATUS_PENDING,
			'fields'   => array(
				'WordPress.org username' => 'lucasmoreau',
				'Reopen Date'            => 'days:40',
			),
			'threads'  => array(
				array( 'customer', 'I’m moving house and need to pause my WooCommerce basics course draft for a month.', 0 ),
				array( 'note', 'Draft is about half done. Check in again after the reopen date.', 2 ),
				array( 'agent', 'No problem. We’ve paused the draft and will check in with you next month.', 3 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Becoming an online workshop facilitator',
			'customer' => 'jamie@example.com',
			'agent'    => null,
			'team'     => 'Learn',
			'days_ago' => 4,
			'status'   => Conversation::STATUS_ACTIVE,
			'fields'   => array(
				'WordPress.org username' => 'jamieokafor',
			),
			'threads'  => array(
				array( 'customer', 'I’d like to run online workshops for Learn WordPress. Where do I start?', 0 ),
			),
		),
		array(
			'mailbox'  => 'Learn WordPress',
			'subject'  => 'Quiz answer seems wrong',
			'customer' => 'sofia@example.com',
			'agent'    => null,
			'days_ago' => 1,
			'status'   => Conversation::STATUS_ACTIVE,
			'fields'   => array(
				'WordPress.org username' => 'sofialindqvist',
			),
			'threads'  => array(
				array( 'customer', 'Question 4 in the Site Editor quiz marks “Templates” as wrong, but the lesson says it’s right.', 0 ),
				array( 'customer', 'Here’s a screenshot of the lesson, in case it helps.', 1 ),
			),
		),
	);
}

/**
 * Refuses to run anywhere but on a local FreeScout.
 *
 * @return void
 */
function require_local(): void {
	$url  = (string) config( 'app.url' );
	$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );

	if ( ! in_array( $host, LOCAL_HOSTS, true ) ) {
		echo "Not adding sample data to {$url}: sample data is only for a local FreeScout.\n";
		exit( 1 );
	}
}

/**
 * Finds a mailbox by name.
 *
 * @param string $name Mailbox name.
 * @return Mailbox
 */
function mailbox( string $name ): Mailbox {
	$mailbox = Mailbox::where( 'name', $name )->first();
	if ( ! $mailbox ) {
		echo "Mailbox {$name} is missing; run the environment's setup first.\n";
		exit( 1 );
	}

	return $mailbox;
}

/**
 * Finds the admin, who owns what an agent would make in the UI.
 *
 * @return User
 */
function admin(): User {
	return User::where( 'role', User::ROLE_ADMIN )->orderBy( 'id' )->firstOrFail();
}

/**
 * Finds an agent by email.
 *
 * @param string $email Agent email.
 * @return User
 */
function agent( string $email ): User {
	return User::where( 'email', $email )->firstOrFail();
}

/**
 * Creates the sample agents, with access to the sample mailboxes.
 *
 * @param Mailbox[] $mailboxes Mailboxes they work in.
 * @return void
 */
function seed_agents( array $mailboxes ): void {
	foreach ( agent_data() as $email => list( $first_name, $last_name ) ) {
		$user = User::where( 'email', $email )->first();
		if ( ! $user ) {
			$user               = new User();
			$user->first_name   = $first_name;
			$user->last_name    = $last_name;
			$user->email        = $email;
			$user->password     = \Hash::make( $user->generateRandomPassword() );
			$user->role         = User::ROLE_USER;
			$user->invite_state = User::INVITE_STATE_ACTIVATED;
			$user->save();

			echo "Created agent {$email}\n";
		}

		foreach ( $mailboxes as $mailbox ) {
			$mailbox->users()->syncWithoutDetaching( array( $user->id ) );
		}
	}
}

/**
 * Creates the sample senders.
 *
 * @return void
 */
function seed_customers(): void {
	foreach ( customer_data() as $email => list( $first_name, $last_name ) ) {
		Customer::create(
			$email,
			array(
				'first_name' => $first_name,
				'last_name'  => $last_name,
			)
		);
	}
}

/**
 * Finds a sample conversation.
 *
 * @param array $data One of conversation_data().
 * @return Conversation|null
 */
function find_conversation( array $data ): ?Conversation {
	return Conversation::where( 'mailbox_id', mailbox( $data['mailbox'] )->id )
		->where( 'subject', $data['subject'] )
		->where( 'customer_email', $data['customer'] )
		->first();
}

/**
 * Adds a thread, without the events and notifications of a new one.
 *
 * @param Conversation $conversation Conversation.
 * @param array        $values       Thread attributes.
 * @return void
 */
function add_thread( Conversation $conversation, array $values ): void {
	$thread = new Thread();
	$thread->forceFill( $values );
	$thread->conversation_id = $conversation->id;
	$thread->state           = Thread::STATE_PUBLISHED;
	$thread->first           = ! $conversation->threads()->exists();
	$thread->updated_at      = $values['created_at'];

	Thread::query()->insert( $thread->getAttributes() );
}

/**
 * Creates a sample conversation with its threads, if it's missing.
 *
 * @param array $data One of conversation_data().
 * @return bool Whether it was created.
 */
function seed_conversation( array $data ): bool {
	if ( find_conversation( $data ) ) {
		return false;
	}

	$mailbox  = mailbox( $data['mailbox'] );
	$customer = Customer::create( $data['customer'] );
	$agent    = $data['agent'] ? agent( $data['agent'] ) : null;
	$started  = Carbon::now()->subDays( $data['days_ago'] )->setTime( 9, 0 )->addMinutes( crc32( $data['subject'] ) % 480 );

	$conversation                         = new Conversation();
	$conversation->type                   = Conversation::TYPE_EMAIL;
	$conversation->subject                = $data['subject'];
	$conversation->mailbox_id             = $mailbox->id;
	$conversation->customer_id            = $customer->id;
	$conversation->customer_email         = $data['customer'];
	$conversation->source_via             = Conversation::PERSON_CUSTOMER;
	$conversation->source_type            = Conversation::SOURCE_TYPE_EMAIL;
	$conversation->state                  = Conversation::STATE_PUBLISHED;
	$conversation->status                 = Conversation::STATUS_ACTIVE;
	$conversation->read_by_user           = true;
	$conversation->created_by_customer_id = $customer->id;
	$conversation->created_at             = $started;
	$conversation->updated_at             = $started;

	// Like the HelpScout importer: core uses up the next number an administrator set while numbering a conversation.
	$next_number = \Option::get( 'next_ticket', 0, true, false );
	$conversation->save();
	if ( $next_number ) {
		\Option::set( 'next_ticket', $next_number );
	}

	$last = $started;
	foreach ( $data['threads'] as list( $who, $body, $hours ) ) {
		$last   = $started->copy()->addMinutes( (int) ( $hours * 60 ) );
		$values = array(
			'body'       => $body,
			'created_at' => $last,
			'user_id'    => $agent ? $agent->id : null,
		);

		if ( 'customer' === $who ) {
			$values += array(
				'type'                   => Thread::TYPE_CUSTOMER,
				'status'                 => Thread::STATUS_ACTIVE,
				'source_via'             => Thread::PERSON_CUSTOMER,
				'source_type'            => Thread::SOURCE_TYPE_EMAIL,
				'customer_id'            => $customer->id,
				'created_by_customer_id' => $customer->id,
				'from'                   => $data['customer'],
			);
		} else {
			$values += array(
				'type'               => 'note' === $who ? Thread::TYPE_NOTE : Thread::TYPE_MESSAGE,
				'status'             => 'note' === $who ? Thread::STATUS_NOCHANGE : $data['status'],
				'source_via'         => Thread::PERSON_USER,
				'source_type'        => Thread::SOURCE_TYPE_WEB,
				'customer_id'        => $customer->id,
				'created_by_user_id' => ( $agent ?? admin() )->id,
			);
			if ( 'agent' === $who ) {
				$values['to'] = \Helper::jsonEncodeUtf8( array( $data['customer'] ) );
			}
		}

		add_thread( $conversation, $values );
	}

	if ( Conversation::STATUS_CLOSED === $data['status'] ) {
		$closer = $agent ?? admin();
		$last   = $last->copy()->addHour();

		add_thread(
			$conversation,
			array(
				'type'               => Thread::TYPE_LINEITEM,
				'status'             => Thread::STATUS_CLOSED,
				'action_type'        => Thread::ACTION_TYPE_STATUS_CHANGED,
				'source_via'         => Thread::PERSON_USER,
				'source_type'        => Thread::SOURCE_TYPE_WEB,
				'customer_id'        => $customer->id,
				'created_by_user_id' => $closer->id,
				'user_id'            => $agent ? $agent->id : null,
				'created_at'         => $last,
			)
		);

		$conversation->closed_at         = $last;
		$conversation->closed_by_user_id = $closer->id;
	}

	update_conversation( $conversation, $data['status'], $agent, $last );

	return true;
}

/**
 * Sets what core's thread observer and status changes would have set on a conversation.
 *
 * @param Conversation $conversation Conversation.
 * @param int          $status       Conversation status.
 * @param User|null    $agent        Assignee.
 * @param Carbon       $updated      When it was last updated.
 * @return void
 */
function update_conversation( Conversation $conversation, int $status, ?User $agent, Carbon $updated ): void {
	$replies = $conversation->threads()->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) )->orderBy( 'created_at' )->get();
	$last    = $replies->last();

	$conversation->status          = $status;
	$conversation->user_id         = $agent ? $agent->id : null;
	$conversation->threads_count   = $replies->count();
	$conversation->last_reply_at   = $last->created_at;
	$conversation->last_reply_from = $last->source_via;
	$conversation->user_updated_at = $updated;
	$conversation->updated_at      = $updated;
	$conversation->setPreview( (string) $last->body );

	$last_from_customer = $replies->where( 'type', Thread::TYPE_CUSTOMER )->last();
	if ( $last_from_customer && \Schema::hasColumn( 'conversations', 'last_customer_reply_at' ) ) {
		$conversation->last_customer_reply_at = $last_from_customer->created_at;
	}

	$conversation->timestamps = false;
	$conversation->updateFolder();
	$conversation->save();
	$conversation->timestamps = true;
}

/**
 * Creates the mailboxes seed.php doesn't add, if missing. Replies sent from them land in Mailpit.
 *
 * @return void
 */
function seed_mailboxes(): void {
	foreach ( mailbox_data() as $name => $email ) {
		if ( Mailbox::where( 'email', $email )->exists() ) {
			continue;
		}

		$mailbox = new Mailbox();
		$mailbox->fill(
			array(
				'name'           => $name,
				'email'          => $email,
				'out_method'     => Mailbox::OUT_METHOD_SMTP,
				'out_server'     => 'mailpit',
				'out_port'       => 1025,
				'out_encryption' => Mailbox::OUT_ENCRYPTION_NONE,
			)
		);
		$mailbox->save();

		echo "Created mailbox {$name} <{$email}>\n";
	}
}

/**
 * Tags: HelpScout's tags and colors, on sample conversations.
 *
 * Attached without the module's events, which would run workflows on the sample conversations.
 *
 * @return void
 */
function seed_tags(): void {
	if ( ! class_exists( Tag::class ) ) {
		return;
	}

	$colors = array(
		'plugin-review'   => Tag::COLOR_DEFAULT,
		'review'          => Tag::COLOR_DEFAULT,
		'approved'        => Tag::COLOR_GREEN,
		'rejected'        => Tag::COLOR_VIOLET,
		'new'             => Tag::COLOR_GREEN,
		'has-attachment'  => Tag::COLOR_DEFAULT,
		'notice'          => Tag::COLOR_BLUE,
		'guidelines'      => Tag::COLOR_BLUE,
		'auto-bounce'     => Tag::COLOR_YELLOW,
		'reply-to-new-wp' => Tag::COLOR_DEFAULT,
		'reported-theme'  => Tag::COLOR_DEFAULT,
		'support'         => Tag::COLOR_DEFAULT,
	);

	foreach ( $colors as $name => $color ) {
		// Only new ones: an agent may have changed the color since.
		if ( ! Tag::where( 'name', $name )->exists() ) {
			$tag = Tag::getOrCreate( array( 'name' => $name ) );
			$tag->setColor( $color );
			$tag->save();
		}
	}

	foreach ( conversation_data() as $data ) {
		$conversation = find_conversation( $data );
		foreach ( $data['tags'] ?? array() as $name ) {
			if ( ! isset( $colors[ $name ] ) ) {
				echo "Not adding tag {$name} to “{$data['subject']}”: it isn't one of the sample tags.\n";
				continue;
			}

			$tag = Tag::where( 'name', $name )->first();
			if ( $conversation && ! $tag->conversations()->whereKey( $conversation->id )->exists() ) {
				$tag->conversations()->attach( $conversation->id );
				$tag->counter = $tag->conversations()->count();
				$tag->save();
			}
		}
	}
}

/**
 * Custom Fields: HelpScout's fields in Learn WordPress, with values on sample conversations.
 *
 * @return void
 */
function seed_custom_fields(): void {
	if ( ! class_exists( CustomField::class ) ) {
		return;
	}

	$mailbox = mailbox( 'Learn WordPress' );
	$fields  = array(
		'WordPress.org username' => CustomField::TYPE_SINGLE_LINE,
		'Reopen Date'            => CustomField::TYPE_DATE,
	);

	foreach ( $fields as $name => $type ) {
		if ( CustomField::where( 'mailbox_id', $mailbox->id )->where( 'name', $name )->exists() ) {
			continue;
		}

		$field             = new CustomField();
		$field->mailbox_id = $mailbox->id;
		$field->name       = $name;
		$field->type       = $type;
		$field->options    = '';
		$field->setSortOrderLast();
		$field->save();
	}

	foreach ( conversation_data() as $data ) {
		$conversation = find_conversation( $data );
		if ( ! $conversation ) {
			continue;
		}

		foreach ( $data['fields'] ?? array() as $name => $value ) {
			$field = CustomField::where( 'mailbox_id', $conversation->mailbox_id )->where( 'name', $name )->first();
			if ( ! $field ) {
				echo "Not setting {$name} on “{$data['subject']}”: its mailbox has no such field.\n";
				continue;
			}

			// Only once: an agent may have changed it since.
			if ( \DB::table( 'conversation_custom_field' )->where( 'conversation_id', $conversation->id )->where( 'custom_field_id', $field->id )->exists() ) {
				continue;
			}

			// Dates are relative to the conversation, so they stay put on later runs.
			if ( str_starts_with( $value, 'days:' ) ) {
				$value = $conversation->created_at->copy()->addDays( (int) substr( $value, 5 ) )->format( 'Y-m-d' );
			}

			CustomField::setValue( $conversation->id, $field->id, $value );
		}
	}
}

/**
 * Saved Replies: HelpScout's saved replies in Plugins and Themes, with short made-up texts.
 *
 * HelpScout has no categories, so they're flat, as the HelpScout importer adds them.
 *
 * @return void
 */
function seed_saved_replies(): void {
	if ( ! class_exists( SavedReply::class ) ) {
		return;
	}

	$replies = array(
		'Plugins' => array(
			'Approved: Your plugin was approved'          => 'Your plugin has been approved! You’ll get an email with SVN access shortly.',
			'Notice: Waiting on reply to complete review' => 'We’re waiting for your reply to finish reviewing your plugin.',
			'Reply: Backlog of Reviews (Please be patient)' => 'We have a backlog of reviews right now. Thanks for your patience.',
			'Reply: Check your spam folder / filters'     => 'We replied a while ago. Please check your spam folder, and allow email from wordpress.org.',
			'Reply: Can\'t commit to SVN, Authentication failed' => 'SVN needs the SVN password from your profile, not your account password.',
		),
		'Themes'  => array(
			'Reply: Categorization done'  => 'We’ve updated your theme’s categories.',
			'Reply: Reported for Support' => 'This address is for guideline issues. Please ask in the theme’s support forum.',
		),
	);

	$admin = admin();
	foreach ( $replies as $mailbox_name => $mailbox_replies ) {
		$mailbox = mailbox( $mailbox_name );
		foreach ( $mailbox_replies as $name => $text ) {
			$reply = SavedReply::firstOrNew(
				array(
					'mailbox_id' => $mailbox->id,
					'name'       => $name,
				)
			);
			if ( ! $reply->exists ) {
				$reply->text    = $text;
				$reply->user_id = $admin->id;
				$reply->save();
			}
		}
	}
}

/**
 * Teams: HelpScout's teams, with sample agents and their mailbox, and a conversation assigned to some.
 *
 * Team names have at most 20 characters, so the longer HelpScout names are shortened.
 *
 * @return void
 */
function seed_teams(): void {
	if ( ! class_exists( TeamsServiceProvider::class ) ) {
		return;
	}

	$teams = array(
		'Plugin Reviews'  => array( 'Plugins', 'eye-open', array( 'priya@wordpress.test', 'marco@wordpress.test', 'lena@wordpress.test' ) ),
		'Plugin Security' => array( 'Plugins', 'lock', array( 'marco@wordpress.test' ) ),
		'Plugin Admin'    => array( 'Plugins', 'cog', array( 'priya@wordpress.test' ) ),
		'Plugin Reports'  => array( 'Plugins', 'flag', array( 'tomas@wordpress.test' ) ),
		'Themes'          => array( 'Themes', 'picture', array( 'lena@wordpress.test', 'tomas@wordpress.test' ) ),
		'Learn'           => array( 'Learn WordPress', 'education', array( 'tomas@wordpress.test' ) ),
	);

	foreach ( $teams as $name => list( $mailbox_name, $icon, $emails ) ) {
		$mailbox = mailbox( $mailbox_name );
		$team    = TeamsServiceProvider::getTeams()->firstWhere( 'first_name', $name );

		if ( ! $team ) {
			$team               = new User();
			$team->first_name   = $name;
			$team->last_name    = TeamsServiceProvider::TEAM_USER_LAST_NAME;
			$team->photo_url    = $icon;
			$team->role         = User::ROLE_USER;
			$team->status       = User::STATUS_DELETED;
			$team->type         = User::TYPE_ROBOT;
			$team->invite_state = User::INVITE_STATE_ACTIVATED;
			$team->email        = TeamsServiceProvider::generateEmail();
			$team->password     = \Hash::make( $team->generateRandomPassword() );
			TeamsServiceProvider::setMembers( $team, User::whereIn( 'email', $emails )->pluck( 'id' )->all() );
			$team->save();

			$team->mailboxes()->sync( array( $mailbox->id ) );

			$folder             = new Folder();
			$folder->type       = TeamsServiceProvider::FOLDER_TYPE;
			$folder->mailbox_id = $mailbox->id;
			$folder->user_id    = $team->id;
			$folder->setMeta( 'icon', $icon );
			$folder->save();

			echo "Created team {$name}\n";
		}

		// Assigned once: an agent may have reassigned it since.
		foreach ( conversation_data() as $data ) {
			$conversation = ( $data['team'] ?? '' ) === $name ? find_conversation( $data ) : null;
			if ( $conversation && ! $conversation->user_id && ! $conversation->getMeta( 'wporg_sample_team' ) ) {
				$conversation->user_id = $team->id;
				$conversation->setMeta( 'wporg_sample_team', true );
				$conversation->updateFolder();
				$conversation->save();
			}
		}
	}
}

/**
 * Custom Folders: HelpScout's "New WordPress" folder in Plugins, for replies to new WordPress version emails.
 *
 * @return void
 */
function seed_custom_folders(): void {
	// Its folder is by tag.
	if ( ! class_exists( CustomFoldersServiceProvider::class ) || ! class_exists( Tag::class ) ) {
		return;
	}

	$mailbox = mailbox( 'Plugins' );
	$name    = 'New WordPress';
	$folder  = CustomFoldersServiceProvider::mailboxCustomFolders( $mailbox->id, false )->first(
		static function ( Folder $folder ) use ( $name ): bool {
			return ( $folder->meta['name'] ?? '' ) === $name;
		}
	);

	if ( ! $folder ) {
		$folder             = new Folder();
		$folder->mailbox_id = $mailbox->id;
		$folder->type       = CustomFoldersServiceProvider::TYPE_CUSTOM;
		$folder->meta       = array(
			'order'      => 1000,
			'name'       => $name,
			'tag_id'     => (int) Tag::getOrCreate( array( 'name' => 'reply-to-new-wp' ) )->id,
			'counter'    => Folder::COUNTER_ACTIVE,
			'own_only'   => '',
			'unassigned' => '',
			'icon'       => 'bullhorn',
		);
		$folder->save();

		echo "Created folder {$name}\n";
	}

	// Tags are attached without the events that would count them.
	CustomFoldersServiceProvider::setCounters( $folder->fresh() );
}

/**
 * Mentions: a note in which one agent mentions another.
 *
 * Added like the other sample threads, so the mentioned agent isn't notified.
 *
 * @return void
 */
function seed_mentions(): void {
	if ( ! class_exists( MentionsServiceProvider::class ) ) {
		return;
	}

	$conversation = Conversation::where( 'subject', 'Report: plugin copies my code' )->where( 'customer_email', 'lucas@example.com' )->first();
	$author       = agent( 'tomas@wordpress.test' );
	$mentioned    = agent( 'marco@wordpress.test' );

	$body = '<span><b data-mentioned-id="' . $mentioned->id . '">@' . $mentioned->first_name . '</b>&nbsp;</span>could you compare the two plugins? You reviewed the original.';

	if ( ! $conversation || $conversation->threads()->where( 'type', Thread::TYPE_NOTE )->where( 'body', $body )->exists() ) {
		return;
	}

	add_thread(
		$conversation,
		array(
			'type'               => Thread::TYPE_NOTE,
			'status'             => Thread::STATUS_NOCHANGE,
			'source_via'         => Thread::PERSON_USER,
			'source_type'        => Thread::SOURCE_TYPE_WEB,
			'customer_id'        => $conversation->customer_id,
			'created_by_user_id' => $author->id,
			'user_id'            => $conversation->user_id,
			'body'               => $body,
			'created_at'         => $conversation->last_reply_at->copy()->addHour(),
		)
	);

	echo "Added a note in which {$author->first_name} mentions {$mentioned->first_name}\n";
}

/**
 * A workflow condition or action.
 *
 * @param string $type     Condition or action type.
 * @param mixed  $value    Its value.
 * @param string $operator Condition operator.
 * @return array
 */
function rule( string $type, mixed $value, string $operator = '' ): array {
	$rule = array(
		'type'  => $type,
		'value' => $value,
	);
	if ( $operator ) {
		$rule['operator'] = $operator;
	}

	return $rule;
}

/**
 * A workflow action that adds a note or sends a reply.
 *
 * @param string $type "note" or "reply".
 * @param string $body Its text.
 * @return array
 */
function message( string $type, string $body ): array {
	return rule( $type, \Helper::jsonEncodeUtf8( array( 'body' => $body ) ) );
}

/**
 * Workflows: HelpScout's workflow names, with conditions and actions made up to fit them.
 *
 * Automatic ones only match new email with particular subjects, or a tag an agent adds; the sample conversations get
 * their tags without events, so none of them run. The one reply only goes to Mailpit.
 *
 * @return void
 */
function seed_workflows(): void {
	if ( ! class_exists( Workflow::class ) ) {
		return;
	}

	$workflows = array(
		'Plugins' => array(
			'Tag: Auto-Bounces'              => array(
				'conditions' => array( array( rule( 'subject', 'Undelivered Mail', 'contains' ) ) ),
				'actions'    => array( array( rule( 'add_tag', 'auto-bounce' ) ) ),
			),
			'Tag: Guidelines'                => array(
				'conditions' => array( array( rule( 'subject', 'Report:', 'starts' ) ) ),
				'actions'    => array( array( rule( 'add_tag', 'guidelines' ) ) ),
			),
			'Review: Ownership Verified'     => array(
				'type'    => Workflow::TYPE_MANUAL,
				'actions' => array( array( message( 'note', 'Ownership verified against the plugin’s website.' ) ) ),
			),
			'Review: Ask others to continue' => array(
				'type'    => Workflow::TYPE_MANUAL,
				'actions' => array( array( message( 'note', 'Could someone else continue this review?' ), rule( 'assign', Conversation::USER_UNASSIGNED ) ) ),
			),
		),
		'Themes'  => array(
			'Tag: Reported Theme'              => array(
				'conditions' => array( array( rule( 'subject', 'Reported theme', 'starts' ) ) ),
				'actions'    => array( array( rule( 'add_tag', 'reported-theme' ) ) ),
			),
			'Auto-reply: Reported for Support' => array(
				'conditions' => array( array( rule( 'tag', 'support', 'equal' ) ) ),
				'actions'    => array( array( message( 'reply', 'This address is for guideline issues. Please ask in the theme’s support forum.' ) ) ),
			),
		),
	);

	foreach ( $workflows as $mailbox_name => $mailbox_workflows ) {
		$mailbox = mailbox( $mailbox_name );
		foreach ( $mailbox_workflows as $name => $values ) {
			if ( Workflow::where( 'mailbox_id', $mailbox->id )->where( 'name', $name )->exists() ) {
				continue;
			}

			$workflow             = new Workflow();
			$workflow->mailbox_id = $mailbox->id;
			$workflow->fill( $values + array( 'type' => Workflow::TYPE_AUTOMATIC ) );
			$workflow->name          = $name;
			$workflow->active        = true;
			$workflow->apply_to_prev = false;
			$workflow->setSortOrderLast();
			$workflow->checkComplete();
			$workflow->save();

			echo "Created workflow {$name}" . ( $workflow->active ? '' : ', but it isn’t complete' ) . "\n";
		}
	}
}

/**
 * Reports: works out response and resolution times for conversations it hasn't seen.
 *
 * Its scheduler task does the same within a minute; this way the reports are ready right away.
 *
 * @return void
 */
function seed_reports(): void {
	if ( ! class_exists( ReportsServiceProvider::class ) ) {
		return;
	}

	ReportsServiceProvider::collectData();
}

/**
 * API & Webhooks: an API key for the admin, and a webhook to the mock API.
 *
 * The webhook only fires for new conversations, and goes to the environment's mock API, which turns it away for its
 * signature. Its log shows the attempts.
 *
 * @return void
 */
function seed_api_webhooks(): void {
	if ( ! class_exists( ApiKey::class ) ) {
		return;
	}

	$admin = admin();
	if ( ! ApiKey::where( 'user_id', $admin->id )->where( 'name', 'Local sample key' )->exists() ) {
		// Its token isn't kept; create another key in the profile to call the API.
		ApiKey::generateFor( $admin->id, 'Local sample key', ApiKey::ABILITY_READ );
		echo "Created a read-only API key for {$admin->email}\n";
	}

	$url = 'http://mock-api:8080/sample-webhook';
	if ( ! Webhook::where( 'url', $url )->exists() ) {
		Webhook::create(
			array(
				'url'    => $url,
				'events' => array( 'convo.created' ),
			)
		);
		echo "Created a webhook to {$url}\n";
	}
}

/**
 * Adds everything.
 *
 * @return void
 */
function main(): void {
	require_local();

	seed_mailboxes();

	$mailboxes = array_map( __NAMESPACE__ . '\\mailbox', array_merge( array( 'Plugins', 'Themes' ), array_keys( mailbox_data() ) ) );

	seed_agents( $mailboxes );
	seed_customers();

	$created = count( array_filter( array_map( __NAMESPACE__ . '\\seed_conversation', conversation_data() ) ) );
	if ( $created ) {
		echo "Created {$created} sample conversations\n";
	}

	seed_tags();
	seed_custom_fields();
	seed_saved_replies();
	seed_teams();
	seed_custom_folders();
	seed_mentions();
	seed_workflows();
	seed_reports();
	seed_api_webhooks();

	foreach ( $mailboxes as $mailbox ) {
		$mailbox->updateFoldersCounters();
	}
}

main();
