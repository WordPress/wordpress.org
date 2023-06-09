/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { Button } from '@wordpress/components';
import { store as patternStore } from '../../store';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Menu from './menu';
import NavigationLayout from '../navigation-layout';
import PatternGrid from '../pattern-grid';
import PatternSelectControl from '../pattern-select-control';
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
		// If the status is "pending", we actually want to show pending and pending-review (possibly spam).
		if ( 'pending' === modifiedQuery.status ) {
			modifiedQuery.status = 'pending,pending-review';
		}
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
				<div className="alignwide" style={ { marginLeft: 'auto', marginRight: 'auto', maxWidth: 960 } }>
					<p>{ __( 'Please log in to view your patterns.', 'wporg-patterns' ) }</p>
					<p>
						<a className="button button-primary button-large" href={ loginUrl }>
							{ __( 'Log in', 'wporg-patterns' ) }
						</a>
					</p>
					<div style={ { height: 100 } } aria-hidden="true" className="wp-block-spacer" />
				</div>
			</div>
		);
	}

	return (
		<RouteProvider>
			<QueryMonitor />
			<NavigationLayout
				primary={ <Menu /> }
				secondary={
					<PatternSelectControl
						label={ __( 'Order by', 'wporg-patterns' ) }
						param="orderby"
						defaultValue="date"
						options={ [
							{ label: __( 'Newest', 'wporg-patterns' ), value: 'date' },
							{ label: __( 'Popular', 'wporg-patterns' ), value: 'favorite_count' },
						] }
					/>
				}
			/>
			{ isEmpty ? (
				<div className="pattern-grid__empty-header">
					<h2>{ __( 'Create and share patterns for every WordPress site.', 'wporg-patterns' ) }</h2>
					<p>
						{ __(
							'Anyone can create and share patterns using the familiar block editor. Design helpful starting points for yourself and any WordPress site.',
							'wporg-patterns'
						) }
					</p>
					<Button variant="primary" href={ `${ wporgPatternsUrl.site }/new-pattern/` }>
						{ __( 'Create your first pattern', 'wporg-patterns' ) }
					</Button>
				</div>
			) : (
				<PatternGrid query={ query }>
					{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } showOptions /> }
				</PatternGrid>
			) }
		</RouteProvider>
	);
};

export default MyPatterns;
