/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CategoryContextBar from '../category-context-bar';
import EmptyHeader from '../patterns/empty-header';
import PatternGrid from '../pattern-grid';
import PatternThumbnail from '../pattern-thumbnail';
import QueryMonitor from '../query-monitor';
import { RouteProvider } from '../../hooks';
import { store as patternStore } from '../../store';

const PatternsSearch = () => {
	const { isEmpty, query } = useSelect( ( select ) => {
		const { getCurrentQuery, getPatternsByQuery, isLoadingPatternsByQuery } = select( patternStore );
		const _query = getCurrentQuery();
		const isLoading = _query && isLoadingPatternsByQuery( _query );
		const posts = _query ? getPatternsByQuery( _query ) : [];

		return {
			isEmpty: ! isLoading && ! posts.length,
			query: _query,
		};
	} );

	return (
		<RouteProvider>
			<QueryMonitor />
			<CategoryContextBar query={ query } />
			{ isEmpty ? (
				<>
					<EmptyHeader />
					<PatternGrid query={ { per_page: 6 } } showPagination={ false }>
						{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } showAvatar /> }
					</PatternGrid>
				</>
			) : (
				<PatternGrid query={ query }>
					{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } showAvatar /> }
				</PatternGrid>
			) }
		</RouteProvider>
	);
};

export default PatternsSearch;
