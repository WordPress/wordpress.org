import React from 'react';
import values from 'lodash/values';

import ImageGallery from './image-gallery';

export default React.createClass( {
	displayName: 'Screenshots',

	render() {
		let items = values( this.props.screenshots ).map( screenshot => {
			return {
				original: screenshot.src,
				thumbnail: screenshot.src + '&width=100',
				description: screenshot.caption || false
			}
		} );

		if ( ! items ) {
			return <div />;
		}

		return (
			<div id="screenshots" className="plugin-screenshots">
				<h2>Screenshots</h2>
				<ImageGallery items={ items } />
			</div>
		)
	}
} );
