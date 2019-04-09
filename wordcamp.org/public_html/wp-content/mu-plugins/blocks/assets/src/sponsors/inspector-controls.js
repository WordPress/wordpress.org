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

/**
 * Class for defining Inspector control in sponsor block.
 */
class SponsorInspectorControls extends Component {
	/**
	 * Renders inspector controls.
	 *
	 * @return {Element}
	 */
	render() {
		const sortOptions = [
			{ label: __( 'Name (A to Z)', 'wordcamporg' ), value: 'name_asc'      },
			{ label: __( 'Name (Z to A)', 'wordcamporg' ), value: 'name_desc'     },
			{ label: __( 'Sponsor Level', 'wordcamporg' ), value: 'sponsor_level' },
		];

		const contentOptions = [
			{ label: __( 'Full',    'wordcamporg' ), value: 'full'    },
			{ label: __( 'Excerpt', 'wordcamporg' ), value: 'excerpt' },
			{ label: __( 'None',    'wordcamporg' ), value: 'none'    },
		];

		const { attributes, setAttributes }                            = this.props;
		const { show_name, show_logo, sort_by, excerpt_more, content } = attributes;

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
							checked={ show_name === undefined ? true : show_name }
							onChange={ ( value ) => setAttributes( { show_name: value } ) }
						/>
					</PanelRow>

					<PanelRow>
						<ToggleControl
							label={ __( 'Logo', 'wordcamporg' ) }
							help={ __( 'Show or hide sponsor logo', 'wordcamporg' ) }
							checked={ show_logo === undefined ? true : show_logo }
							onChange={ ( value ) => setAttributes( { show_logo: value } ) }
						/>
					</PanelRow>

					<PanelRow>
						<SelectControl
							label={ __( 'Description', 'wordcamporg' ) }
							value={ content }
							options={ contentOptions }
							help={ __( 'Length of sponsor description', 'wordcamporg' ) }
							onChange={ ( value ) => setAttributes( { content: value } ) }
						/>
					</PanelRow>

					{ 'excerpt' === content &&
						<PanelRow>
							<ToggleControl
								label={ __( 'Read More Link', 'wordcamporg' ) }
								help={ __( 'Show a link at the end of the excerpt (some themes already include this)', 'wordcamporg' ) }
								checked={ excerpt_more }
								onChange={ ( value ) => setAttributes( { excerpt_more: value } ) }
							/>
						</PanelRow>
					}

					<PanelRow>
						<SelectControl
							label={ __( 'Sort by', 'wordcamporg' ) }
							options={ sortOptions }
							value={ sort_by || 'name_asc' }
							onChange={ ( value ) => setAttributes( { sort_by: value } ) }
							help={ __( 'Configure sponsor levels from the Sponsor -> Order Sponsor Levels page.', 'wordcamporg' ) }
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

export default SponsorInspectorControls;
