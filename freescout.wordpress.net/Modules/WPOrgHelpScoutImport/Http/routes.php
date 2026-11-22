<?php
/**
 * WPOrgHelpScoutImport routes; administrators only.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web', 'auth', 'roles' ),
		'roles'      => array( 'admin' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgHelpScoutImport\Http\Controllers',
	),
	static function (): void {
		Route::get( '/helpscout-import', 'ImportController@index' )->name( 'wporghelpscoutimport.index' );
		Route::post( '/helpscout-import', 'ImportController@start' )->name( 'wporghelpscoutimport.start' );
		Route::get( '/helpscout-import/users', 'AgentsController@index' )->name( 'wporghelpscoutimport.agents' );
		Route::post( '/helpscout-import/users', 'AgentsController@save' )->name( 'wporghelpscoutimport.agents.save' );
		Route::post( '/helpscout-import/users/refresh', 'AgentsController@refresh' )->name( 'wporghelpscoutimport.agents.refresh' );
		Route::get( '/helpscout-import/users/export', 'AgentsController@export' )->name( 'wporghelpscoutimport.agents.export' );
		Route::post( '/helpscout-import/users/connect', 'AgentsController@connect' )->name( 'wporghelpscoutimport.agents.connect' );
		Route::post( '/helpscout-import/copies', 'ImportController@point' )->name( 'wporghelpscoutimport.copies' );
		Route::post( '/helpscout-import/{id}/pause', 'ImportController@pause' )->name( 'wporghelpscoutimport.pause' );
		Route::post( '/helpscout-import/{id}/resume', 'ImportController@resume' )->name( 'wporghelpscoutimport.resume' );
		Route::post( '/helpscout-import/{id}/cancel', 'ImportController@cancel' )->name( 'wporghelpscoutimport.cancel' );
		Route::post( '/helpscout-import/{id}/retry', 'ImportController@retry' )->name( 'wporghelpscoutimport.retry' );
	}
);
