<?php
/**
 * WPOrgApi module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgApi
 */

declare( strict_types = 1 );

return array(
	'name'   => 'WPOrgApi',

	// Shared with WPOrgSidebar and WPOrgWebhooks: must match FREESCOUT_SECRET on WordPress.org.
	'secret' => env( 'WPORG_API_SECRET', '' ),
);
