/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
const { Component } = wp.element;

/**
 * Displays featured image, can be linked with block control for size.
 */
export default class FeaturedImage extends Component {

	/**
	 * @param props Props for function.
	 * @param props.wpMediaDetails Available sizes of images in the format as returned by WP API. This is the `sizes` object inside `media_details` inside `wp:featuredMedia` object.
	 * @param props.height Height in pixels for image.
	 * @param props.width Width in pixels for image.
	 * @param props.className Classname for image element
	 * @param props.alt Alt text for image
	 */
	constructor( props ) {
		super( props );
		this.state = {};
	}

	/**
	 * Get 'full' size image to be displayed in editor. Or get the widest one.
	 */
	getFullImage() {

		const availableSizes = this.props.wpMediaDetails;

		const { selectedImage } = this.state;

		if ( selectedImage && selectedImage.hasOwnProperty( 'source_url' ) ) {
			return selectedImage;
		}

		if ( availableSizes.hasOwnProperty( 'full' ) && availableSizes['full'].hasOwnProperty( 'source_url' ) ) {
			this.setState( { selectedImage: availableSizes['full'] } );
			return availableSizes[ 'full' ];
		}

		let widestImage = { source_url : '' };

		for ( const size in availableSizes ) {
			if ( ! availableSizes.hasOwnProperty( size ) ) {
				continue;
			}

			if ( availableSizes[ size ]['width'] > ( widestImage[ 'width '] || 0 ) && availableSizes[ size ].hasOwnProperty( 'source_url' ) ) {
				widestImage = availableSizes[ size ];
			}
		}

		this.setState( { selectedImage: widestImage } );

		return widestImage;
	}

	/**
	 * Renders FeaturedImage component.
	 *
	 * @returns {*}
	 */
	render() {
		const { className, alt, attributes } = this.props;

		const { featured_image_width } = attributes;
		const image = this.getFullImage();

		const width = featured_image_width || 150 ;

		return(
			<img
				className={ classnames( 'wordcamp-featured-image', className ) }
				src = { image['source_url'] }
				alt = { alt }
				width = { width + 'px' }
			/>
		)
	}
}
