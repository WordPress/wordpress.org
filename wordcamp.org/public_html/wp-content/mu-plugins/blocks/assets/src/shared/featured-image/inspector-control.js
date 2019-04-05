/**
 * WordPress dependencies.
 */
const { Component } = wp.element;
const { PanelBody, PanelRow, TextControl, BaseControl, SelectControl, ToggleControl } = wp.components;
const { __ } = wp.i18n;
import './style.scss';

/**
 * Not sure if these sizes are actually useful.
 */
const sizePresets = [
	{
		label : __( 'Thumbnail', 'wordcamporg' ),
		value : '150',
	},
	{
		label : __( 'Thumbnail - Large', 'wordcamporg' ),
		value : '926',
	},
	{
		label : __( 'Medium', 'wordcamporg' ),
		value : '300',
	},
	{
		label : __( 'Medium - Large', 'wordcamporg' ),
		value : '768',
	},
	{
		label : __( 'Large', 'wordcamporg' ),
		value : '1024',
	},
	{
		label : __( '(Custom)', 'wordcamporg' ),
		value : '',
	},
];

/**
 * Implements inspector control for FeaturedImage component defined in ./index.js. Uses and sets attribute `featured_image_height` and `featured_image_width`.
 */
class FeaturedImageInspectorControls extends Component {

	constructor( props ) {
		super( props );
		this.availableSizes = sizePresets.map( ( size ) => size.value );
	}

	onPresetSizeSelect( size ) {
		if ( size === '' ) {
			return;
		}
		const { setAttributes } = this.props;
		setAttributes( { featured_image_width: Number( size ) } );
	}

	render() {
		const { attributes, setAttributes, title, help, selectLabel, cropLabel } = this.props;
		const { featured_image_width } = attributes;
		const selectedValue = this.availableSizes.indexOf( featured_image_width.toString() ) === -1 ? '' : featured_image_width.toString();
		return (
			<PanelBody
				title={ title }
				initialopen={ false }
			>
				<PanelRow>
					<BaseControl
						help={ help }
					>
						<PanelRow>
							<SelectControl
								label={ selectLabel }
								value={ selectedValue }
								options={ sizePresets }
								onChange={ ( size ) => this.onPresetSizeSelect( size ) }
							/>
						</PanelRow>
						<PanelRow>
							<TextControl
								label={ __( 'Width (in px)', 'wordcamporg' ) }
								type="number"
								value={ featured_image_width }
								onChange={ ( width ) => setAttributes( { featured_image_width: Number( width ) } ) }
							/>
						</PanelRow>
					</BaseControl>
				</PanelRow>
			</PanelBody>
		);
	}
}

export default FeaturedImageInspectorControls;
