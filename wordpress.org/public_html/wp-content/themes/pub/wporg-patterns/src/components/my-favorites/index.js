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
import PatternGrid from '../pattern-grid';
import PatternGridMenu from '../pattern-grid-menu';
import PatternThumbnail from '../pattern-thumbnail';
import QueryMonitor from '../query-monitor';
import { RouteProvider } from '../../hooks';

const MyFavorites = () => {
	const { favorites, query } = useSelect( ( select ) => ( {
		favorites: select( patternStore ).getFavorites(),
		query: select( patternStore ).getCurrentQuery(),
	} ) );
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

	if ( ! favorites.length ) {
		return (
			<div className="entry-content">
				<p>{ __( 'You haven’t favorited any patterns yet.', 'wporg-patterns' ) }</p>
			</div>
		);
	}

	const modifiedQuery = { ...query, include: favorites };

	return (
		<RouteProvider>
			<QueryMonitor />
			<PatternGridMenu basePath="/my-favorites/" query={ modifiedQuery } />
			<PatternGrid query={ modifiedQuery }>
				{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> }
			</PatternGrid>
		</RouteProvider>
	);
};

export default MyFavorites;
