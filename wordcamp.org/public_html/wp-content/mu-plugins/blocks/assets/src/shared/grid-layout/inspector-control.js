/**
 * WordPress dependencies.
 */
const { Component } = wp.element;
const { PanelBody, PanelRow, RangeControl } = wp.components;
const { __ } = wp.i18n;

const DEFAULT_SCHEMA = {
	grid_columns: {
		default : 2,
		minimum : 2,
		maximum : 4,
	},
};
/**
 * Add a slider for increasing and decreasing columns. Should be used with rest of the components in this folder. Will use and set attributes `layout` and `gird_columns`.
 */
class GridInspectorControl extends Component {
	render() {
		const { attributes, setAttributes } = this.props;
		const { layout, grid_columns } = attributes;

		if ( 'grid' !== layout ) {
			return null;
		}
		const schema = DEFAULT_SCHEMA;

		return (
			<PanelBody>
				<PanelBody
					title={ __( 'Layout', 'wordcamporg' ) }
					initialOpen={ true }
				>
					<PanelRow>
						<RangeControl
							label={ __( 'Grid Columns', 'wordcamporg' ) }
							value={ Number( grid_columns ) }
							min={ schema.grid_columns.minimum }
							max={ schema.grid_columns.maximum }
							initialPosition={ schema.grid_columns.default }
							onChange={ ( option ) => setAttributes(
								{ grid_columns: option } ) }
						/>
					</PanelRow>
				</PanelBody>
			</PanelBody>
		);
	}
}

export default GridInspectorControl;
