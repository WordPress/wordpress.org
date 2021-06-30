/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import PatternPreview from '../pattern-preview';
import PatternPreviewActions from '../pattern-preview-actions';
import ReportPatternButton from '../report-pattern-button';
import { store as patternStore } from '../../store';

const Pattern = ( { postId, userHasReported, loggedIn } ) => {
	// postId as passed from the HTML dataset is a string.
	postId = Number( postId ) || 0;
	const pattern = useSelect( ( select ) => select( patternStore ).getPattern( postId ), [ postId ] );
	if ( ! pattern ) {
		return null;
	}

	return (
		<>
			<PatternPreviewActions postId={ postId } />
			<PatternPreview blockContent={ pattern.content.rendered } />
			<div className="pattern__meta">
				<ReportPatternButton
					userHasReported={ userHasReported === 'true' }
					loggedIn={ loggedIn === 'true' }
					postId={ postId }
				/>
			</div>
		</>
	);
};

export default Pattern;
