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
import { featuredImageSizePresets, ImageInspectorPanel } from '../shared/image';
import { GridInspectorPanel }                            from '../shared/post-list';

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
		const { show_logo, featured_image_width, image_align, show_name, content, sort } = attributes;
		const { schema, options = DEFAULT_OPTIONS } = blockData;

		return (
			<InspectorControls>
				<GridInspectorPanel
					{ ...this.props }
				/>

				<ImageInspectorPanel
					title={ __( 'Logo Settings', 'wordcamporg' ) }
					show={ show_logo }
					onChangeShow={ ( value ) => setAttributes( { show_logo: value } ) }
					size={ featured_image_width }
					onChangeSize={ ( value ) => setAttributes( { featured_image_width: value } ) }
					sizeSchema={ schema.featured_image_width }
					sizePresets={ featuredImageSizePresets }
					align={ image_align }
					onChangeAlign={ ( value ) => setAttributes( { image_align: value } ) }
					alignOptions={ options.align_image }
				/>

				<PanelBody
					title={ __( 'Content Settings', 'wordcamporg' ) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __( 'Name', 'wordcamporg' ) }
						help={ __( 'Show or hide sponsor name', 'wordcamporg' ) }
						checked={ show_name }
						onChange={ ( value ) => setAttributes( { show_name: value } ) }
					/>
					<SelectControl
						label={ __( 'Description', 'wordcamporg' ) }
						value={ content }
						options={ options.content }
						help={ __( 'Length of sponsor description', 'wordcamporg' ) }
						onChange={ ( value ) => setAttributes( { content: value } ) }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Sorting', 'wordcamporg' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Sort by', 'wordcamporg' ) }
						value={ sort }
						options={ options.sort }
						onChange={ ( value ) => setAttributes( { sort: value } ) }
					/>
				</PanelBody>
			</InspectorControls>
		);
	}
}

export default SponsorsInspectorControls;
