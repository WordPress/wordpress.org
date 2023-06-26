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
	const { isAuthor, isEmpty, isSearch, query } = useSelect( ( select ) => {
		const { getCurrentQuery, getPatternsByQuery, isLoadingPatternsByQuery } = select( patternStore );
		const _query = getCurrentQuery() || {};
		const _isSearch = !! _query.search;
		const _isAuthor = !! _query.author_name;

		const modifiedQuery = { ..._query };
		if ( ! _isSearch && ! _isAuthor && ! modifiedQuery.curation ) {
			modifiedQuery.curation = 'core';
		}

		const isLoading = isLoadingPatternsByQuery( modifiedQuery );
		const posts = modifiedQuery ? getPatternsByQuery( modifiedQuery ) : [];

		return {
			isAuthor: _isAuthor,
			isEmpty: ! isLoading && ! posts.length,
			isSearch: _isSearch,
			query: modifiedQuery,
		};
	} );
	const [ ref, onNavigation ] = useFocusOnNavigation();

	return (
		<RouteProvider>
			<DocumentTitleMonitor />
			<QueryMonitor />
			<BreadcrumbMonitor />
			<div ref={ ref }>
				{ isSearch ? (
					<ContextBar query={ query } />
				) : (
					<PatternGridMenu onNavigation={ onNavigation } query={ query } hideCuration={ isAuthor } />
				) }
			</div>
			{ isEmpty ? (
				<>
					<EmptyHeader />
					<PatternGrid query={ { per_page: 6, curation: 'core' } } showPagination={ false }>
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
