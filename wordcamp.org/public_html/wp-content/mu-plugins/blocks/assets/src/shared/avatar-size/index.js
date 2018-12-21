/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { BaseControl, Button, ButtonGroup, RangeControl } = wp.components;
const { withInstanceId } = wp.compose;
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

function AvatarSizeControl( {
	className,
	label,
	help,
	value,
	instanceId,
	onChange,
	initialPosition,
	...props
} ) {
	const id = `wordcamp-inspector-avatar-size-control-${ instanceId }`;

	return (
		<BaseControl
			id={ id }
			className={ classnames( 'wordcamp-components-avatar-size', className ) }
			label={ label }
			help={ help }
		>
			<div className={ 'wordcamp-components-avatar-size-buttons' }>
				<ButtonGroup>
					{ sizePresets.map( ( preset ) => {
						const { name, shortName, size, slug } = preset;
						const isCurrent = value === size;

						return (
							<Button
								key={ slug }
								isLarge
								isPrimary={ isCurrent }
								aria-pressed={ isCurrent }
								onClick={ () => onChange( Number( size ) ) }
							>
								{ shortName || name }
							</Button>
						);
					} ) }
				</ButtonGroup>

				<Button
					className={ 'wordcamp-components-avatar-size-button-reset' }
					isLarge
					isDefault
					onClick={ () => onChange( Number( initialPosition ) ) }
				>
					{ __( 'Reset', 'wordcamporg' ) }
				</Button>
			</div>

			<RangeControl
				className={ 'wordcamp-components-avatar-size-range' }
				value={ value }
				initialPosition={ initialPosition }
				onChange={ onChange }
				beforeIcon={ 'format-image' }
				afterIcon={ 'format-image' }
				{ ...props }
			/>
		</BaseControl>
	);
}

export default withInstanceId( AvatarSizeControl );
