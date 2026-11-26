<?php
/**
 * WPOrgApi routes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgApi
 */

declare( strict_types = 1 );

// Not in the web group: WordPress.org signs its requests, and has no session or CSRF token.
Route::group(
	array(
		'middleware' => array( 'throttle:60,1' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgApi\Http\Controllers',
	),
	static function (): void {
		Route::post( '/wporgapi/saved-reply', 'SavedReplyController@show' )->name( 'wporgapi.saved_reply' );
	}
);
