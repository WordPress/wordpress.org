/**
 * External dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import Screenshots from './screenshots';

function initializeScreenshots( id ) {
	const container = document.getElementById( id );
	if ( ! container ) {
		return;
	}

	const elements = container.querySelectorAll( 'figure' );
	const images = [];
	for ( let i = 0; i < elements.length; i++ ) {
		const caption = elements[ i ].querySelector( 'figcaption' );
		const item = {
			src: elements[ i ].querySelector( 'img.screenshot' ).src,
			caption: caption ? caption.textContent : '',
		};
		images.push( item );
	}

	if ( images.length > 0 ) {
		render( <Screenshots screenshots={ images } />, container );
	}
}

initializeScreenshots( 'screenshots' );
