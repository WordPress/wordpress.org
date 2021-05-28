/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Pagination from './pagination';
import PatternThumbnail from '../pattern-thumbnail';
import { store as patternStore } from '../../store';

function PatternGrid( { query } ) {
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
			<div className="pattern-grid">
				{ isLoading ? (
					<Spinner />
				) : (
					posts.map( ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> )
				) }
			</div>
			<Pagination totalPages={ totalPages } currentPage={ query?.page } />
		</>
	);
}

export default PatternGrid;
