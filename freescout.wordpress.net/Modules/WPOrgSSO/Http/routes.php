<?php
/**
 * WPOrgSSO routes.
 *
 * The ACS and metadata URLs are registered with login.wordpress.org; don't change them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgSSO\Http\Controllers',
	),
	static function (): void {
		Route::get( '/wporgsso/start', 'SsoController@start' )->middleware( 'guest' )->name( 'wporgsso.start' );
		Route::get( '/wporgsso/complete', 'SsoController@complete' )->middleware( 'guest' )->name( 'wporgsso.complete' );
		// Throttled, so it can't be used to go through WordPress.org accounts.
		Route::get( '/wporgsso/lookup', 'SsoController@lookup' )->middleware( array( 'auth', 'throttle:30,1' ) )->name( 'wporgsso.lookup' );
	}
);

// The identity provider posts cross-site: no session to start, and no CSRF token to check.
Route::group(
	array(
		'middleware' => array( 'open', 'throttle:60,1' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgSSO\Http\Controllers',
	),
	static function (): void {
		Route::post( '/wporgsso/acs', 'SsoController@acs' )->name( 'wporgsso.acs' );
		Route::get( '/wporgsso/metadata', 'SsoController@metadata' )->name( 'wporgsso.metadata' );
	}
);
