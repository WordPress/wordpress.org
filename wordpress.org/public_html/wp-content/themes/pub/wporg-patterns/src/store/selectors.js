/**
 * WordPress dependencies
 */
import { buildQueryString, getQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { getQueryString } from './utils';
import { getCategoryFromPath, getPageFromPath, getSearchTermFromPath, getValueFromPath } from '../utils';

/**
 * Check if there is a pending request for the given pattern query.
 *
 * @param {Object} state Global application state.
 * @param {Object} query Query parameters.
 *
 * @return {boolean} True if an API request is in progress for this query.
 */
export function isLoadingPatternsByQuery( state, query ) {
	const queryString = getQueryString( query );
	const page = query?.page || 1;
	return ! Array.isArray( state.patterns.queries?.[ queryString ]?.[ page ] );
}

/**
 * Get all loaded patterns.
 *
 * @param {Object} state Global application state.
 *
 * @return {Array} A list of all patterns.
 */
export function getPatterns( state ) {
	return Object.values( state.patterns.byId );
}

/**
 * Get loaded patterns for a given query.
 *
 * @param {Object} state Global application state.
 * @param {Object} query Query parameters.
 *
 * @return {Array} A list of patterns matching this query.
 */
export function getPatternsByQuery( state, query ) {
	const queryString = getQueryString( query );
	const page = query?.page || 1;
	const patternIds = state.patterns.queries?.[ queryString ]?.[ page ];
	return ( patternIds || [] ).map( ( id ) => state.patterns.byId[ id ] );
}

/**
 * Get the count of all patterns for a given query.
 *
 * @param {Object} state Global application state.
 * @param {Object} query Query parameters.
 *
 * @return {number} The count of all patterns matching this query.
 */
export function getPatternTotalsByQuery( state, query ) {
	const queryString = getQueryString( query );
	return state.patterns.queries?.[ queryString ]?.total || 0;
}

/**
 * Get the number of pages of patterns for a given query.
 *
 * @param {Object} state Global application state.
 * @param {Object} query Query parameters.
 *
 * @return {number} The count of pages.
 */
export function getPatternTotalPagesByQuery( state, query ) {
	const queryString = getQueryString( query );
	return state.patterns.queries?.[ queryString ]?.totalPages || 0;
}

/**
 * Get a specific pattern.
 *
 * @param {Object} state Global application state.
 * @param {string} id    Pattern ID.
 *
 * @return {Object} The requested pattern, if loaded.
 */
export function getPattern( state, id ) {
	return state.patterns.byId[ id ] || null;
}

/**
 * Get the current query.
 *
 * @param {Object} state Global application state.
 *
 * @return {Object} The current query.
 */
export function getCurrentQuery( state ) {
	return state.currentQuery;
}

/**
 * Check if there is a pending request for category query.
 *
 * @param {Object} state Global application state.
 *
 * @return {boolean} True if an API request is in progress for this query.
 */
export function isLoadingCategories( state ) {
	return state.categories === null;
}

/**
 * Check if the categories have been loaded.
 *
 * @param {Object} state Global application state.
 *
 * @return {boolean} True if categories is an array.
 */
export function hasLoadedCategories( state ) {
	return Array.isArray( state.categories );
}

/**
 * Get all loaded categories.
 *
 * @param {Object} state Global application state.
 *
 * @return {Array} A list of all categories.
 */
export function getCategories( state ) {
	return state.categories;
}

/**
 * Get category by its slug.
 *
 * @param {Object} state Global application state.
 * @param {string} slug  Category slug.
 *
 * @return {Array|undefined} The requested category.
 */
export function getCategoryBySlug( state, slug ) {
	if ( ! hasLoadedCategories( state ) ) {
		return;
	}

	const [ cat ] = state.categories.filter( ( i ) => i.slug === slug );
	return cat;
}

/**
 * Get category by its term ID.
 *
 * @param {Object} state  Global application state.
 * @param {number} termId Term ID.
 *
 * @return {Array|undefined} The requested category.
 */
export function getCategoryById( state, termId ) {
	if ( ! hasLoadedCategories( state ) ) {
		return;
	}

	const term = state.categories.find( ( { id } ) => termId === id );
	return term;
}

/**
 * Get pattern flag reasons.
 *
 * @param {Object} state Global application state.
 *
 * @return {Array} A list of pattern flag reasons.
 */
export function getPatternFlagReasons( state ) {
	return state.patternFlagReasons;
}

/**
 * Check if pattern flag reasons are loading.
 *
 * @param {Object} state Global application state.
 *
 * @return {Array} A list of pattern flag reasons.
 */
export function isLoadingPatternFlagReasons( state ) {
	return state.patternFlagReasons === null;
}

/**
 * Get the list of favorites.
 *
 * @param {Object} state Global application state.
 *
 * @return {number[]} A list of favorite pattern IDs.
 */
export function getFavorites( state ) {
	return state.favorites;
}

/**
 * Check if a pattern ID is in the list of favorites.
 *
 * @param {Object} state     Global application state.
 * @param {number} patternId The pattern to check.
 *
 * @return {number[]} A list of favorite pattern IDs.
 */
export function isFavorite( state, patternId ) {
	return state.favorites?.includes( patternId );
}

/**
 * Parse the query from the given URL or path.
 *
 * @param {Object} state Global application state.
 * @param {string} url   A URL or path, optionally with query string.
 *
 * @return {Object} The query to use when requesting content from the API.
 */
export function getQueryFromUrl( state, url ) {
	const params = [ 'categories', 'author', 'page', 'search' ];
	const query = getQueryArgs( url );

	const categorySlug = getCategoryFromPath( url );
	if ( categorySlug && -1 === params.indexOf( categorySlug ) ) {
		const term = getCategoryBySlug( state, categorySlug );
		if ( term?.id ) {
			query[ 'pattern-categories' ] = term.id;
		}
	}

	const author = getValueFromPath( url, 'author' );
	if ( author && -1 === params.indexOf( author ) ) {
		query.author_name = author;
	}

	const page = getPageFromPath( url );
	if ( 'number' === typeof page && page > 1 ) {
		query.page = page;
	}

	const search = getSearchTermFromPath( url );
	if ( search.length > 0 && -1 === params.indexOf( search ) ) {
		query.search = search;
	}

	const myPatternStatus = getValueFromPath( url, 'my-patterns' );
	if ( myPatternStatus && 'page' !== myPatternStatus ) {
		query.status = myPatternStatus;
	}

	return query;
}

/**
 * Convert a query object back into the URL for that request.
 *
 * @param {Object} state   Global application state.
 * @param {Object} query   A query object.
 * @param {string} baseUrl The URL to build off, defaults to the global site home.
 *
 * @return {string} The URL representing that query object.
 */
export function getUrlFromQuery( state, query = {}, baseUrl = wporgPatternsUrl.site ) {
	baseUrl = baseUrl.replace( /\/$/, '' );

	if ( query.author_name ) {
		baseUrl += `/author/${ query.author_name }`;
		delete query.author_name;
	}

	if ( query[ 'pattern-categories' ] ) {
		const termId = query[ 'pattern-categories' ];
		const categories = getCategories( state );
		const term = categories.find( ( { id } ) => termId === id );
		if ( term?.slug ) {
			baseUrl += `/categories/${ term.slug }`;
		}
		delete query[ 'pattern-categories' ];
	}

	if ( query.page ) {
		baseUrl += `/page/${ query.page }`;
		delete query.page;
	}

	if ( Object.keys( query ).length ) {
		baseUrl += '/?' + buildQueryString( query );
		return baseUrl;
	}

	return baseUrl + '/';
}
