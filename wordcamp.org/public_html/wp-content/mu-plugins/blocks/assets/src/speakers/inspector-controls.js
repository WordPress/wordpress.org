/**
 * WordPress dependencies
 */
const { PanelBody, PanelRow, SelectControl, ToggleControl } = wp.components;
const { InspectorControls } = wp.editor;
const { Component, Fragment } = wp.element;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { AvatarSizeControl }  from '../shared/avatar';
import ImageAlignmentControl  from '../shared/image-alignment';
import { GridInspectorPanel } from '../shared/post-list';

const DEFAULT_SCHEMA = {
	grid_columns: {
		default : 2,
		minimum : 2,
		maximum : 4,
	},
	avatar_size: {
		default : 150,
		minimum : 25,
		maximum : 600,
	},
};

const DEFAULT_OPTIONS = {
	align_image : {},
	content     : {},
	sort        : {},
};

/**
 * Component for block controls that appear in the Inspector Panel.
 */
class SpeakerInspectorControls extends Component {
	/**
	 * Render the controls.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, setAttributes, blockData } = this.props;
		const { show_avatars, avatar_size, avatar_align, content, show_session, sort } = attributes;
		const { schema = DEFAULT_SCHEMA, options = DEFAULT_OPTIONS } = blockData;

		return (
			<InspectorControls>
				<GridInspectorPanel
					{ ...this.props }
				/>

				<PanelBody title={ __( 'Avatar Settings', 'wordcamporg' ) } initialOpen={ true }>
					<PanelRow>
						<ToggleControl
							label={ __( 'Show avatars', 'wordcamporg' ) }
							checked={ show_avatars }
							onChange={ ( value ) => setAttributes( { show_avatars: value } ) }
						/>
					</PanelRow>
					{ show_avatars &&
						<Fragment>
							<PanelRow>
								<AvatarSizeControl
									label={ __( 'Size', 'wordcamporg' ) }
									value={ Number( avatar_size ) }
									initialPosition={ Number( schema.avatar_size.default ) }
									onChange={ ( value ) => setAttributes( { avatar_size: value } ) }
									rangeProps={ {
										min : Number( schema.avatar_size.minimum ),
										max : Number( schema.avatar_size.maximum ),
									} }
								/>
							</PanelRow>
							<PanelRow>
								<ImageAlignmentControl
									label={ __( 'Alignment', 'wordcamporg' ) }
									value={ avatar_align }
									onChange={ ( value ) => setAttributes( { avatar_align: value } ) }
									alignOptions={ options.align_image }
								/>
							</PanelRow>
						</Fragment>
					}
				</PanelBody>

				<PanelBody title={ __( 'Content Settings', 'wordcamporg' ) } initialOpen={ false }>
					<PanelRow>
						<SelectControl
							label={ __( 'Biography Length', 'wordcamporg' ) }
							value={ content }
							options={ options.content }
							onChange={ ( value ) => setAttributes( { content: value } ) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label={ __( 'Session Information', 'wordcamporg' ) }
							help={ __( "Show speaker's session name, time, and track", 'wordcamporg' ) }
							checked={ show_session }
							onChange={ ( value ) => setAttributes( { show_session: value } ) }
						/>
					</PanelRow>
				</PanelBody>

				<PanelBody title={ __( 'Sorting & Filtering', 'wordcamporg' ) } initialOpen={ false }>
					<PanelRow>
						<SelectControl
							label={ __( 'Sort by', 'wordcamporg' ) }
							value={ sort }
							options={ options.sort }
							onChange={ ( value ) => setAttributes( { sort: value } ) }
						/>
					</PanelRow>
				</PanelBody>
			</InspectorControls>
		);
	}
}

export default SpeakerInspectorControls;
