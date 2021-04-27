/**
 * Internal dependencies
 */
import Iframe from '../iframe';

function Canvas( { html } ) {
	const style = {
		width: '100%',
		height: '50vh',
		minHeight: '600px',
		overflowY: 'auto',
	};

	return (
		<div>
			<Iframe
				className="pattern-preview__viewport-iframe"
				style={ style }
				headHTML={ window.__editorStyles.html }
			>
				<div
					dangerouslySetInnerHTML={ {
						__html: html,
					} }
				/>
			</Iframe>
		</div>
	);
}

export default Canvas;
