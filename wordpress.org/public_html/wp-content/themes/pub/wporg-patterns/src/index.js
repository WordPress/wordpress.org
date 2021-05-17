/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import PatternPreview from './components/pattern-preview';
import PatternPreviewActions from './components/pattern-preview-actions';
import ReportPatternButton from './components/report-pattern-button';
import Patterns from './components/patterns';

// Load the preview into any awaiting preview container.
const previewContainers = document.querySelectorAll( '.pattern-preview__container' );
for ( let i = 0; i < previewContainers.length; i++ ) {
	const container = previewContainers[ i ];
	const blockContent = JSON.parse( decodeURIComponent( container.innerText ) );
	// Use `wp.blocks.parse` to convert HTML to block objects (for use in editor), if needed.

	render( <PatternPreview blockContent={ blockContent } />, container, () => {
		// This callback is called after the render to unhide the container.
		container.hidden = false;
	} );
}

// Load the preview into any awaiting preview container.
const gridContainer = document.getElementById( 'patterns__container' );
if ( gridContainer ) {
	render( <Patterns />, gridContainer );
}

// Load the pattern preview actions
const patternActionsContainer = document.getElementById( 'pattern-actions' );
if ( patternActionsContainer ) {
	render(
		<PatternPreviewActions patternId={ Number( patternActionsContainer.dataset.id ) } />,
		patternActionsContainer
	);
}

// Load report button
const patternReportContainer = document.getElementById( 'pattern-report' );
if ( patternReportContainer ) {
	const { loggedIn, postId, userHasReported } = patternReportContainer.dataset;

	render(
		<ReportPatternButton
			userHasReported={ userHasReported === 'true' }
			loggedIn={ loggedIn === 'true' }
			postId={ postId }
		/>,
		patternReportContainer
	);
}
