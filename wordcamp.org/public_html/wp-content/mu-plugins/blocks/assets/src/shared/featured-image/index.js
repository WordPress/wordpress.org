/**
 * External dependencies
 */
import classnames from 'classnames';
import { sortBy } from 'lodash';

/**
 * WordPress dependencies.
 */
const { Disabled } = wp.components;
const { Component } = wp.element;
const { isURL } = wp.url;

/**
 * Displays a featured image, can be linked with block control for size.
 *
 * Unlike the PHP function that outputs a featured image, this doesn't bother with a `srcset` attribute, because
 * it's assumed that this is being used in the block editor interface, and it just uses the largest image size
 * available.
 */
export default class FeaturedImage extends Component {
	/**
	 * @param {Object} props
	 * @param {Array}  props.imageData Meta data about the featured image, including available sizes and the image's
	 *                                 alt text. This is the `_embedded.wp:featuredMedia[0]` object in a REST response.
	 * @param {number} props.width     Width in pixels for the image.
	 * @param {string} props.className Additional class names for the image element
	 * @param {string} props.imageLink URL for wrapping the image in an anchor tag
	 */
	constructor( props ) {
		super( props );

		const { imageData } = props;
		const { media_details = {}, alt_text = '' } = imageData;
		const image = this.constructor.getWidestImage( media_details );

		this.state = {
			image : image,
			alt   : alt_text,
		};
	}

	/**
	 * Get the details of the widest image size available.
	 *
	 * @param {Object} media_details
	 *
	 * @return {Object}
	 */
	static getWidestImage( media_details ) {
		let image = {};
		const { sizes = {} } = media_details;

		if ( sizes.hasOwnProperty( 'full' ) && sizes.full.hasOwnProperty( 'source_url' ) ) {
			image = sizes.full;
		} else if ( Object.getOwnPropertyDescriptors( sizes ).length > 0 ) {
			const sortedSizes = sortBy( sizes, 'width' );
			image = sortedSizes.pop();
		}

		return image;
	}

	/**
	 * Calculate a height based on the aspect ratio and a given width.
	 *
	 * @param {number} newWidth
	 * @param {Object} image
	 *
	 * @return {number|null}
	 */
	static getNewHeight( newWidth, image ) {
		let newHeight = null;
		const { width, height } = image;

		if ( width && height ) {
			const aspectRatio = Number( height ) / Number( width );
			newHeight = Number.parseFloat( aspectRatio * newWidth ).toFixed( 1 );
		}

		return newHeight;
	}

	/**
	 * Renders FeaturedImage component.
	 *
	 * @return {Element}
	 */
	render() {
		const { width, className, imageLink } = this.props;
		const { image, alt } = this.state;
		const { source_url: src = '' } = image;

		if ( ! src ) {
			return '';
		}

		const height = this.constructor.getNewHeight( width, image );

		let output = (
			<img
				className={ classnames( 'wordcamp-featured-image', 'wp-post-image' ) }
				src={ src }
				alt={ alt }
				width={ width }
				height={ height }
			/>
		);

		if ( isURL( imageLink ) ) {
			output = (
				<Disabled>
					<a href={ imageLink } className={ classnames( 'wordcamp-image-link', 'wordcamp-featured-image-link' ) }>
						{ output }
					</a>
				</Disabled>
			);
		}

		output = (
			<div className={ classnames( 'wordcamp-image-container', 'wordcamp-featured-image-container', className ) }>
				{ output }
			</div>
		);

		return output;
	}
}
