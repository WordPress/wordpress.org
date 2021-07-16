/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
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

const MyFavorites = () => {
	const { isEmpty, query } = useSelect( ( select ) => {
		const { getCurrentQuery, getFavorites, getPatternsByQuery, isLoadingPatternsByQuery } = select(
			patternStore
		);
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

	const isLoggedIn = !! wporgPatternsData.userId;

	if ( ! isLoggedIn ) {
		const loginUrl = addQueryArgs( wporgPatternsUrl.login, { redirect_to: window.location } );
		return (
			<div className="entry-content">
				<p>{ __( 'Please log in to view your favorite patterns.', 'wporg-patterns' ) }</p>
				<a className="button button-primary" href={ loginUrl }>
					{ __( 'Log in', 'wporg-patterns' ) }
				</a>
			</div>
		);
	}

	if ( isEmpty ) {
		return <EmptyHeader />;
	}

	return (
		<RouteProvider>
			<QueryMonitor />
			<PatternGridMenu basePath="/my-favorites/" query={ query } />
			<PatternGrid query={ query }>
				{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> }
			</PatternGrid>
		</RouteProvider>
	);
};

export default MyFavorites;
