<?php
/**
 * WPOrgPluginReview routes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web', 'auth' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgPluginReview\Http\Controllers',
	),
	static function (): void {
		Route::get( '/wporgpluginreview/{conversation_id}/plugin', 'PluginController@show' )
			->where( 'conversation_id', '[0-9]+' )
			->name( 'wporgpluginreview.plugin' );
	}
);
