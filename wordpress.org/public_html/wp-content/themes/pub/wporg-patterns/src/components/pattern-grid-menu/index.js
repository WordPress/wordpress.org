/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import ContextBar from '../context-bar';
import PatternOrderSelect from '../pattern-order-select';
import Menu from '../menu';
import NavigationLayout from '../navigation-layout';
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

const ALLOWED_CATS = [
	'', // All
	'featured',
	'query', // Posts
	'text',
	'gallery',
	'call-to-action',
	'banner',
	'header',
	'footer',
	'wireframe',
];

const PatternGridMenu = ( { basePath = '', onNavigation, ...props } ) => {
	const { path, update: updatePath } = useRoute();
	const { categorySlug, isLoading, options } = useSelect( ( select ) => {
		const { getCategoryById, getCategories, getQueryFromUrl, getUrlFromQuery, isLoadingCategories } =
			select( patternStore );
		const query = getQueryFromUrl( path );
		// Remove pagination, so we don't go from /page/2/ to /categories/images/page/2/.
		delete query.page;
		const allCategories = getCategories() || [];
		const _options = ALLOWED_CATS.map( ( slug ) => {
			const category = allCategories.find( ( cat ) => cat.slug === slug );
			if ( ! category ) {
				return false;
			}
			return {
				value: getUrlFromQuery(
					{ ...query, 'pattern-categories': category.id },
					wporgPatternsUrl.site + basePath
				),
				slug: category.slug,
				label: category.name,
			};
		} ).filter( Boolean );

		return {
			categorySlug: getCategoryById( query[ 'pattern-categories' ] )?.slug || '',
			isLoading: isLoadingCategories(),
			options: _options,
		};
	} );

	return (
		<>
			<NavigationLayout
				primary={
					<Menu
						current={ categorySlug }
						options={ options || [] }
						onClick={ ( event ) => {
							event.preventDefault();
							updatePath( event.target.pathname );
							if ( 'function' === typeof onNavigation ) {
								onNavigation();
							}
						} }
						isLoading={ isLoading }
					/>
				}
				secondary={
					<PatternOrderSelect
						options={ [
							{ label: __( 'Newest', 'wporg-patterns' ), value: 'date' },
							{ label: __( 'Popular', 'wporg-patterns' ), value: 'favorite_count' },
						] }
					/>
				}
			/>
			<ContextBar { ...props } />
		</>
	);
};

export default PatternGridMenu;
