/**
 * WordPress dependencies
 */
import { _n, sprintf } from '@wordpress/i18n';
import { createInterpolateElement } from '@wordpress/element';

/**
 * Returns an object with extra information about the category.
 *
 * @param {number} count Number of patterns associated to the category.
 * @param {string} categoryName The category name.
 * @param {string|void} searchTerm The search term.
 * @return {Object}
 */
export const getContextMessage = ( count, categoryName, searchTerm ) => {
	let message = sprintf(
		/* translators: %1$d: number of patterns. %2$s category name. */
		_n( '%1$d <b>%2$s</b> pattern.', '%1$d <b>%2$s</b> patterns.', count, 'wporg-patterns' ),
		count,
		categoryName.toLowerCase(),
		'wporg-patterns'
	);

	if ( searchTerm ) {
		if ( categoryName !== 'All' ) {
			message = sprintf(
				/* translators: %1$d: number of patterns. %2$s category name. %3$s search term  */
				_n(
					'%1$d <b>%2$s</b> pattern matching "<b>%3$s</b>".',
					'%1$d <b>%2$s</b> patterns matching "<b>%3$s</b>".',
					count,
					'wporg-patterns'
				),
				count,
				categoryName.toLowerCase(),
				searchTerm,
				'wporg-patterns'
			);
		} else {
			message = sprintf(
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
			);
		}
	}

	return {
		message: createInterpolateElement( message, {
			b: <b />,
		} ),

		// TODO Fetch these from somewhere
		title: '',
		links: [],
	};
};
