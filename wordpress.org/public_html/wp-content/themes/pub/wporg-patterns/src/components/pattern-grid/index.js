/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Pagination from './pagination';
import { store as patternStore } from '../../store';

function PatternGrid( { query, children } ) {
	const { isLoading, posts, totalPages } = useSelect( ( select ) => {
		const { getPatternTotalPagesByQuery, getPatternsByQuery, isLoadingPatternsByQuery } = select(
			patternStore
		);

		return {
			isLoading: query && isLoadingPatternsByQuery( query ),
			posts: query ? getPatternsByQuery( query ) : [],
			totalPages: query ? getPatternTotalPagesByQuery( query ) : 0,
		};
	} );

	return (
		<>
			<div className="pattern-grid">{ isLoading ? <Spinner /> : posts.map( children ) }</div>
			<Pagination totalPages={ totalPages } currentPage={ query?.page } />
		</>
	);
}

export default PatternGrid;
