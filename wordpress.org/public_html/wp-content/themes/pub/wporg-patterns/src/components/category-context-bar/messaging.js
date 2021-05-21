/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Returns a message regarding current category filter status.
 *
 * @param {number} count Number of patterns associated to the current category.
 * @param {string} categoryName The category name.
 * @return {Object}
 */
export const getDefaultMessage = ( count, categoryName ) => {
	return createInterpolateElement(
		sprintf(
			/* translators: %1$d: number of patterns. %2$s category name. */
			_n( '%1$d <b>%2$s</b> pattern.', '%1$d <b>%2$s</b> patterns.', count, 'wporg-patterns' ),
			count,
			categoryName,
			'wporg-patterns'
		),
		{
			b: <b />,
		}
	);
};

/**
 * Returns a message regarding current loading status.
 *
 * @param {string} categoryName The category name.
 * @return {Object}
 */
export const getLoadingMessage = ( categoryName ) => {
	return createInterpolateElement(
		sprintf(
			/* translators: %1$d: number of patterns. %2$s category name. */
			__( 'Loading <b>%s</b> patterns.', 'wporg-patterns' ),
			categoryName,
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
 * @param {number} count Number of patterns associated to the category.
 * @param {string} categoryName The category name.
 * @param {string|void} searchTerm The search term.
 * @return {Object}
 */
export const getDefaultSearchMessage = ( count, categoryName, searchTerm ) => {
	return createInterpolateElement(
		sprintf(
			/* translators: %1$d: number of patterns. %2$s category name. %3$s search term  */
			_n(
				'%1$d <b>%2$s</b> pattern matching "<b>%3$s</b>".',
				'%1$d <b>%2$s</b> patterns matching "<b>%3$s</b>".',
				count,
				'wporg-patterns'
			),
			count,
			categoryName,
			searchTerm,
			'wporg-patterns'
		),
		{
			b: <b />,
		}
	);
};

/**
 * Returns a message regarding current search status for when no category is selected.
 *
 * @param {number} count Number of patterns associated to the category.
 * @param {string|void} searchTerm The search term.
 * @return {Object}
 */
export const getAllSearchMessage = ( count, searchTerm ) => {
	return createInterpolateElement(
		sprintf(
			/* translators: %1$d: number of patterns. %2$s search term.  */
			_n(
				'%1$d pattern matching "<b>%2$s</b>".',
				'%1$d patterns matching "<b>%2$s</b>".',
				count,
				'wporg-patterns'
			),
			count,
			searchTerm,
			'wporg-patterns'
		),
		{
			b: <b />,
		}
	);
};
