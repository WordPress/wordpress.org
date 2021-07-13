/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';
import { getAllCategory } from '../../store/utils';
import { useRoute } from '../../hooks';
import { getCategoryFromPath, getValueFromPath } from '../../utils';

/**
 * Update the breadcrumb text to the category name.
 *
 * @param {string} categoryName
 */
const setBreadcrumbText = ( categoryName ) => {
	const breadcrumb = document.getElementById( 'breadcrumb-part' );
	if ( breadcrumb ) {
		// translators: %s is the category name.
		const label = sprintf( __( 'Category: %s', 'wporg-patterns' ), categoryName );

		breadcrumb.innerText = label;
	}
};

const BreadcrumbMonitor = () => {
	const { path } = useRoute();

	const categorySlug = getCategoryFromPath( path );
	const categoryName = useSelect(
		( select ) => {
			const { getCategoryBySlug, hasLoadedCategories } = select( patternStore );
			if ( hasLoadedCategories() ) {
				return getCategoryBySlug( categorySlug )?.name || getAllCategory().name;
			}
		},
		[ categorySlug ]
	);

	const author = getValueFromPath( path, 'author' );

	useEffect( () => {
		// `author` is currently unique in that it uses the default `Patterns` component.
		// We don't want to update it in that case.
		// For now we'll exclude it but a more reliable solution will be needed for:
		// https://github.com/WordPress/pattern-directory/issues/268
		if ( categoryName && ! author ) {
			setBreadcrumbText( categoryName );
		}
	}, [ path ] );

	return null;
};

export default BreadcrumbMonitor;
