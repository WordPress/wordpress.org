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
 * Retrieves the category from a url path without query string params.
 *
 * @param {string} path
 * @return {string} The category slug.
 */
export const getCategoryFromPath = ( path ) => {
	let _path = removeQueryString( path );
	_path = removeTrailingSlash( _path );

	return _path.substring( _path.lastIndexOf( '/' ) + 1 );
};
