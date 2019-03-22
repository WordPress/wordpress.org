/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Split a string into an array with sprintf-style tokens as the delimiter.
 *
 * Including the entire match as a capture group causes the tokens to be included in the array
 * as separate items instead of being removed.
 *
 * This allows translated strings, which may contain tokens in different positions than they have
 * in English, to be manipulated, modified, and included as an array of child elements in a
 * React template.
 *
 * See also arrayTokenReplace
 *
 * Example:
 *
 *   tokenSplit( 'I accuse %1$s in the %2$s with the %3$s!' )
 *
 *   becomes
 *
 *   [ 'I accuse ', '%1$s', ' in the ', '%2$s', ' with the ', '%3$s', '!' ]
 *
 * @param {string} string
 *
 * @return {Array} The split string.
 */
export function tokenSplit( string ) {
	const regex = /(%[1-9]?\$?s)/;

	return string.split( regex );
}

/**
 * Replace array items that are sprintf-style tokens with argument values.
 *
 * This allows tokens to be replaced with complex objects such as React elements, instead of just strings.
 * This way, for example, a translation can include both plain strings and HTML and be inserted as an array
 * of child elements into a React template without having to use RawHTML.
 *
 * See also tokenSplit
 *
 * Example:
 *
 *   arrayTokenReplace(
 *       [ 'I accuse ', '%1$s', ' in the ', '%2$s', ' with the ', '%3$s', '!' ],
 *       [ 'Professor Plum', 'Conservatory', 'Wrench' ]
 *   )
 *
 *   becomes
 *
 *   [ 'I accuse ', 'Professor Plum', ' in the ', 'Conservatory', ' with the ', 'Wrench', '!' ]
 *
 * @param {Array} source
 * @param {Array} args
 *
 * @return {Array} Array with token items replaced.
 */
export function arrayTokenReplace( source, args ) {
	let specificArgIndex,
		nextArgIndex = 0;

	return source.flatMap( ( value ) => {
		const regex = /^%([1-9])?\$?s$/;
		const match = value.match( regex );

		if ( Array.isArray( match ) ) {
			if ( match.length > 1 && 'undefined' !== typeof match[ 1 ] ) {
				specificArgIndex = Number( match[ 1 ] ) - 1;

				if ( 'undefined' !== typeof args[ specificArgIndex ] ) {
					value = args[ specificArgIndex ];
				}
			} else {
				value = args[ nextArgIndex ];

				nextArgIndex++;
			}
		}

		return value;
	} );
}

/**
 * Insert a separator item in between each item in an array.
 *
 * See https://stackoverflow.com/a/23619085/402766
 *
 * @param {Array} array
 * @param {string} separator
 *
 * @return {Array} Array with separator items.
 */
export function intersperse( array, separator ) {
	if ( ! array.length ) {
		return [];
	}

	return array
		.slice( 1 )
		.reduce(
			( accumulator, curValue, curIndex ) => {
				const sep = ( typeof separator === 'function' ) ? sep( curIndex ) : separator;

				return accumulator.concat( [ sep, curValue ] );
			},
			[ array[ 0 ] ]
		);
}

/**
 * Add proper list grammar to an array of strings.
 *
 * Insert punctuation and conjunctions in between array items so that when it is joined into
 * a single string, it is a human-readable list.
 *
 * Example:
 *
 *   listify( [ '<em>apples</em>', '<strong>oranges</strong>', '<del>bananas</del>' ] )
 *
 *   becomes
 *
 *   [ '<em>apples</em>', ', ', '<strong>oranges</strong>', ', ', ' and ', '<del>bananas</del>' ]
 *
 *   so that when the array is joined, it becomes
 *
 *   '<em>apples</em>, <strong>oranges</strong>, and <del>bananas</del>'
 *
 * @param {Array} array
 *
 * @return {Array} Array with separator items.
 */
export function listify( array ) {
	let list = [];

	/* translators: used between list items, there is a space after the comma */
	const separator = __( ', ', 'wordcamporg' );
	/* translators: preceding the last item in a list, there are spaces on both sides */
	const conjunction = __( ' and ', 'wordcamporg' );

	if ( ! Array.isArray( array ) ) {
		return list;
	}

	const count = array.length;

	switch ( count ) {
		case 0:
			break;
		case 1:
			list = array;
			break;
		case 2:
			list = intersperse( array, conjunction );
			break;
		default:
			const [ last, ...initial ] = [ ...array ].reverse();

			list = intersperse( initial, separator ).concat( [ separator, conjunction, last ] );
			break;
	}

	return list;
}
