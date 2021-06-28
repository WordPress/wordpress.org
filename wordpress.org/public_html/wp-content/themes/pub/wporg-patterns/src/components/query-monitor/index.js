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
import { getCategoryFromPath, getPageFromPath, getValueFromPath } from '../../utils';

/**
 * Listens for changes to the path and reconstructs the query object based on the path
 */
const QueryMonitor = () => {
	const { setCurrentQuery } = useDispatch( patternStore );
	const { path } = useRoute();

	let queryReady = true;
	const query = getQueryArgs( path );
	const categorySlug = getCategoryFromPath( path );

	const categoryId = useSelect(
		( select ) => {
			if ( categorySlug ) {
				// Don't let the query be set until we have the categories.
				queryReady = false;
				const { getCategoryBySlug, hasLoadedCategories } = select( patternStore );
				if ( hasLoadedCategories() ) {
					queryReady = true;
					const categoryObj = getCategoryBySlug( categorySlug );
					return categoryObj?.id || false;
				}
			}
			return false;
		},
		[ categorySlug ]
	);
	if ( categoryId ) {
		query[ 'pattern-categories' ] = categoryId;
	}

	const author = getValueFromPath( path, 'author' );
	if ( author ) {
		query.author_name = author;
	}

	const page = getPageFromPath( path );
	if ( page > 1 ) {
		query.page = page;
	}

	const myPatternStatus = getValueFromPath( path, 'my-patterns' );
	if ( myPatternStatus && 'page' !== myPatternStatus ) {
		query.status = myPatternStatus;
	}

	useEffect( () => {
		if ( queryReady ) {
			setCurrentQuery( query );
		}
	}, [ query, queryReady ] );

	return null;
};

export default QueryMonitor;
