<?php
/**
 * WPOrgPluginReview module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

return array(
	'name'    => 'WPOrgPluginReview',

	// Base URL of the WordPress.org helpdesk endpoints, with a trailing slash.
	'api_url' => env( 'WPORG_API_URL', 'https://api.wordpress.org/dotorg/freescout/' ),

	// Shared secret used to sign requests; must match FREESCOUT_SECRET on api.wordpress.org.
	'secret'  => env( 'WPORG_API_SECRET', '' ),
);
