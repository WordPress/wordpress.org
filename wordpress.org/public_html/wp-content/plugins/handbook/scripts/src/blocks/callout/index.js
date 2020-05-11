/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import { InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies
 */
import edit from './edit';
import variations from './variations';
import transforms from './transforms';

registerBlockType( 'wporg/callout', {
	title: __( 'Callout', 'wporg' ),
	description: __( 'Callout boxes to be used in handbooks.', 'wporg' ),
	category: 'widgets',
	icon: 'info',
	keywords: [ __( 'alert', 'wporg' ), __( 'tip', 'wporg' ) ],
	attributes: {
		type: {
			type: 'string',
			default: 'info',
		},
	},
	supports: {
		className: false,
	},
	example: {
		attributes: {
			type: 'info',
		},
		innerBlocks: [
			{
				name: 'core/paragraph',
				attributes: {
					content: __(
						'This is the content of the callout boxes.',
						'wporg'
					),
				},
			},
		],
	},
	variations,
	transforms,
	edit,
	save: ( { attributes } ) => {
		const className = `callout callout-${ attributes.type }`;
		return (
			<div className={ className }>
				<InnerBlocks.Content />
			</div>
		);
	},
} );
