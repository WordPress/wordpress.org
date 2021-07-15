/**
 * WordPress dependencies
 */
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Convert a query object into a standardized string.
 * See the `stableKey` generation in `getQueryParts`
 * https://github.com/WordPress/gutenberg/blob/e12881c79441ca874fb2b2c2adffba8ed0792103/packages/core-data/src/queried-data/get-query-parts.js#L34
 *
 * @param {Object} query Query parameters.
 *
 * @return {string} A string which can be used to key the patterns state.
 */
export function getQueryString( query = {} ) {
	// Ensure stable key by sorting keys. Also more efficient for iterating.
	const keys = Object.keys( query ).sort();
	let stableKey = '';

	for ( let i = 0; i < keys.length; i++ ) {
		const key = keys[ i ];
		let value = query[ key ];
		if ( Array.isArray( value ) ) {
			value = query[ key ].join();
		}

		switch ( key ) {
			// These keys are excluded from the stableKey.
			case 'page':
			case 'per_page':
			case '_fields':
				break;

			default:
				stableKey += ( stableKey ? '&' : '' ) + addQueryArgs( '', { [ key ]: value } ).slice( 1 );
		}
	}

	return stableKey;
}

/**
 * Get the first category used to display all patterns.
 *
 * See Schema:
 * https://developer.wordpress.org/rest-api/reference/categories/
 *
 * @return {Object} A category object
 */
export function getAllCategory() {
	return {
		id: -1,
		slug: '', // Slug matches url
		name: __( 'All', 'wporg-patterns' ),
		link: wporgPatternsUrl.site,
	};
}
