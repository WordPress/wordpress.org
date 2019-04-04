/**
 * External dependencies
 */
import classnames from 'classnames';
import { debounce } from 'lodash';

/**
 * WordPress dependencies
 */
const { Component }                                      = wp.element;
const { BaseControl, Button, ButtonGroup, RangeControl } = wp.components;
const { __, _x } = wp.i18n;

/**
 * Internal dependencies
 */
import './style.scss';

const sizePresets = [
	{
		name      : __( 'Small', 'wordcamporg' ),
		shortName : _x( 'S', 'size small', 'wordcamporg' ),
		size      : 90,
		slug      : 'small',
	},
	{
		name      : __( 'Regular', 'wordcamporg' ),
		shortName : _x( 'M', 'size medium', 'wordcamporg' ),
		size      : 150,
		slug      : 'regular',
	},
	{
		name      : __( 'Large', 'wordcamporg' ),
		shortName : _x( 'L', 'size large', 'wordcamporg' ),
		size      : 300,
		slug      : 'large',
	},
	{
		name      : __( 'Larger', 'wordcamporg' ),
		shortName : _x( 'XL', 'size extra large', 'wordcamporg' ),
		size      : 500,
		slug      : 'larger',
	},
];

class AvatarSizeControl extends Component {
	constructor( props ) {
		super( props );

		this.state = {
			value    : props.value,
			onChange : debounce( props.onChange, 10 ) // higher values lead to a noticeable degradation in visual feedback.
		};

		this.onChange = this.onChange.bind( this );
	}

	onChange( value ) {
		this.setState( { value: value } );
		this.state.onChange( value );
	}

	render() {
		const { className, label, help, initialPosition, rangeProps } = this.props;
		const { value }                                               = this.state;

		return (
			<BaseControl
				className={ classnames( 'wordcamp-components-avatar-size', className ) }
				label={ label }
				help={ help }
			>
				<div className="wordcamp-components-avatar-size-buttons">
					<ButtonGroup aria-label={ label }>
						{ sizePresets.map( ( preset ) => {
							const { name, shortName, size, slug } = preset;
							const isCurrent                       = value === size;

							return (
								<Button
									key={ slug }
									isLarge
									isPrimary={ isCurrent }
									aria-label={ name }
									aria-pressed={ isCurrent }
									onClick={ () => this.onChange( Number( size ) ) }
								>
									{ shortName || name }
								</Button>
							);
						} ) }
					</ButtonGroup>

					<Button
						className="wordcamp-components-avatar-size-button-reset"
						isLarge
						isDefault
						onClick={ () => this.onChange( Number( initialPosition ) ) }
					>
						{ __( 'Reset', 'wordcamporg' ) }
					</Button>
				</div>

				<RangeControl
					className="wordcamp-components-avatar-size-range"
					value={ value }
					initialPosition={ initialPosition }
					onChange={ this.onChange }
					beforeIcon="format-image"
					afterIcon="format-image"
					aria-label={ label }
					{ ...rangeProps }
				/>
			</BaseControl>
		);
	}
}

export default AvatarSizeControl;
