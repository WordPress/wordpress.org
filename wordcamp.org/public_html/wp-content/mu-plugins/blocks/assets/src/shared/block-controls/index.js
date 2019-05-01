/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Placeholder }         = wp.components;
const { Component, Fragment } = wp.element;

/**
 * Internal dependencies
 */
import './style.scss';

/**
 * Component for extending that adds common functionality for other block controls components.
 */
export class BlockControls extends Component {
	/**
	 * Run additional operations during component initialization.
	 *
	 * @param {Object} props
	 */
	constructor( props ) {
		super( props );

		this.getModeLabel = this.getModeLabel.bind( this );
	}

	/**
	 * Find a specific mode option and retrieve its label.
	 *
	 * @param {string} value
	 *
	 * @return {string}
	 */
	getModeLabel( value ) {
		const { mode } = this.props.blockData.options;

		return mode.find( ( modeOption ) => {
			return value === modeOption.value;
		} ).label;
	}
}

/**
 * Component for block controls when the block has a specific mode selected.
 *
 * @param {Object} props {
 *     @type {string} className
 *     @type {string} label
 *     @type {string} icon
 *     @type {string} content
 *     @type {Array}  placeholderChildren
 * }
 *
 * @return {Element}
 */
export function PlaceholderSpecificMode( { className, label, icon, content, placeholderChildren } ) {
	const classes = [
		'wordcamp-block-edit-placeholder',
		'wordcamp-block-edit-placeholder-specific-mode',
		className,
	];

	return (
		<Fragment>
			{ content }
			<Placeholder
				className={ classnames( classes ) }
				label={ label }
				icon={ icon }
			>
				{ placeholderChildren }
			</Placeholder>
		</Fragment>
	);
}
