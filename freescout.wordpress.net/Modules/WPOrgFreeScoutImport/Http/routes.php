<?php
/**
 * WPOrgFreeScoutImport routes; administrators only.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web', 'auth', 'roles' ),
		'roles'      => array( 'admin' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgFreeScoutImport\Http\Controllers',
	),
	static function (): void {
		Route::get( '/freescout-import', 'ImportController@index' )->name( 'wporgfreescoutimport.index' );
		Route::post( '/freescout-import', 'ImportController@start' )->name( 'wporgfreescoutimport.start' );
		Route::post( '/freescout-import/settings', 'ImportController@settings' )->name( 'wporgfreescoutimport.settings' );
		Route::post( '/freescout-import/settings/forget', 'ImportController@forget' )->name( 'wporgfreescoutimport.settings.forget' );
		Route::post( '/freescout-import/message-ids', 'ImportController@message_ids' )->name( 'wporgfreescoutimport.message_ids' );
		Route::get( '/freescout-import/find', 'ImportController@find' )->name( 'wporgfreescoutimport.find' );
		Route::get( '/freescout-import/users', 'UsersController@index' )->name( 'wporgfreescoutimport.users' );
		Route::post( '/freescout-import/users', 'UsersController@save' )->name( 'wporgfreescoutimport.users.save' );
		Route::get( '/freescout-import/users/export', 'UsersController@export' )->name( 'wporgfreescoutimport.users.export' );
		Route::post( '/freescout-import/users/connect', 'UsersController@connect' )->name( 'wporgfreescoutimport.users.connect' );
		Route::post( '/freescout-import/users/refresh', 'UsersController@refresh' )->name( 'wporgfreescoutimport.users.refresh' );
		Route::post( '/freescout-import/{id}/pause', 'ImportController@pause' )->name( 'wporgfreescoutimport.pause' );
		Route::post( '/freescout-import/{id}/resume', 'ImportController@resume' )->name( 'wporgfreescoutimport.resume' );
		Route::post( '/freescout-import/{id}/cancel', 'ImportController@cancel' )->name( 'wporgfreescoutimport.cancel' );
		Route::post( '/freescout-import/{id}/retry', 'ImportController@retry' )->name( 'wporgfreescoutimport.retry' );
	}
);
