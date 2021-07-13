/**
 * WordPress dependencies
 */
import { getPath } from '@wordpress/url';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CategoryContextBar from '../category-context-bar';
import { getCategoryFromPath } from '../../utils';
import Menu from '../menu';
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

const PatternGridMenu = () => {
	const { path, update: updatePath } = useRoute();
	const categorySlug = getCategoryFromPath( path );

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
										value: `/${ getPath( record.link ) || '' }`,
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
			<CategoryContextBar />
		</>
	);
};

export default PatternGridMenu;
