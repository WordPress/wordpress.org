/**
 * WordPress dependencies
 */
import apiFetch         from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

export function fetchSessions() {
	return apiFetch( {
		path: addQueryArgs( `wp/v2/sessions`, {
			per_page: 100,
			status  : 'publish',
			_embed  : true,
		} ),
	} );
}

export function fetchTracks() {
	return apiFetch( {
		path: addQueryArgs( `wp/v2/session_track`, {
			per_page: 100,
			status  : 'publish',
		} ),
	} );
}

export function fetchPosts() {
	return apiFetch( {
		path: addQueryArgs( `wp/v2/posts`, {
			per_page: 3,
			status: 'publish',
			_embed: true,   // todo need embed here?
		} ),
	} );
}

// todo maybe don't need this file, just embed into maincontroller?
