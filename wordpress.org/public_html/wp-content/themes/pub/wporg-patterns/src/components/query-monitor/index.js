/**
 * External dependencies
 */
import useDeepCompareEffect from 'use-deep-compare-effect';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';
import { useRoute } from '../../hooks';

/**
 * Listens for changes to the path and reconstructs the query object based on the path
 */
const QueryMonitor = () => {
	const { setCurrentQuery } = useDispatch( patternStore );
	const { path } = useRoute();
	const query = useSelect( ( select ) => select( patternStore ).getQueryFromUrl( path ), [ path ] );

	// Deep compare the object dependency, since `query` is a new object on every render.
	useDeepCompareEffect( () => {
		setCurrentQuery( query );
	}, [ query ] );

	return null;
};

export default QueryMonitor;
