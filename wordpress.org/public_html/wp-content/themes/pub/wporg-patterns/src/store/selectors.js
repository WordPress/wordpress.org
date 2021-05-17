/**
 * Internal dependencies
 */
import { getQueryString } from './utils';

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
	return ! Array.isArray( state.patterns.queries[ queryString ] );
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
	return ( state.patterns.queries[ queryString ] || [] ).map( ( id ) => state.patterns.byId[ id ] );
}

/**
 * Get a specific pattern.
 *
 * @param {Object} state Global application state.
 * @param {string} id Pattern ID.
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
 * @param {string} slug Category slug.
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
 * @param {Object} state Global application state.
 * @param {number} patternId The pattern to check.
 *
 * @return {number[]} A list of favorite pattern IDs.
 */
export function isFavorite( state, patternId ) {
	return state.favorites.includes( patternId );
}
