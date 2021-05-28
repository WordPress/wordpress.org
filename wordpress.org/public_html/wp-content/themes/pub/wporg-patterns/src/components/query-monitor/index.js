/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { getQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';
import { getCategoryFromPath, getPageFromPath } from '../../utils';

/**
 * Listens for changes to the path and reconstructs the query object based on the path
 */
const QueryMonitor = () => {
	const { path } = useRoute();

	const query = useSelect(
		( select ) => {
			const { getCategoryBySlug, hasLoadedCategories } = select( patternStore );

			// We need categories loaded before building the query
			if ( ! hasLoadedCategories() ) {
				return;
			}

			const categorySlug = getCategoryFromPath( path );
			const category = getCategoryBySlug( categorySlug );

			// Default to {} if empty
			const queryStrings = getQueryArgs( path );

			let _query = queryStrings;

			// If we have a category and it's not the default category
			if ( category && category.id !== -1 ) {
				_query = {
					..._query,
					'pattern-categories': category.id,
				};
			}

			const page = getPageFromPath( path );
			if ( page > 1 ) {
				_query.page = page;
			}

			return _query;
		},
		[ path ]
	);

	const { setCurrentQuery } = useDispatch( patternStore );

	useEffect( () => {
		setCurrentQuery( query );
	}, [ query ] );

	return null;
};

export default QueryMonitor;
