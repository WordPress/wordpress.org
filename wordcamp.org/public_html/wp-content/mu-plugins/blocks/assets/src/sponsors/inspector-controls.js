/**
 * WordPress dependencies.
 */
const { Component }                                         = wp.element;
const { InspectorControls }                                 = wp.editor;
const { PanelBody, PanelRow, ToggleControl, SelectControl } = wp.components;
const { __ }                                                = wp.i18n;

/**
 * Internal dependencies
 */
import GridInspectorControl           from '../shared/grid-layout/inspector-control';
import FeaturedImageInspectorControls from '../shared/featured-image/inspector-control';

const DEFAULT_OPTIONS = {
	align_image : {},
	content     : {},
	sort        : {},
};

/**
 * Component for block controls that appear in the Inspector Panel.
 */
class SponsorsInspectorControls extends Component {
	/**
	 * Render the controls.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, setAttributes, blockData } = this.props;
		const { show_name, show_logo, sort, content } = attributes;
		const { options = DEFAULT_OPTIONS } = blockData;

		return (
			<InspectorControls>
				<GridInspectorControl
					{ ...this.props }
				/>

				<PanelBody
					title={ __( 'Content Settings', 'wordcamporg' ) }
					initialOpen={ true }
				>
					<PanelRow>
						<ToggleControl
							label={ __( 'Name', 'wordcamporg' ) }
							help={ __( 'Show or hide sponsor name', 'wordcamporg' ) }
							checked={ show_name }
							onChange={ ( value ) => setAttributes( { show_name: value } ) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={ __( 'Logo', 'wordcamporg' ) }
							help={ __( 'Show or hide sponsor logo', 'wordcamporg' ) }
							checked={ show_logo }
							onChange={ ( value ) => setAttributes( { show_logo: value } ) }
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label={ __( 'Description', 'wordcamporg' ) }
							value={ content }
							options={ options.content }
							help={ __( 'Length of sponsor description', 'wordcamporg' ) }
							onChange={ ( value ) => setAttributes( { content: value } ) }
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label={ __( 'Sort by', 'wordcamporg' ) }
							options={ options.sort }
							value={ sort }
							onChange={ ( value ) => setAttributes( { sort: value } ) }
						/>
					</PanelRow>
				</PanelBody>

				<FeaturedImageInspectorControls
					title={ __( 'Logo size', 'wordcamporg' ) }
					help={ __( 'Specify logo width, or select a predefined size.', 'wordcamporg' ) }
					selectLabel={ __( 'Size', 'wordcamporg' ) }
					{ ...this.props }
				/>
			</InspectorControls>
		);
	}
}

export default SponsorsInspectorControls;
