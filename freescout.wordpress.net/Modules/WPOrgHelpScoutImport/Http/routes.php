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
		Route::get( '/helpscout-import/agents', 'AgentsController@index' )->name( 'wporghelpscoutimport.agents' );
		Route::post( '/helpscout-import/agents', 'AgentsController@save' )->name( 'wporghelpscoutimport.agents.save' );
		Route::post( '/helpscout-import/agents/refresh', 'AgentsController@refresh' )->name( 'wporghelpscoutimport.agents.refresh' );
		Route::post( '/helpscout-import/{id}/pause', 'ImportController@pause' )->name( 'wporghelpscoutimport.pause' );
		Route::post( '/helpscout-import/{id}/resume', 'ImportController@resume' )->name( 'wporghelpscoutimport.resume' );
	}
);
