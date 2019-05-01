/**
 * WordPress dependencies
 */
const { Toolbar }        = wp.components;
const { BlockControls }  = wp.editor;
const { Component }      = wp.element;

/**
 * Component for adding UI buttons to the top of the block.
 */
class OrganizersToolbar extends Component {
	/**
	 * Render the toolbar.
	 *
	 * @return {Element}
	 */
	render() {
		const { attributes, setAttributes, blockData } = this.props;
		const { layout }                               = attributes;
		const { layout: layoutOptions = {} }           = blockData.options;

		const controls = layoutOptions.map( ( option ) => {
			const icon     = `${ option.value }-view`;
			const isActive = layout === option.value;

			return {
				icon     : icon,
				title    : option.label,
				isActive : isActive,
				onClick  : () => {
					setAttributes( { layout: option.value } );
				},
			};
		} );

		return (
			<BlockControls>
				<Toolbar controls={ controls } />
			</BlockControls>
		);
	}
}

export default OrganizersToolbar;
