import React from 'react';
import values from 'lodash/values';

import ImageGallery from './image-gallery';

export default React.createClass( {
	displayName: 'Screenshots',

	render() {
		const items = values( this.props.screenshots ).map( ( { caption, src } ) => ( {
			original: src,
			originalAlt: '',
			thumbnail: src + '&width=100',
			thumbnailAlt: caption || '',
			description: caption || false,
		} ) );

		if ( ! items ) {
			return;
		}

		return (
			<div id="screenshots" className="plugin-screenshots">
				<h2>Screenshots</h2>
				<ImageGallery items={ items } />
			</div>
		)
	}
} );
