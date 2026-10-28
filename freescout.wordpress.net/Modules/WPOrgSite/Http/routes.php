<?php
/**
 * WPOrgSite routes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web', 'auth' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgSite\Http\Controllers',
	),
	static function (): void {
		// `/conversation/{ID}` alone is core's route, handled by the RedirectHelpScoutLinks middleware.
		Route::get( '/conversation/{helpscout_id}/{number}', 'HelpScoutLinksController@show' )
			->where(
				array(
					'helpscout_id' => '[0-9]+',
					'number'       => '[0-9]+',
				)
			)
			->name( 'wporgsite.helpscout_link' );
	}
);
