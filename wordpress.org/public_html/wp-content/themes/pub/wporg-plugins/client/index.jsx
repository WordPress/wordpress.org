import React from 'react';
import { render } from 'react-dom';

import Screenshots from 'components/plugin/sections/screenshots';

// Temporary hack to use the srceenshot viewer without the full React client
var elements = document.querySelectorAll( '#screenshots figure' );
var images = [];
for ( var i=0; i < elements.length; i++ ) {
	var item = { 
		src: elements[i].querySelector('img.screenshot').src,
		caption: elements[i].querySelector('figcaption').textContent,
	}
	images.push( item );
}

render(
	<Screenshots screenshots={images}>
	</Screenshots>,
	document.getElementById( 'screenshots' )
);
