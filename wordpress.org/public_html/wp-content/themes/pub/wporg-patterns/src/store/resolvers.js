/**
 * WordPress dependencies
 */
import { addQueryArgs } from '@wordpress/url';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __unstableAwaitPromise, apiFetch } from '@wordpress/data-controls';

/**
 * Internal dependencies
 */
import {
	fetchCategories,
	fetchPatternFlagReasons,
	fetchPatterns,
	loadCategories,
	loadFavorites,
	loadPattern,
	loadPatternFlagReasons,
	loadPatterns,
	setErrorPatterns,
} from './actions';
import { getQueryString } from './utils';

async function parseResponse( response ) {
	try {
		return {
			total: Number( response.headers?.get( 'X-WP-Total' ) || 0 ),
			totalPages: Number( response.headers?.get( 'X-WP-TotalPages' ) || 0 ),
			results: await response.json(),
		};
	} catch ( error ) {
		return {};
	}
}

async function parseError( response ) {
	try {
		return await response.json();
	} catch ( error ) {
		return {};
	}
}

export function* getPatternsByQuery( query ) {
	const queryString = getQueryString( query );
	try {
		yield fetchPatterns( queryString );
		const response = yield apiFetch( {
			path: addQueryArgs( '/wp/v2/wporg-pattern', { ...query, locale: wporgLocale.id } ),
			parse: false,
		} );
		const { total, totalPages, results } = yield __unstableAwaitPromise( parseResponse( response ) );
		yield loadPatterns( queryString, {
			page: query.page || 1,
			patterns: results,
			total: total,
			totalPages: totalPages,
		} );
	} catch ( error ) {
		const parsedError = yield __unstableAwaitPromise( parseError( error ) );
		// @todo Do something with this error message.
		yield setErrorPatterns( queryString, {
			page: query.page || 1,
			error: parsedError,
		} );
	}
}

export function* getPattern( postId ) {
	try {
		const pattern = yield apiFetch( {
			path: addQueryArgs( `/wp/v2/wporg-pattern/${ postId }` ),
		} );
		yield loadPattern( postId, pattern );
	} catch ( error ) {}
}

export function* getCategories() {
	try {
		yield fetchCategories();
		const results = yield apiFetch( {
			path: addQueryArgs( '/wp/v2/pattern-categories', { per_page: 50 } ),
		} );
		yield loadCategories( results );
	} catch ( error ) {}
}

export function* getPatternFlagReasons() {
	try {
		yield fetchPatternFlagReasons();

		const results = yield apiFetch( {
			path: addQueryArgs( '/wp/v2/wporg-pattern-flag-reason' ),
		} );
		yield loadPatternFlagReasons( results );
	} catch ( error ) {}
}

export function* getFavorites() {
	try {
		const results = yield apiFetch( {
			path: '/wporg/v1/pattern-favorites',
		} );
		yield loadFavorites( results );
	} catch ( error ) {}
}
