/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { Placeholder, Spinner } = wp.components;
const { Component, Fragment } = wp.element;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import './style.scss';

export class BlockControls extends Component {
	constructor( props ) {
		super();

		this.getModeLabel = this.getModeLabel.bind( this );
	}

	getModeLabel( value ) {
		const { mode } = this.props.blockData.options;

		return mode.find( ( modeOption ) => {
			return value === modeOption.value;
		} ).label;
	}
}

export function PlaceholderNoContent( { className, icon, label, loading } ) {
	const classes = [
		'wordcamp-block-edit-placeholder',
		'wordcamp-block-edit-placeholder-no-content',
		className,
	];

	return (
		<Placeholder
			className={ classnames( classes ) }
			icon={ icon }
			label={ label }
		>
			{ loading ?
				<Spinner /> :
				__( 'No content found.', 'wordcamporg' )
			}
		</Placeholder>
	);
}

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
