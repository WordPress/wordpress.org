<?php
/**
 * WPOrgAkismet module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

return array(
	'name'    => 'WPOrgAkismet',

	// Without a key, nothing is checked.
	'key'     => env( 'WPORG_AKISMET_KEY', '' ),

	'api_url' => 'https://rest.akismet.com/1.1/',

	// Domains of WordPress.org's own mail servers, by reverse DNS: the sender's IP is that of the server before them.
	'relays'  => array( 'wordpress.org', 'w.org' ),
);
