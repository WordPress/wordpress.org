/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Pagination from './pagination';
import { store as patternStore } from '../../store';
import CardSkeleton from './card-skeleton';

function PatternGrid( { header, children, query, showPagination = true } ) {
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
			{ posts.length ? header : null }
			<div className="pattern-grid">
				{ isLoading
					? Array( 6 )
							.fill()
							.map( ( val, idx ) => <CardSkeleton key={ idx } /> )
					: posts.map( children ) }
			</div>
			{ showPagination && <Pagination totalPages={ totalPages } currentPage={ query?.page } /> }
		</>
	);
}

export default PatternGrid;
