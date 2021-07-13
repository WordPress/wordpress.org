/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import CategoryContextBar from '../category-context-bar';
import PatternGrid from '../pattern-grid';
import QueryMonitor from '../query-monitor';
import PatternThumbnail from '../pattern-thumbnail';
import { RouteProvider } from '../../hooks';
import { store as patternStore } from '../../store';

const PatternsSearch = ( { resultCount } ) => {
	const query = useSelect( ( select ) => select( patternStore ).getCurrentQuery() );

	return (
		<RouteProvider>
			<QueryMonitor />
			<CategoryContextBar resultCount={ resultCount } />
			<PatternGrid query={ query }>
				{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } showAvatar /> }
			</PatternGrid>
		</RouteProvider>
	);
};

export default PatternsSearch;
