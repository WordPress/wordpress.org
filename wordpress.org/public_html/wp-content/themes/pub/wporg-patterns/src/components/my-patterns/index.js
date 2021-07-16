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
import Menu from './menu';
import PatternGrid from '../pattern-grid';
import PatternThumbnail from '../pattern-thumbnail';
import QueryMonitor from '../query-monitor';
import { RouteProvider } from '../../hooks';

const MyPatterns = () => {
	const author = wporgPatternsData.userId;
	const { isEmpty, query } = useSelect( ( select ) => {
		const { getCurrentQuery, getPatternsByQuery, isLoadingPatternsByQuery } = select( patternStore );
		const _query = getCurrentQuery() || {};

		// Show all patterns regardless of status, but if the current query has a status (the view is draft, for
		// example), that will override `any`. Lastly, make sure this shows the current user's patterns.
		const modifiedQuery = { status: 'any', ..._query, author: author };
		const isLoading = author && isLoadingPatternsByQuery( modifiedQuery );
		const posts = author ? getPatternsByQuery( modifiedQuery ) : [];

		return {
			isEmpty: ! isLoading && ! posts.length,
			query: modifiedQuery,
		};
	} );

	if ( ! author ) {
		const loginUrl = addQueryArgs( wporgPatternsUrl.login, { redirect_to: window.location } );
		return (
			<div className="entry-content">
				<p>{ __( 'Please log in to view your patterns.', 'wporg-patterns' ) }</p>
				<a className="button button-primary" href={ loginUrl }>
					{ __( 'Log in', 'wporg-patterns' ) }
				</a>
			</div>
		);
	}

	return (
		<RouteProvider>
			<QueryMonitor />
			<Menu />
			{ isEmpty ? (
				<div className="pattern-grid__empty-header">
					<h2>{ __( 'Nothing found', 'wporg-patterns' ) }</h2>
					<p>{ __( 'You haven’t created any patterns yet.', 'wporg-patterns' ) }</p>
				</div>
			) : (
				<PatternGrid query={ query }>
					{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> }
				</PatternGrid>
			) }
		</RouteProvider>
	);
};

export default MyPatterns;
