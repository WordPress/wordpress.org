/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { store as patternStore } from '../../store';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import EmptyHeader from './empty-header';
import PatternGrid from '../pattern-grid';
import PatternGridMenu from '../pattern-grid-menu';
import PatternThumbnail from '../pattern-thumbnail';
import QueryMonitor from '../query-monitor';
import { RouteProvider } from '../../hooks';
import useFocusOnNavigation from '../../hooks/use-focus-on-navigation';

const MyFavorites = () => {
	const { isEmpty, query } = useSelect( ( select ) => {
		const { getCurrentQuery, getFavorites, getPatternsByQuery, isLoadingPatternsByQuery } =
			select( patternStore );
		const _query = getCurrentQuery() || {};
		const favorites = getFavorites();

		// Favorites haven't loaded yet.
		if ( favorites === null ) {
			return {
				query: false,
				isEmpty: false,
			};
		}

		const modifiedQuery = { ..._query, include: favorites };
		const isLoading = !! favorites.length && isLoadingPatternsByQuery( modifiedQuery );
		const posts = favorites.length ? getPatternsByQuery( modifiedQuery ) : [];

		return {
			query: modifiedQuery,
			isEmpty: ! isLoading && ! posts.length,
		};
	} );
	const [ ref, onNavigation ] = useFocusOnNavigation();

	const mostFavoritedQuery = { orderby: 'favorite_count', per_page: 6, curation: 'core' };
	if ( query[ 'pattern-categories' ] ) {
		mostFavoritedQuery[ 'pattern-categories' ] = query[ 'pattern-categories' ];
	}

	const isLoggedIn = !! wporgPatternsData.userId;

	return (
		<RouteProvider>
			<QueryMonitor />
			<div ref={ ref }>
				{ isLoggedIn && (
					<PatternGridMenu
						basePath="/favorites/"
						query={ query }
						onNavigation={ onNavigation }
						isEmpty={ isEmpty }
						hideCuration
					/>
				) }
			</div>
			{ ! isLoggedIn || isEmpty ? (
				<>
					<EmptyHeader isLoggedIn={ isLoggedIn } />
					<PatternGrid
						header={
							<h2 className="pattern-favorites__grid-title">
								{ __( 'Here’s a few of our favorite patterns', 'wporg-patterns' ) }
							</h2>
						}
						query={ mostFavoritedQuery }
						showPagination={ false }
					>
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

export default MyFavorites;
