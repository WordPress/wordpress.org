/**
 * WordPress dependencies
 */
import { addQueryArgs, getPath, getQueryArg } from '@wordpress/url';
import { useDebounce } from '@wordpress/compose';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CategoryMenu from '../category-menu';
import CategorySearch from '../category-search';
import CategoryContextBar from '../category-context-bar';
import { getCategoryFromPath } from '../../utils';
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

/**
 * Module constants
 */
const DEBOUNCE_MS = 300;

const PatternGridMenu = () => {
	const { path, update: updatePath } = useRoute();
	const categorySlug = getCategoryFromPath( path );

	const { categories, isLoading, hasLoaded } = useSelect( ( select ) => {
		const { getCategories, isLoadingCategories, hasLoadedCategories } = select( patternStore );
		return {
			categories: getCategories(),
			isLoading: isLoadingCategories(),
			hasLoaded: hasLoadedCategories(),
		};
	} );

	const handleUpdatePath = ( value ) => {
		const updatedPath = addQueryArgs( path, {
			search: value,
		} );

		updatePath( updatedPath );
	};

	const debouncedHandleUpdate = useDebounce( handleUpdatePath, DEBOUNCE_MS );

	return (
		<>
			<nav className="pattern-grid-menu">
				<CategoryMenu
					currentCategory={ categorySlug }
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
				<CategorySearch
					isLoading={ isLoading }
					isVisible={ hasLoaded }
					defaultValue={ getQueryArg( window.location.href, 'search' ) }
					onUpdate={ ( event ) => {
						event.preventDefault();
						debouncedHandleUpdate( event.target.value );
					} }
					onSubmit={ ( event ) => {
						event.preventDefault();
						debouncedHandleUpdate( event.target.elements[ 0 ].value );
					} }
				/>
			</nav>
			<CategoryContextBar />
		</>
	);
};

export default PatternGridMenu;
