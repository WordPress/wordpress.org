/* global localeData, wp */
/**
 * WordPress dependencies.
 */
import { isRTL } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import ImageGallery from 'react-image-gallery';

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
			<ImageGallery
				items={ items }
				lazyLoad={ true }
				disableSwipe={ true }
				useBrowserFullscreen={ false }
				showPlayButton={ false }
				showFullscreenButton={ true }
				isRTL={ isRTL() }
			/>
		</div>
	);
};

export default Screenshots;
