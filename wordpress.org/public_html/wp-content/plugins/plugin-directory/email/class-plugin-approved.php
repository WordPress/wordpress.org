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
'Congratulations, your plugin hosting request for %1$s has been approved.

Within one (1) hour your account will be granted commit access to your Subversion (SVN) repository. Your username is %2$s and your password is the one you already use to log in to WordPress.org. Keep in mind, your username is case sensitive and you cannot use your email address to log in to SVN.

https://plugins.svn.wordpress.org/%3$s

Once your account has been added, you will need to upload your code using a SVN client of your choice. We are unable to upload or maintain your code for you.

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
