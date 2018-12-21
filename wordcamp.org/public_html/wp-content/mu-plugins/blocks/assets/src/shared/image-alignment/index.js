/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
const { BaseControl, ButtonGroup, IconButton } = wp.components;
const { withInstanceId } = wp.compose;

/**
 * Internal dependencies
 */
import './style.scss';

const data = window.WordCampBlocks.speakers || {};

function ImageAlignmentControl( {
	className,
	label,
	help,
	value,
	instanceId,
	onChange,
} ) {
	const { options } = data;
	const id = `wordcamp-inspector-image-alignment-control-${ instanceId }`;

	return (
		<BaseControl
			id={ id }
			className={ classnames( 'wordcamp-components-image-alignment', className ) }
			label={ label }
			help={ help }
		>
			<ButtonGroup>
				{ options.align.map( ( alignment ) => {
					const optLabel = alignment.label;
					const optValue = alignment.value;
					const isCurrent = value === optValue;
					const iconSlug = `align-${ optValue }`;

					return (
						<IconButton
							key={ optValue }
							isLarge
							isPrimary={ isCurrent }
							aria-pressed={ isCurrent }
							onClick={ () => onChange( optValue ) }
							icon={ iconSlug }
							label={ optLabel }
						/>
					);
				} ) }
			</ButtonGroup>
		</BaseControl>
	);
}

export default withInstanceId( ImageAlignmentControl );
