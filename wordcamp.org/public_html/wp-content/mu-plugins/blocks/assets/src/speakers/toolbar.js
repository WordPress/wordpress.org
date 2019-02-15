/**
 * WordPress dependencies
 */
const { Toolbar } = wp.components;
const { BlockControls } = wp.editor;
const { Component } = wp.element;

const data = window.WordCampBlocks.speakers || {};

class SpeakersToolbar extends Component {
	render() {
		const { attributes, setAttributes } = this.props;
		const { layout } = attributes;
		const { layout: layoutOptions = {} } = data.options;

		return (
			<BlockControls>
				<Toolbar
					controls={ layoutOptions.map( ( option ) => {
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
					} ) }
				/>
			</BlockControls>
		);
	}
}

export default SpeakersToolbar;
