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
 * Get the action object signalling that patterns have been loaded.
 *
 * @param {string} query Search string.
 * @param {Array} patterns A list of patterns.
 *
 * @return {Object} Action object.
 */
export function loadPatterns( query, patterns ) {
	return { type: 'LOAD_BLOCK_PATTERNS', query: query, patterns: patterns };
}
