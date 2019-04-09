/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies.
 */
const { Disabled } = wp.components;
const { Component } = wp.element;
const { isURL } = wp.url;

/**
 * Displays featured image, can be linked with block control for size.
 */
export default class FeaturedImage extends Component {
	/**
	 * @param {Object} props
	 * @param {Array}  props.wpMediaDetails Available sizes of images in the format as returned by WP API. This is the `sizes` object inside `media_details` inside `wp:featuredMedia` object.
	 * @param {number} props.width          Width in pixels for image.
	 * @param {string} props.className      Class name for image element
	 * @param {string} props.alt            Alt text for image
	 */
	constructor( props ) {
		super( props );

		this.state = {};
	}

	/**
	 * Get 'full' size image to be displayed in editor. Or get the widest one.
	 *
	 * @return {Object}
	 */
	getFullImage() {
		const { getOwnPropertyDescriptors } = Object;
		const availableSizes = this.props.wpMediaDetails;

		const { selectedImage } = this.state;

		if ( selectedImage && selectedImage.hasOwnProperty( 'source_url' ) ) {
			return selectedImage;
		}

		if ( availableSizes.hasOwnProperty( 'full' ) && availableSizes.full.hasOwnProperty( 'source_url' ) ) {
			this.setState( { selectedImage: availableSizes.full } );
			return availableSizes.full;
		}

		let widestImage = { source_url: '' };

		for ( const size in getOwnPropertyDescriptors( availableSizes ) ) {
			if ( availableSizes[ size ].width > ( widestImage.width || 0 ) && availableSizes[ size ].hasOwnProperty( 'source_url' ) ) {
				widestImage = availableSizes[ size ];
			}
		}

		this.setState( { selectedImage: widestImage } );

		return widestImage;
	}

	/**
	 * Renders FeaturedImage component.
	 *
	 * @return {Element}
	 */
	render() {
		const { className, alt, width = 150, imageLink } = this.props;
		const fullImage = this.getFullImage();

		let image = (
			<img
				className={ classnames( 'wordcamp-featured-image', className ) }
				src={ fullImage.source_url }
				alt={ alt }
				width={ width + 'px' }
			/>
		);

		if ( isURL( imageLink ) ) {
			image = (
				<Disabled>
					<a href={ imageLink } className={ classnames( 'wordcamp-image-link', 'wordcamp-featured-image-link' ) }>
						{ image }
					</a>
				</Disabled>
			);
		}

		image = (
			<div className={ classnames( 'wordcamp-image-container', 'wordcamp-featured-image-container', className ) }>
				{ image }
			</div>
		);

		return image;
	}
}
