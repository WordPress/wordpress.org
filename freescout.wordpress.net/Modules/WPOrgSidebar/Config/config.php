<?php
/**
 * WPOrgSidebar module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

return array(
	'name'    => 'WPOrgSidebar',

	// Base URL of the WordPress.org helpdesk endpoints, with a trailing slash.
	'api_url' => env( 'WPORG_API_URL', 'https://api.wordpress.org/dotorg/freescout/' ),

	// Shared secret used to sign requests; must match FREESCOUT_SECRET on api.wordpress.org.
	'secret'  => env( 'WPORG_API_SECRET', '' ),

	// Customer sidebar panels, keyed by ID, rendered in this order.
	'panels'  => array(
		'profile'        => array(
			'title'    => 'WordPress.org',
			'endpoint' => 'profile.php',
		),
		'forums'         => array(
			'title'    => 'Forum Notes',
			'endpoint' => 'forums.php',
		),
		'plugins-themes' => array(
			'title'    => 'Plugins & Themes',
			'endpoint' => 'plugins-themes.php',
		),
		'dpo'            => array(
			'title'    => 'Privacy Requests',
			'endpoint' => 'dpo.php',
		),
	),
);
