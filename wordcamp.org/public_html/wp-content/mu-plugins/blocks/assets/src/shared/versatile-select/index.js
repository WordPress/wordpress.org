/**
 * External dependencies
 */
import Select from 'react-select';
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { BaseControl, Button } = wp.components;
const { withInstanceId } = wp.compose;
const { Component } = wp.element;
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import './style.scss';

class VersatileSelect extends Component {
	constructor( props ) {
		super( props );

		this.state = {
			selectedOptions: null,
		};
	}

	render() {
		const { instanceId, className, label, help, onChange, selectProps, submitLabel } = this.props;
		const value = this.state.selectedOptions || this.props.value;
		const id = `wordcamp-block-versatile-select-control-${ instanceId }`;

		return (
			<BaseControl
				id={ id }
				className={ classnames( 'wordcamp-components-versatile-select', className ) }
				label={ label }
				help={ help }
			>
				<div className="wordcamp-components-versatile-select-inner">
					<Select
						id={ id }
						className="wordcamp-components-versatile-select-select"
						value={ value }
						aria-label={ label }
						onChange={ ( selectedOptions ) => {
							this.setState( { selectedOptions } );
						} }
						{ ...selectProps }
					/>
					<Button
						className="wordcamp-components-versatile-select-button"
						isLarge
						isDefault
						onClick={ () => onChange( this.state.selectedOptions ) }
					>
						{ submitLabel || __( 'Select', 'wordcamporg' ) }
					</Button>
				</div>
			</BaseControl>
		);
	}
}

export default withInstanceId( VersatileSelect );
