/**
 * WordPress dependencies
 */
const { Toolbar } = wp.components;
const { BlockControls } = wp.editor;
const { __ } = wp.i18n;
const { Component } = wp.element;

/**
 * Add option to select between grid and list layout.
 * This just adds the "grid" and "list" button in block toolbar, functionality still needs to be connected to it separately. Other components in this folder can be used to provide functionality.
 *
 * Sets attribute `layout` to `grid` / `list`. Also sets `grid_columns` to 2 for `grid`, and 1 for `list`.
 */
class GridToolbar extends Component {
	/**
	 * Render the toolbar.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, setAttributes } = this.props;
		const { layout } = attributes;
		const layoutOptions = [
			{
				value    : 'grid',
				label    : __( 'Grid', 'wordcamporg' ),
				isActive : layout === 'grid',
			},
			{
				value    : 'list',
				label    : __( 'List', 'wordcamporg' ),
				isActive : layout === 'grid',
			},
		];

		return (
			<BlockControls>
				<Toolbar
					controls={ layoutOptions.map( ( option ) => {
						const icon = `${ option.value }-view`;
						const isActive = layout === option.value;

						return {
							icon     : icon,
							title    : option.label,
							isActive : isActive,
							onClick  : () => {
								setAttributes(
									{
										layout       : option.value,
										grid_columns : option.value === 'grid' ? 2 : 1,
									}
								);
							},
						};
					} ) }
				/>
			</BlockControls>
		);
	}
}

export default GridToolbar;
