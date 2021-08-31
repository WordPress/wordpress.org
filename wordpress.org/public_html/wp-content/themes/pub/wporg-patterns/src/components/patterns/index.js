/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import BreadcrumbMonitor from '../breadcrumb-monitor';
import ContextBar from '../context-bar';
import DocumentTitleMonitor from '../document-title-monitor';
import EmptyHeader from './empty-header';
import PatternGrid from '../pattern-grid';
import PatternGridMenu from '../pattern-grid-menu';
import PatternThumbnail from '../pattern-thumbnail';
import QueryMonitor from '../query-monitor';
import { RouteProvider } from '../../hooks';
import { store as patternStore } from '../../store';
import useFocusOnNavigation from '../../hooks/use-focus-on-navigation';

const Patterns = () => {
	const { isEmpty, isSearch, query } = useSelect( ( select ) => {
		const { getCurrentQuery, getPatternsByQuery, isLoadingPatternsByQuery } = select( patternStore );
		const _query = getCurrentQuery();
		const isLoading = _query && isLoadingPatternsByQuery( _query );
		const posts = _query ? getPatternsByQuery( _query ) : [];

		return {
			isEmpty: ! isLoading && ! posts.length,
			isSearch: _query && !! _query.search,
			query: _query,
		};
	} );
	const [ ref, onNavigation ] = useFocusOnNavigation();

	return (
		<RouteProvider>
			<DocumentTitleMonitor />
			<QueryMonitor />
			<BreadcrumbMonitor />
			<div ref={ ref }>
				{ isSearch ? <ContextBar query={ query } /> : <PatternGridMenu onNavigation={ onNavigation } /> }
			</div>
			{ isEmpty ? (
				<>
					<EmptyHeader />
					<PatternGrid query={ { per_page: 6 } } showPagination={ false }>
						{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } showAvatar /> }
					</PatternGrid>
				</>
			) : (
				<PatternGrid query={ query } onNavigation={ onNavigation }>
					{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } showAvatar /> }
				</PatternGrid>
			) }
		</RouteProvider>
	);
};

export default Patterns;
