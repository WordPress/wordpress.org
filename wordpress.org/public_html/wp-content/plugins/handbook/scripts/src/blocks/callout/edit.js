/**
 * WordPress dependencies
 */
import { InnerBlocks } from '@wordpress/block-editor';

const CalloutEdit = ( { attributes } ) => {
	const className = `callout callout-${ attributes.type }`;
	return (
		<div className={ className }>
			<InnerBlocks allowedBlocks={ [ 'core/paragraph', 'core/list' ] } />
		</div>
	);
};

export default CalloutEdit;
