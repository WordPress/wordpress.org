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
		Route::get( '/wporgsidebar/sender-photo/{conversation_id}', 'PanelController@sender_photo' )
			->where( 'conversation_id', '[0-9]+' )
			->name( 'wporgsidebar.sender_photo' );

		Route::get( '/wporgsidebar/{conversation_id}/{panel}', 'PanelController@show' )
			->where( 'conversation_id', '[0-9]+' )
			->where( 'panel', '[a-z0-9-]+' )
			->name( 'wporgsidebar.panel' );
	}
);
