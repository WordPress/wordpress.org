<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WordPressdotorg\Plugin_Directory\Tools;

class Plugin_Approved extends Base {
	protected $required_args = [];

	function subject() {
		return sprintf(
			/* translators: 1: Plugin Name */
			__( '%s has been approved!', 'wporg-plugins' ),
			$this->plugin->post_title
		);
	}

	function body() {
		/* translators: 1: plugin name, 2: plugin author's username, 3: plugin slug */
		$email_text = __(
'Congratulations, the plugin hosting request for %1$s has been approved.

Within one (1) hour your account (%2$s) will be granted commit access to your Subversion (SVN) repository.

* SVN URL: https://plugins.svn.wordpress.org/%3$s
* Public URL: https://wordpress.org/plugins/%3$s

Once your account access has been activated, you may upload your code using a SVN client of your choice. If you are new to SVN (or the Plugin Directory) make sure to review all the links in this email.

To answer some common questions:

* You must use SVN to upload your code -- we are unable to do that for you
* Your SVN username is %2$s and your password is the same as you use to log in to WordPress.org
* Your username is case sensitive
* SVN will not accept your email address as a username
* Due to the size of the directory, it may take 72 hours before all search results are properly updated

To help you get started, here are some links:

Using Subversion with the WordPress Plugin Directory:
https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

FAQ about the WordPress Plugin Directory:
https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

WordPress Plugin Directory readme.txt standard:
https://wordpress.org/plugins/developers/#readme

A readme.txt validator:
https://wordpress.org/plugins/developers/readme-validator/

Plugin Assets (header images, etc):
https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

WordPress Plugin Directory Guidelines:
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

Block Specific Plugin Guidelines:
https://developer.wordpress.org/plugins/wordpress-org/block-specific-plugin-guidelines/

If you have issues or questions, please reply to this email and let us know.

Enjoy!', 'wporg-plugins' );

		return sprintf(
			$email_text,
			$this->plugin->post_title,
			$this->user->user_login,
			$this->plugin->post_name
		);
	}
}
