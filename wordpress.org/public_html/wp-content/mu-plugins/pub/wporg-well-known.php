<?php
namespace WordPressdotorg\WellKnown;
/**
 * Plugin Name: WordPress.org /.well-known/ files.
 */

if ( empty( $_SERVER['REQUEST_URI'] ) ) {
	return;
}

add_action( 'init', function() {
	if (
		'/.well-known/security.txt' === $_SERVER['REQUEST_URI'] ||
		'/security.txt' === $_SERVER['REQUEST_URI']
	) {
		security_txt();
		exit;
	}

	if ( '/.well-known/change-password' === $_SERVER['REQUEST_URI'] ) {
		wp_safe_redirect( 'https://wordpress.org/support/users/profile/edit/' );
		exit;
	}
} );

function security_txt() {
	header( 'Content-Type: text/plain')
	?>
Contact: https://hackerone.com/wordpress
Expires: 2024-12-31T15:00:00.000Z
Acknowledgments: https://hackerone.com/wordpress/thanks
Canonical: https://wordpress.org/.well-known/security.txt
Policy: https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/

# The above contact is for reporting security issues in core WordPress software itself.
# For reporting issues in a plugin hosted at wordpress.org, contact plugins@wordpress.org 
# If your website is hacked, please contact your site administrator or hosting provider.
# Additionally, community support forums are a good resource at https://wordpress.org/support/
<?php
	exit;
}