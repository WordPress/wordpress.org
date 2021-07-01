/**
 * WordPress dependencies
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Pattern from './components/pattern';
import Patterns from './components/patterns';
import MyPatterns from './components/my-patterns';

// Load the grid into the awaiting preview container.
const gridContainer = document.getElementById( 'patterns__container' );
if ( gridContainer ) {
	render( <Patterns />, gridContainer );
}

// Load the preview into any awaiting preview container.
const myGridContainer = document.getElementById( 'my-patterns__container' );
if ( myGridContainer ) {
	render( <MyPatterns />, myGridContainer );
}

// Load the preview into any awaiting preview container.
const previewContainers = document.querySelectorAll( '.pattern__container' );
for ( let i = 0; i < previewContainers.length; i++ ) {
	const container = previewContainers[ i ];
	const props = container.dataset;

	render( <Pattern { ...props } />, container, () => {
		// This callback is called after the render to unhide the container.
		container.hidden = false;
	} );
}
