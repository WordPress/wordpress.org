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
		Route::get( '/freescout-import/find', 'ImportController@find' )->name( 'wporgfreescoutimport.find' );
		Route::post( '/freescout-import/sources', 'SourcesController@store' )->name( 'wporgfreescoutimport.sources.store' );
		Route::post( '/freescout-import/sources/{id}', 'SourcesController@update' )->name( 'wporgfreescoutimport.sources.update' );
		Route::post( '/freescout-import/sources/{id}/forget', 'SourcesController@forget' )->name( 'wporgfreescoutimport.sources.forget' );
		Route::post( '/freescout-import/sources/{id}/delete', 'SourcesController@delete' )->name( 'wporgfreescoutimport.sources.delete' );
		Route::post( '/freescout-import/sources/{id}/message-ids', 'SourcesController@message_ids' )->name( 'wporgfreescoutimport.message_ids' );
		Route::get( '/freescout-import/sources/{id}/users', 'UsersController@index' )->name( 'wporgfreescoutimport.users' );
		Route::post( '/freescout-import/sources/{id}/users', 'UsersController@save' )->name( 'wporgfreescoutimport.users.save' );
		Route::get( '/freescout-import/sources/{id}/users/export', 'UsersController@export' )->name( 'wporgfreescoutimport.users.export' );
		Route::post( '/freescout-import/sources/{id}/users/connect', 'UsersController@connect' )->name( 'wporgfreescoutimport.users.connect' );
		Route::post( '/freescout-import/sources/{id}/users/refresh', 'UsersController@refresh' )->name( 'wporgfreescoutimport.users.refresh' );
		Route::post( '/freescout-import/{id}/pause', 'ImportController@pause' )->name( 'wporgfreescoutimport.pause' );
		Route::post( '/freescout-import/{id}/resume', 'ImportController@resume' )->name( 'wporgfreescoutimport.resume' );
		Route::post( '/freescout-import/{id}/cancel', 'ImportController@cancel' )->name( 'wporgfreescoutimport.cancel' );
		Route::post( '/freescout-import/{id}/retry', 'ImportController@retry' )->name( 'wporgfreescoutimport.retry' );
	}
);
