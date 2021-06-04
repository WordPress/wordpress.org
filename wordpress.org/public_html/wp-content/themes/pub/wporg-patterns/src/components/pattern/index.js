/**
 * Internal dependencies
 */
import PatternPreview from '../pattern-preview';
import PatternPreviewActions from '../pattern-preview-actions';
import ReportPatternButton from '../report-pattern-button';

const Pattern = ( { content, postId, userHasReported, loggedIn } ) => {
	// postId as passed from the HTML dataset is a string.
	postId = Number( postId ) || 0;
	return (
		<>
			<PatternPreviewActions postId={ postId } />
			<PatternPreview blockContent={ content } />
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
