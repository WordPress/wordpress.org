/* global localeData */
/**
 * Internal dependencies.
 */
import ImageGallery from './image-gallery';

export const Screenshots = ( { screenshots = [] } ) => {
	if ( ! screenshots ) {
		return null;
	}

	const items = screenshots.map( ( { caption, src } ) => ( {
		original: src,
		originalAlt: '',
		thumbnail: src,
		thumbnailAlt: caption || '',
		description: caption || false,
	} ) );

	return (
		<div id="screenshots" className="plugin-screenshots">
			<h2>{ localeData.screenshots }</h2>
			<ImageGallery items={ items } />
		</div>
	);
};

export default Screenshots;
