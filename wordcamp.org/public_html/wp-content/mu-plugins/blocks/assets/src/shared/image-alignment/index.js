/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { BaseControl, Toolbar } = wp.components;

function ImageAlignmentControl( {
	className,
	label,
	help,
	value,
	onChange,
	alignOptions,
} ) {
	return (
		<BaseControl
			className={ classnames( 'wordcamp-components-image-alignment', className ) }
			label={ label }
			help={ help }
		>
			<Toolbar
				controls={ alignOptions.map( ( alignment ) => {
					const isActive = value === alignment.value;
					const iconSlug = `align-${ alignment.value }`;

					return {
						title    : alignment.label,
						icon     : iconSlug,
						isActive : isActive,
						onClick  : () => {
							onChange( alignment.value );
						},
					};
				} ) }
			/>
		</BaseControl>
	);
}

export default ImageAlignmentControl;
