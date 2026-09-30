<?php
/**
 * WPOrgSSO module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

return array(
	'name'           => 'WPOrgSSO',

	// Shared with WPOrgSidebar: base URL of the WordPress.org helpdesk endpoints, with a trailing slash.
	'api_url'        => env( 'WPORG_API_URL', 'https://api.wordpress.org/dotorg/freescout/' ),

	// Shared with WPOrgSidebar: must match FREESCOUT_SECRET on api.wordpress.org.
	'secret'         => env( 'WPORG_API_SECRET', '' ),

	// The WordPress.org SAML identity provider, as its settings page lists it.
	'idp'            => array(
		'entity_id' => env( 'WPORG_SSO_IDP_ENTITY_ID', 'https://login.wordpress.org' ),
		'url'       => env( 'WPORG_SSO_IDP_URL', 'https://login.wordpress.org/wp-login.php?action=idp' ),

		// Base64 body of its signing certificate, without the BEGIN/END lines.
		'cert'      => env( 'WPORG_SSO_IDP_CERT', '' ),
	),

	// Break-glass: lets administrators log in with their FreeScout password. Every such login is logged.
	'password_login' => filter_var( env( 'WPORG_SSO_PASSWORD_LOGIN', false ), FILTER_VALIDATE_BOOLEAN ),
);
