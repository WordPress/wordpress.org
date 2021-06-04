/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useCallback, useMemo, useState } from '@wordpress/element';
import { useInstanceId, useViewportMatch } from '@wordpress/compose';
import { SelectControl, VisuallyHidden } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Canvas from './canvas';
import DragHandle from './drag-handle';

/* eslint-disable jsx-a11y/anchor-is-valid -- These are just placeholders. */

const INITIAL_WIDTH = 960;

function PatternPreview( { blockContent } ) {
	const showViewportControl = useViewportMatch( 'mobile', '>=' );
	const showViewportControlDefault = useViewportMatch( 'large', '>=' );
	const showViewportControlLarge = useViewportMatch( 'wide', '>=' );

	const instanceId = useInstanceId( PatternPreview );
	const [ width, setWidth ] = useState( window.innerWidth < INITIAL_WIDTH ? window.innerWidth : INITIAL_WIDTH );
	const onDragChange = useCallback( ( delta ) => setWidth( ( value ) => value + delta ), [ setWidth ] );

	const availableWidths = useMemo( () => {
		// Less than 480 wide.
		if ( ! showViewportControl ) {
			return [];
		}
		if ( showViewportControlLarge ) {
			// More than 1280 wide.
			return [
				{ label: 'Full (1200px)', value: 1200 },
				{ label: 'Default (960px)', value: 960 },
				{ label: 'Medium (480px)', value: 480 },
				{ label: 'Narrow (320px)', value: 320 },
			];
		} else if ( showViewportControlDefault ) {
			// Less than 1280, more than 960.
			return [
				{ label: 'Default (960px)', value: 960 },
				{ label: 'Medium (480px)', value: 480 },
				{ label: 'Narrow (320px)', value: 320 },
			];
		}
		// Less than 960, but larger than 480.
		return [
			{ label: __( 'Medium (480px)', 'wporg-patterns' ), value: 480 },
			{ label: __( 'Narrow (320px)', 'wporg-patterns' ), value: 320 },
		];
	}, [ showViewportControl, showViewportControlDefault, showViewportControlLarge ] );

	let currentOpt = false;
	if ( ! availableWidths.some( ( opt ) => opt.value === width ) ) {
		currentOpt = {
			/* translators: %s is the width in pixels, ex 600. */
			label: sprintf( __( 'Current (%spx)', 'wporg-patterns' ), width ),
			value: width,
		};
	}

	return (
		<>
			<div className="pattern-preview__size-control">
				{ showViewportControl && (
					<SelectControl
						hideLabelFromVision
						label={ __( 'Preview Width', 'wporg-patterns' ) }
						value={ width }
						options={ currentOpt ? [ currentOpt, ...availableWidths ] : availableWidths }
						onChange={ ( value ) => setWidth( Number( value ) ) }
					/>
				) }
			</div>
			<div className="pattern-preview__viewport" style={ { width: width + 40 } }>
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
