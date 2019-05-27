/**
 * External dependencies.
 */
import React from 'react';
import { render } from 'react-dom';

/**
 * Internal dependencies.
 */
import Screenshots from 'components/plugin/sections/screenshots';

// Temporary hack to use the srceenshot viewer without the full React client
const elements = document.querySelectorAll( '#screenshots figure' );
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
	const temp = document.createElement( 'div');
	const container = document.querySelector( '.entry-content' );

	render(
		<Screenshots screenshots={ images } />,
		temp
	);

	container.replaceChild( temp.querySelector( '#screenshots' ), document.getElementById( 'screenshots' ) );

	// On page load, we want to show or hide <Screenshots /> after it is rendered, based on the #hash in url.
	if ( typeof window.showUrlHashTargetTab === 'function' ) {
		window.showUrlHashTargetTab();
	}
}
