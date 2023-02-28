/**
 * WordPress dependencies
 */
import { apiFetch } from '@wordpress/data-controls';

/**
 * Get the action object signalling that patterns have been requested.
 *
 * @param {string} query Search string.
 *
 * @return {Object} Action object.
 */
export function fetchPatterns( query ) {
	return { type: 'FETCH_BLOCK_PATTERNS', query: query };
}

/**
 * Get the action object signalling that a singple pattern has been loaded.
 *
 * @param {number} postId  The pattern ID.
 * @param {Object} pattern The pattern as returned from the API.
 *
 * @return {Object} Action object.
 */
export function loadPattern( postId, pattern ) {
	return {
		type: 'LOAD_BLOCK_PATTERN',
		postId: postId,
		pattern: pattern,
	};
}

/**
 * Get the action object signalling that patterns have been loaded.
 *
 * @param {string} query               Search string.
 * @param {Object} response
 * @param {Array}  response.page       The current page.
 * @param {Array}  response.patterns   A list of patterns.
 * @param {number} response.total      The total number of patterns.
 * @param {number} response.totalPages The total number of pages.
 *
 * @return {Object} Action object.
 */
export function loadPatterns( query, { page, patterns, total, totalPages } ) {
	return {
		type: 'LOAD_BLOCK_PATTERNS',
		query: query,
		page: page,
		patterns: patterns,
		total: total,
		totalPages: totalPages,
	};
}

/**
 * Get the action object signalling that patterns have been loaded.
 *
 * @param {string} query          Search string.
 * @param {Object} response
 * @param {Array}  response.page  The current page.
 * @param {Object} response.error The error message, as reported from the API.
 *
 * @return {Object} Action object.
 */
export function setErrorPatterns( query, { page, error } ) {
	return {
		type: 'ERROR_BLOCK_PATTERNS',
		query: query,
		page: page,
		error: error,
	};
}

/**
 * Get the action object signalling that the current view has been set.
 *
 * @param {string} query Query object.
 * @return {Object} Action object.
 */
export function setCurrentQuery( query ) {
	return { type: 'SET_CURRENT_QUERY', query: query };
}

/**
 * Get the action object signalling that categories have been requested.
 *
 * @return {Object} Action object.
 */
export function fetchCategories() {
	return { type: 'FETCH_CATEGORIES' };
}

/**
 * Get the action object signalling that categories have been loaded.
 *
 * @param {Array} categories A list of categories.
 * @return {Object} Action object.
 */
export function loadCategories( categories ) {
	return { type: 'LOAD_CATEGORIES', categories: categories };
}

/**
 * Get the action object signalling that pattern flag reasons have been requested.
 *
 * @return {Object} Action object.
 */
export function fetchPatternFlagReasons() {
	return { type: 'FETCH_PATTERN_FLAG_REASONS' };
}

/**
 * Get the action object signalling that pattern flag reasons have been loaded.
 *
 * @param {Array} reasons A list of reasons.
 * @return {Object} Action object.
 */
export function loadPatternFlagReasons( reasons ) {
	return { type: 'LOAD_PATTERN_FLAG_REASONS', reasons: reasons };
}

/**
 * Get the action object signalling that the favorites list has been loaded.
 *
 * @param {number[]} patternIds A list of pattern IDs.
 *
 * @return {Object} Action object.
 */
export function loadFavorites( patternIds ) {
	return { type: 'LOAD_FAVORITES', patternIds: patternIds };
}

/**
 * Get the action object to favorite a pattern.
 *
 * @param {number} patternId The pattern to favorite.
 *
 * @return {Object|void} Action object.
 */
export function* addFavorite( patternId ) {
	const result = yield apiFetch( {
		path: '/wporg/v1/pattern-favorites',
		method: 'POST',
		data: { id: patternId },
	} );
	// Silently discarding any errors.
	if ( 'number' === typeof result ) {
		return { type: 'ADD_FAVORITE', patternId: patternId, count: result };
	}
}

/**
 * Get the action object to unfavorite a pattern.
 *
 * @param {number} patternId The pattern to unfavorite.
 *
 * @return {Object|void} Action object.
 */
export function* removeFavorite( patternId ) {
	const result = yield apiFetch( {
		path: '/wporg/v1/pattern-favorites',
		method: 'DELETE',
		data: { id: patternId },
	} );
	// Silently discarding any errors.
	if ( 'number' === typeof result ) {
		return { type: 'REMOVE_FAVORITE', patternId: patternId, count: result };
	}
}
