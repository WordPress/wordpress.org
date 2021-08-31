/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Returns a message regarding current loading status.
 *
 * @param {Object} data          Message properties.
 * @param {string} data.category The category name.
 * @param {string} data.author   The author's name.
 *
 * @return {Object}
 */
export const getLoadingMessage = ( { category, author } ) => {
	if ( category && author ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %1$s category name, %2$s author name. */
				__( 'Loading <b>%1$s</b> patterns by %2$s.', 'wporg-patterns' ),
				category,
				author
			),
			{
				b: <b />,
			}
		);
	} else if ( category ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %s category name. */
				__( 'Loading <b>%s</b> patterns.', 'wporg-patterns' ),
				category
			),
			{
				b: <b />,
			}
		);
	} else if ( author ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %s author name. */
				__( 'Loading patterns by <b>%s</b>.', 'wporg-patterns' ),
				author
			),
			{
				b: <b />,
			}
		);
	}
	return __( 'Loading patterns', 'wporg-patterns' );
};

/**
 * Returns a message regarding the current filter status.
 *
 * @param {Object} data          Message properties.
 * @param {string} data.category The category name.
 * @param {string} data.author   The author's name.
 * @param {number} count         Number of patterns associated to the current category.
 *
 * @return {Object}
 */
export const getMessage = ( { category, author }, count ) => {
	if ( category && author ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %1$d: number of patterns, %2$s category name, %3$s author name. */
				_n(
					'%1$d <b>%2$s</b> pattern by %3$s.',
					'%1$d <b>%2$s</b> patterns by %3$s.',
					count,
					'wporg-patterns'
				),
				count,
				category,
				author
			),
			{
				b: <b />,
			}
		);
	} else if ( category ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %1$d: number of patterns, %2$s category name. */
				_n( '%1$d <b>%2$s</b> pattern.', '%1$d <b>%2$s</b> patterns.', count, 'wporg-patterns' ),
				count,
				category,
				'wporg-patterns'
			),
			{
				b: <b />,
			}
		);
	} else if ( author ) {
		return createInterpolateElement(
			sprintf(
				/* translators: %1$d: number of patterns, %2$s author name. */
				_n( '%1$d pattern by <b>%2$s</b>.', '%1$d patterns by <b>%2$s</b>.', count, 'wporg-patterns' ),
				count,
				author
			),
			{
				b: <b />,
			}
		);
	}
	return __( 'Loading patterns', 'wporg-patterns' );
};

/**
 * Returns a message regarding current search status.
 *
 * @param {number}      count      Number of patterns associated to the category.
 * @param {string|void} searchTerm The search term.
 * @return {Object}
 */
export const getSearchMessage = ( count, searchTerm ) => {
	return createInterpolateElement(
		sprintf(
			/* translators: %1$d: number of patterns. %2$s search term.  */
			_n(
				'%1$d pattern found for <b>%2$s</b>',
				'%1$d patterns found for <b>%2$s</b>',
				count,
				'wporg-patterns'
			),
			count,
			searchTerm.replace( /\+/g, ' ' ),
			'wporg-patterns'
		),
		{
			b: <b />,
		}
	);
};

/**
 * Returns a message regarding current search status.
 *
 * @param {number} page       Current page number.
 * @param {number} totalPages Total number of pages.
 * @return {string}
 */
export const getPageLabel = ( page = 1, totalPages = 1 ) => {
	if ( 1 === totalPages ) {
		return '';
	}
	/* translators: %1$d: current page. %2$d: total number of pages.  */
	return sprintf( __( 'Page %1$d of %2$d.', 'wporg-patterns' ), page, totalPages );
};
