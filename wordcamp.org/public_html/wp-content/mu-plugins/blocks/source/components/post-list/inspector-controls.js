/**
 * WordPress dependencies.
 */
const { PanelBody, RangeControl } = wp.components;
const { Component, Fragment }               = wp.element;
const { __ }                                = wp.i18n;

/**
 * Component for a range control that adjusts the number of columns in a post list grid.
 *
 * For use within a block, consider GridInspectorPanel instead unless you want to include this control
 * in a panel with other unrelated controls.
 *
 * @param {Object} props {
 *     @type {number}   grid_columns
 *     @type {Object}   schema
 *     @type {Function} setAttributes
 * }
 *
 * @return {Element}
 */
export function GridColumnsControl( {
	grid_columns,
	schema,
	setAttributes,
} ) {
	const { default: defaultValue = 2, maximum = 4, minimum = 4 } = schema;

	return (
		<RangeControl
			label={ __( 'Grid Columns', 'wordcamporg' ) }
			value={ Number( grid_columns ) }
			min={ minimum }
			max={ maximum }
			initialPosition={ defaultValue }
			onChange={ ( value ) => setAttributes( { grid_columns: value } ) }
		/>
	);
}

/**
 * Component to add an Inspector panel
 *
 * Should be used with rest of the components in this folder. Will use and set attributes `layout` and `grid_columns`.
 */
export class GridInspectorPanel extends Component {
	/**
	 * Render the control.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, setAttributes, blockData } = this.props;
		const { layout, grid_columns } = attributes;
		const { schema } = blockData;

		return (
			<Fragment>
				{ 'grid' === layout &&
					<PanelBody
						title={ __( 'Grid Layout', 'wordcamporg' ) }
						initialOpen={ true }
					>
						<GridColumnsControl
							grid_columns={ grid_columns }
							schema={ schema.grid_columns || {} }
							setAttributes={ setAttributes }
						/>
					</PanelBody>
				}
			</Fragment>
		);
	}
}
