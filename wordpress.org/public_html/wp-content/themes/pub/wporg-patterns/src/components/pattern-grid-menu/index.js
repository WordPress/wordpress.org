/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { getQueryString } from '@wordpress/url';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CategoryContextBar from '../category-context-bar';
import { getCategoryFromPath } from '../../utils';
import PatternOrderSelect from '../pattern-order-select';
import Menu from '../menu';
import NavigationLayout from '../navigation-layout';
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

const PatternGridMenu = ( { basePath = '/', query } ) => {
	const { path, update: updatePath } = useRoute();
	const categorySlug = getCategoryFromPath( path );
	const queryString = getQueryString( path ) ? '?' + getQueryString( path ) : '';

	// Make sure the path is prefixed with the full site URL.
	basePath = wporgPatternsUrl.site + basePath;

	const { categories, isLoading } = useSelect( ( select ) => {
		const { getCategories, isLoadingCategories } = select( patternStore );
		return {
			categories: getCategories(),
			isLoading: isLoadingCategories(),
		};
	} );

	return (
		<>
			<NavigationLayout
				primary={
					<Menu
						current={ categorySlug }
						options={
							categories
								? categories.map( ( record ) => {
										return {
											value: record.slug
												? `${ basePath }pattern-categories/${ record.slug }/${ queryString }`
												: `${ basePath }${ queryString }`,
											slug: record.slug,
											label: record.name,
										};
								  } )
								: []
						}
						onClick={ ( event ) => {
							event.preventDefault();
							updatePath( event.target.pathname );
						} }
						isLoading={ isLoading }
					/>
				}
				secondary={
					<PatternOrderSelect
						options={ [
							{ label: __( 'Newest', 'wporg-patterns' ), value: 'date' },
							{ label: __( 'Favorites', 'wporg-patterns' ), value: 'favorite_count' },
						] }
					/>
				}
			/>
			<CategoryContextBar query={ query } />
		</>
	);
};

export default PatternGridMenu;
