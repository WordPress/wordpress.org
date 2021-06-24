/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';
import { store as coreStore } from '@wordpress/core-data';
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
	const query = useSelect( ( select ) => select( patternStore ).getCurrentQuery() );
	const author = useSelect( ( select ) => select( coreStore ).getCurrentUser()?.id );

	if ( ! author ) {
		const loginUrl = addQueryArgs( wporgLoginUrl, { redirect_to: window.location } );
		return (
			<div className="entry-content">
				<p>{ __( 'Please log in to view your patterns.', 'wporg-patterns' ) }</p>
				<a className="button button-primary" href={ loginUrl }>
					{ __( 'Log in', 'wporg-patterns' ) }
				</a>
			</div>
		);
	}
	// Show all patterns regardless of status, but if the current query has a status (the view is draft, for
	// example), that will override `any`. Lastly, make sure this shows the current user's patterns.
	const modifiedQuery = { status: 'any', ...query, author: author };

	return (
		<RouteProvider>
			<QueryMonitor />
			<Menu />
			<PatternGrid query={ modifiedQuery }>
				{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> }
			</PatternGrid>
		</RouteProvider>
	);
};

export default MyPatterns;
