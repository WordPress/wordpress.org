/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import ServerSideRender from '@wordpress/server-side-render';
import { useBlockProps } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import './style.scss';

export default function Edit( { name, attributes, context } ) {
	const blockProps = useBlockProps();
	const { postId } = context;
	return (
		<div { ...blockProps }>
			<ServerSideRender
				block={ name }
				attributes={ attributes }
				skipBlockSupportAttributes
				urlQueryArgs={ { post_id: postId } }
			/>
		</div>
	);
}

registerBlockType( metadata.name, {
	edit: Edit,
	save: () => null,
} );
