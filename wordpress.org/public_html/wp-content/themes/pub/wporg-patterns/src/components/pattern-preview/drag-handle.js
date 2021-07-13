/**
 * External dependencies
 */
import { useDrag } from 'react-use-gesture';

/**
 * WordPress dependencies
 */
import { LEFT, RIGHT } from '@wordpress/keycodes';

function DragHandle( { label, className, onDragChange, onDragEnd, direction = 'left', ...props } ) {
	const dragGestures = useDrag( ( { delta, dragging } ) => {
		const multiplier = direction === 'left' ? -2 : 2;
		if ( dragging ) {
			onDragChange( delta[ 0 ] * multiplier );
		} else {
			onDragEnd();
		}
	} );

	const onKeyDown = ( event ) => {
		const { keyCode } = event;

		if ( ( direction === 'left' && keyCode === LEFT ) || ( direction === 'right' && keyCode === RIGHT ) ) {
			onDragChange( 20 );
		} else if (
			( direction === 'left' && keyCode === RIGHT ) ||
			( direction === 'right' && keyCode === LEFT )
		) {
			onDragChange( -20 );
		}
	};

	return (
		<div className={ `pattern-preview__drag-handle ${ className }` }>
			<button
				className="pattern-preview__drag-handle-button"
				aria-label={ label }
				{ ...props }
				onKeyDown={ onKeyDown }
				onKeyUp={ onDragEnd }
				{ ...dragGestures() }
			/>
		</div>
	);
}

export default DragHandle;
