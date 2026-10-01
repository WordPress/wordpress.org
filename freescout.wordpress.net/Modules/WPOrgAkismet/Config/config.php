<?php
/**
 * WPOrgAkismet module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

return array(
	'name'      => 'WPOrgAkismet',

	// Without a key, nothing is checked.
	'key'       => env( 'WPORG_AKISMET_KEY', '' ),

	// Off, Akismet's verdicts are only recorded, so they can be compared with what agents mark first.
	'mark_spam' => filter_var( env( 'WPORG_AKISMET_MARK_SPAM', false ), FILTER_VALIDATE_BOOLEAN ),

	'api_url'   => 'https://rest.akismet.com/1.1/',
);
