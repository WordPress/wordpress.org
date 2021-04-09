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
