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
	render(
		<Screenshots screenshots={ images } />,
		document.getElementById( 'screenshots' )
	);
}
