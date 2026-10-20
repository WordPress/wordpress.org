<?php
/**
 * WPOrgWebhooks module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

return array(
	'name'     => 'WPOrgWebhooks',

	// Shared with WPOrgSidebar: base URL of the WordPress.org helpdesk endpoints, with a trailing slash.
	'api_url'  => env( 'WPORG_API_URL', 'https://api.wordpress.org/dotorg/freescout/' ),

	// Shared with WPOrgSidebar: must match FREESCOUT_SECRET on api.wordpress.org.
	'secret'   => env( 'WPORG_API_SECRET', '' ),

	'endpoint' => 'webhook.php',
);
