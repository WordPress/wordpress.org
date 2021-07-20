/**
 * WordPress dependencies
 */
import { getQueryArgs } from '@wordpress/url';

/**
 * Remove the last '/' from a string if it exists.
 *
 * @param {string} str Url or url part
 * @return {string}
 */
const removeTrailingSlash = ( str ) => {
	return str.replace( /\/$/, '' );
};

/**
 * Remove the first '/' from a string if it exists.
 *
 * @param {string} str Url or url part
 * @return {string}
 */
const removeLeadingSlash = ( str ) => {
	return str.replace( /^\//, '' );
};

/**
 * Removes any empty properties or empty strings.
 *
 * @param {Object} obj
 * @return {Object} Object with keys that are defined and not empty
 */
export const removeEmptyArgs = ( obj ) => {
	const cleaned = {};

	Object.keys( obj ).forEach( ( key ) => {
		const arg = obj[ key ];

		// If it's not undefined or null convert it to string and check that it is not empty.
		// It's a bit of a "trick" to handle non-strings and strings without checking type explicitly.
		if ( arg !== undefined && arg !== null && arg.toString().length > 0 ) {
			cleaned[ key ] = arg;
		}
	} );

	return cleaned;
};

/**
 * Splits a string at ? and returns the first part.
 *
 * @param {string} path Url or url part
 * @return {string} Returns string or string before ? character.
 */
export const removeQueryString = ( path ) => {
	return path.split( '?' )[ 0 ];
};

/**
 * Retrieve a given key from a url path.
 * A query string will take precedence, otherwise will fall back to the value in the path, for example
 * in '/category/blog/page/2', the value for category is blog, and page is 2. This could also be written
 * as `?category=blog&page=2`, or `/category/blog/?page=2`.
 *
 * @param {string} path A URL path and query string.
 * @param {string} key  The query var to extract, ex: `categories`, `page`.
 * @return {string} The value of the requested key.
 */
export const getValueFromPath = ( path, key ) => {
	if ( ! key || ! path ) {
		return '';
	}
	const query = getQueryArgs( path );
	if ( query[ key ] ) {
		return query[ key ];
	}

	const _path = removeLeadingSlash( removeTrailingSlash( removeQueryString( path ) ) );
	const parts = _path.split( '/' );
	// Find the key section, if it exists. The next part of the URL is the value.
	const index = parts.indexOf( key );
	if ( -1 === index ) {
		return '';
	}

	return parts[ index + 1 ] || '';
};

/**
 * Retrieve the category from a url path.
 *
 * @param {string} path
 * @return {string} The category slug.
 */
export const getCategoryFromPath = ( path ) => {
	return getValueFromPath( path, 'categories' );
};

/**
 * Retrieve the page from a url path.
 *
 * @param {string} path
 * @return {number} The page number.
 */
export const getPageFromPath = ( path ) => {
	return Number( getValueFromPath( path, 'page' ) || 1 );
};

/**
 * Retrieve the search term from a url path.
 *
 * @param {string} path
 * @return {string} The search term.
 */
export const getSearchTermFromPath = ( path ) => {
	return decodeURI( getValueFromPath( path, 'search' ) );
};
