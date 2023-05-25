/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

/**
 * Update the breadcrumb text to the current page.
 *
 * @param {string} label
 */
const setBreadcrumbText = ( label ) => {
	const breadcrumb = document.getElementById( 'breadcrumb-part' );
	if ( breadcrumb ) {
		breadcrumb.innerText = label;
	}
};

const BreadcrumbMonitor = () => {
	const { path } = useRoute();

	const { authorName, categoryName } = useSelect( ( select ) => {
		const _query = select( patternStore ).getQueryFromUrl( path );
		const category = select( patternStore ).getCategoryById( _query[ 'pattern-categories' ] );
		return {
			authorName: wporgPatternsData.currentAuthorName || _query?.author_name,
			categoryName: category?.name,
		};
	}, [] );

	useEffect( () => {
		if ( authorName ) {
			// translators: %s is the author name.
			setBreadcrumbText( sprintf( __( 'Author: %s', 'wporg-patterns' ), authorName ) );
		} else if ( categoryName ) {
			// translators: %s is the category name.
			setBreadcrumbText( sprintf( __( 'Category: %s', 'wporg-patterns' ), categoryName ) );
		}
	}, [ path, authorName, categoryName ] );

	return null;
};

export default BreadcrumbMonitor;
