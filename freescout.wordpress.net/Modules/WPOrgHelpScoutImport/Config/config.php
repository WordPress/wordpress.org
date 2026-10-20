<?php
/**
 * WPOrgHelpScoutImport module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

return array(
	'name'        => 'WPOrgHelpScoutImport',
	// A HelpScout app's credentials (Your Profile » My Apps). Without them, nothing can be imported.
	'app_id'      => env( 'WPORG_HELPSCOUT_APP_ID', '' ),
	'app_secret'  => env( 'WPORG_HELPSCOUT_APP_SECRET', '' ),
	'api_url'     => 'https://api.helpscout.net/',
	// Requests per minute left to HelpScout's other users: the whole account shares one rate limit.
	'reserve'     => 100,
	// Where HelpScout put images pasted into emails; they're copied, since they'd go with the account.
	'image_hosts' => array( 'd33v4339jhl8k0.cloudfront.net' ),
);
