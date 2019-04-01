/**
 * WordPress dependencies
 */
const { Toolbar }        = wp.components;
const { BlockControls }  = wp.editor;
const { Component }      = wp.element;
const { decodeEntities } = wp.htmlEntities;

class OrganizersToolbar extends Component {
	render() {
		const { attributes, setAttributes, blockData } = this.props;
		const { layout }                               = attributes;
		const { layout: layoutOptions = {} }           = blockData.options;

		const controls = layoutOptions.map( ( option ) => {
			const icon     = `${ decodeEntities( option.value ) }-view`;
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
