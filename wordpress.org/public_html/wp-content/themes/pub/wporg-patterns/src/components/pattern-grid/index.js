/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import GridSkeleton from './grid-skeleton';
import Pagination from './pagination';
import { store as patternStore } from '../../store';

function PatternGrid( { header, children, onNavigation, query, showPagination = true } ) {
	const { isLoading, posts, totalPages } = useSelect( ( select ) => {
		const { getPatternTotalPagesByQuery, getPatternsByQuery, isLoadingPatternsByQuery } =
			select( patternStore );

		return {
			isLoading: query && isLoadingPatternsByQuery( query ),
			posts: query ? getPatternsByQuery( query ) : [],
			totalPages: query ? getPatternTotalPagesByQuery( query ) : 0,
		};
	} );

	return (
		<>
			{ posts.length ? header : null }
			<div className="pattern-grid">
				{ isLoading ? <GridSkeleton length={ query?.per_page } /> : posts.map( children ) }
			</div>
			{ showPagination && (
				<Pagination totalPages={ totalPages } currentPage={ query?.page } onNavigation={ onNavigation } />
			) }
		</>
	);
}

export default PatternGrid;
