/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CategoryContextBar from '../category-context-bar';
import { getCategoryFromPath } from '../../utils';
import Menu from '../menu';
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

const PatternGridMenu = ( { basePath = '/', query } ) => {
	const { path, update: updatePath } = useRoute();
	const categorySlug = getCategoryFromPath( path );

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
			<nav className="pattern-grid-menu">
				<Menu
					current={ categorySlug }
					options={
						categories
							? categories.map( ( record ) => {
									return {
										value: record.slug
											? `${ basePath }pattern-categories/${ record.slug }/`
											: basePath,
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
			</nav>
			<CategoryContextBar query={ query } />
		</>
	);
};

export default PatternGridMenu;
