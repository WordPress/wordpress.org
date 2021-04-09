/**
 * WordPress dependencies
 */
import { Spinner } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import PatternThumbnail from '../pattern-thumbnail';
import PatternGridMenu from '../pattern-grid-menu';
import { store as patternStore } from '../../store';

function PatternGrid() {
	const posts = useSelect( ( select ) => {
		const { getPatternsByQuery } = select( patternStore );
		return getPatternsByQuery( {} );
	} );
	// `posts` will be null while the fetch happens.
	const isLoading = ! Array.isArray( posts );

	return (
		<>
			<PatternGridMenu />

			<div className="pattern-grid">
				{ isLoading ? (
					<Spinner />
				) : (
					posts.map( ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> )
				) }
			</div>
		</>
	);
}

export default PatternGrid;
