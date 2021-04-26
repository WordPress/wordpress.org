/**
 * Internal dependencies
 */
import PatternGrid from '../pattern-grid';
import PatternGridMenu from '../pattern-grid-menu';
import QueryMonitor from '../query-monitor';
import { RouteProvider } from '../../hooks';

const Patterns = () => {
	return (
		<RouteProvider>
			<QueryMonitor />
			<PatternGridMenu />
			<PatternGrid />
		</RouteProvider>
	);
};

export default Patterns;
