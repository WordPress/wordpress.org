/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

function Canvas( { url } ) {
	const style = {
		width: '100%',
		height: '50vh',
		minHeight: '600px',
		overflowY: 'auto',
	};

	return (
		<div>
			<iframe
				className="pattern-preview__viewport-iframe"
				title={ __( 'Pattern Preview', 'wporg-patterns' ) }
				tabIndex="-1"
				style={ style }
				src={ url }
			/>
		</div>
	);
}

export default Canvas;
