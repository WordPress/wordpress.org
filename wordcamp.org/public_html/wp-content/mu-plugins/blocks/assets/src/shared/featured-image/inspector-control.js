/**
 * WordPress dependencies
 */
const { Component } = wp.element;
const { PanelBody } = wp.components;

/**
 * Internal dependencies
 */
import './style.scss';
import { AvatarSizeControl } from '../avatar';

/**
 * Implements inspector control for FeaturedImage component defined in ./index.js. Uses and sets attribute `featured_image_height` and `featured_image_width`.
 */
class FeaturedImageInspectorControls extends Component {
	/**
	 * Renders inspector controls for FeatureImages.
	 *
	 * @returns {*}
	 */
	render() {
		const { attributes, setAttributes, title, help, selectLabel } = this.props;
		const { featured_image_width } = attributes;
		return (
			<PanelBody
				title={ title }
				initialopen={ false }
			>
				<AvatarSizeControl
					onChange={ ( width ) => setAttributes( { featured_image_width: Number( width ) } ) }
					label={ selectLabel }
					initialPosition={ featured_image_width }
					help={ help }
					// TODO: Use settings from add_script_data instead of hardcoded values. Related: https://github.com/WordPress/wordcamp.org/issues/57.
					rangeProps={ {
						min : 25,
						max : 1024,
					} }
				/>
			</PanelBody>
		);
	}
}

export default FeaturedImageInspectorControls;
