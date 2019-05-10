/**
 * WordPress dependencies
 */
const { Toolbar }       = wp.components;
const { BlockControls } = wp.editor;

/**
 * Component for a toolbar UI to the top of a post list block to change the layout.
 *
 * @param {Object} props {
 *     @type {string}   layout
 *     @type {Array}    options
 *     @type {Function} setAttributes
 * }
 *
 * @return {Element}
 */
export function LayoutToolbar( {
	layout,
	options,
	setAttributes,
} ) {
	const controls = options.map( ( option ) => {
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
