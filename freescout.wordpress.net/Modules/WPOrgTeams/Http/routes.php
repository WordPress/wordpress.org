<?php
/**
 * WPOrgTeams routes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

Route::group(
	array(
		'middleware' => array( 'web', 'auth' ),
		'prefix'     => \Helper::getSubdirectory(),
		'namespace'  => 'Modules\WPOrgTeams\Http\Controllers',
	),
	static function (): void {
		Route::get( '/users/teams/{id}', 'UserTeamsController@show' )->where( 'id', '[0-9]+' )->name( 'wporgteams.user' );
		Route::post( '/users/teams/{id}', 'UserTeamsController@save' )->where( 'id', '[0-9]+' )->name( 'wporgteams.user.save' );
	}
);
