import React from 'react';
import { render } from 'react-dom';

import Screenshots from 'components/plugin/sections/screenshots';

// Temporary hack to use the srceenshot viewer without the full React client
var elements = document.querySelectorAll( '#screenshots figure' );
var images = [];
for ( var i=0; i < elements.length; i++ ) {
	var caption = elements[i].querySelector('figcaption');
	var item = { 
		src: elements[i].querySelector('img.screenshot').src,
		caption: caption ? caption.textContent : '',
	}
	images.push( item );
}

if ( images.length > 0 ) {
	render(
		<Screenshots screenshots={images}>
		</Screenshots>,
		document.getElementById( 'screenshots' )
	);
}
