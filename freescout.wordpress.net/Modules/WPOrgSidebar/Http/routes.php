<?php
/**
 * WPOrgSidebar routes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web', 'auth' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgSidebar\Http\Controllers',
	),
	static function (): void {
		// Each request holds a worker while api.wordpress.org answers.
		Route::get( '/wporgsidebar/{conversation_id}/{panel}', 'PanelController@show' )
			->middleware( 'throttle:120,1' )
			->where( 'conversation_id', '[0-9]+' )
			->where( 'panel', '[a-z0-9-]+' )
			->name( 'wporgsidebar.panel' );
	}
);
