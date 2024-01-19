<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WP_User;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

class Plugin_Submission extends Markdown_Base {
	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( 'Successful Plugin Submission - %s', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	/**
	 * The HTML content for the email template
	 */
	public function markdown() {

		$placeholders = [
			'###NAME###' => $this->plugin->post_title,
			'###SLUG###' => $this->plugin->post_name,
		];

		/* translators: This is Markdown, please be extra careful to retain the formatting characters, including newlines, and the placeholders. */
		$template = __( "Thank you for uploading ###NAME### to the WordPress Plugin Directory. We will review your submission as soon as possible and send you a follow up email with the results.

**Slug / Permalink**

Based on your display name of ###NAME### your plugin has been initially assigned the following permalink:
https://wordpress.org/plugins/###SLUG###/ _(please note: This URL will not be operational until your plugin review is approved)_

You can customize this on the [Plugin Submission page](https://wordpress.org/plugins/developers/add/), if you were unable to do this, please reply with the details of what you would like your plugin slug to be.
Once your plugin is approved, _we cannot change it_. If the slug listed [on the submission page](https://wordpress.org/plugins/developers/add/) is incorrect, please tell us as soon as possible; if that's correct, you do not need to contact us.
Your plugin slug / permalink is subject to change once a human review is completed.

**Review Process**

Our volunteer team of reviewers endeavors to perform all reviews in a timely manner, we are unable to provide an exact date of when your plugin will be reviewed.

We encourage all plugin authors to use [Plugin Check](https://wordpress.org/plugins/plugin-check/) to ensure that most basic issues are resolved first.
Please note: Automated tools may have false positives, or may miss issues. Plugin Check and other tools cannot guarantee our reviewers won’t find an issue that requires fixing or clarification.

Please refer to the [Plugin Submission page](https://wordpress.org/plugins/developers/add/) for:

* Up-to-date details of the currently review queue length and estimated times.
* Upload a new version of the plugin: In case you wish to update the files of your plugin submission before we begin with the review.

**Guidelines**

All plugins are required to abide by our Plugin Guidelines. Please ensure you have read and understand these.
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

A common issue encountered by plugin authors is that we require your accounts email address to remain operational, not to have autoresponders, and not mark our emails as spam.
To reduce the possibility of this affecting you, please ensure you add `plugins@wordpress.org` to your address book / contact list.
If you use an email forwarder, alias, or group mailing list, please ensure that address is added to the Allow list.

**Other Questions or Concerns?**

Please refer to our Frequently Asked Questions list here:
https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

If your question is not covered by this email, the submission page, or the above FAQ, please respond to this email with your question.

**Additionally...**

Please make sure to follow our official blog, for updates related to the team and review requirement changes:
https://make.wordpress.org/plugins/

Reviews are currently in English only. We apologize for the inconvenience.

If you have no questions, concerns, or comments, please do not reply to this email.", 'wporg-plugins' );

		$template = str_replace(
			array_keys( $placeholders ),
			array_values( $placeholders ),
			$template
		);

		/*
		 * Localise the WordPress.org hostname, if we're on a non-wordpress.org host.
		 * This ensures that a submission from `de.wordpress.org/plugins` which results in a german translated message
		 * retains links to the german-facing pages.
		 *
		 * NOTE: {make,developer}.wordpress.org remains unchanged, as it is not localised.
		 *
		 * TODO: This may be better done by re-using a Rosetta-provided function.
		 */
		if ( 'wordpress.org' !== $_SERVER['HTTP_HOST'] ) {
			$template = str_replace( '://wordpress.org/', '://' . $_SERVER['HTTP_HOST'] . '/', $template );
		}

		return $template;
	}
}
