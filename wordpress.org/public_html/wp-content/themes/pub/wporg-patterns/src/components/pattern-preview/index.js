/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useCallback, useState } from '@wordpress/element';
import { useInstanceId } from '@wordpress/compose';
import { VisuallyHidden } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Canvas from './canvas';
import DragHandle from './drag-handle';

/* eslint-disable jsx-a11y/anchor-is-valid -- These are just placeholders. */

const INITIAL_WIDTH = 1200;

function PatternPreview( { blockContent } ) {
	const instanceId = useInstanceId( PatternPreview );
	const [ width, setWidth ] = useState( window.innerWidth < INITIAL_WIDTH ? window.innerWidth : INITIAL_WIDTH );
	const onDragChange = useCallback(
		( delta ) => {
			setWidth( ( value ) => value + delta );
		},
		[ setWidth ]
	);

	return (
		<>
			<div className="pattern-preview__viewport" style={ { width } }>
				<DragHandle
					label={ __( 'Drag to resize', 'wporg-patterns' ) }
					className="is-left"
					onDragChange={ onDragChange }
					direction="left"
					aria-describedby={ `pattern-preview__resize-help-${ instanceId }` }
				/>
				<Canvas html={ blockContent } />
				<DragHandle
					label={ __( 'Drag to resize', 'wporg-patterns' ) }
					className="is-right"
					onDragChange={ onDragChange }
					direction="right"
					aria-describedby={ `pattern-preview__resize-help-${ instanceId }` }
				/>
				<VisuallyHidden
					id={ `pattern-preview__resize-help-${ instanceId }` }
					className="pattern-preview__resize-help"
				>
					{ __( 'Use left and right arrow keys to resize the preview.', 'wporg-patterns' ) }
				</VisuallyHidden>
			</div>
		</>
	);
}

export default PatternPreview;
