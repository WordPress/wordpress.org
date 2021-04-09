/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import PatternPreview from './components/pattern-preview';
import PatternGrid from './components/pattern-grid';

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
const gridContainer = document.getElementById( 'pattern-grid__container' );
if ( gridContainer ) {
	render( <PatternGrid />, gridContainer );
}
