/**
 * WordPress dependencies
 */
import { createBlock } from '@wordpress/blocks';

const transforms = {
	// TODO: Implement after https://github.com/WordPress/gutenberg/issues/17758 is fixed.
	/*from: [
		{
			type: 'shortcode',
			tag: 'info',
			attributes: {
				type: 'info'
			},
		},
	],*/
	from: [
		{
			type: 'block',
			blocks: [ 'core/paragraph' ],
			transform: ( { content } ) => {
				const innerBlocks = [
					createBlock( 'core/paragraph', {
						content,
					} ),
				];

				return createBlock(
					'wporg/callout',
					{ type: 'info' },
					innerBlocks
				);
			},
		},
		{
			type: 'block',
			blocks: [ 'core/shortcode' ],
			isMatch: ( attributes ) => {
				const shortcodeRegexp = wp.shortcode.regexp(
					'info|tip|alert|tutorial|warning'
				);
				return shortcodeRegexp.test( attributes.text );
			},
			transform: ( attributes ) => {
				const shortcodeRaw = attributes.text;

				let shortcode = wp.shortcode.next( 'info', shortcodeRaw )
					?.shortcode;
				if ( ! shortcode ) {
					shortcode = wp.shortcode.next( 'tip', shortcodeRaw )
						?.shortcode;
				}
				if ( ! shortcode ) {
					shortcode = wp.shortcode.next( 'alert', shortcodeRaw )
						?.shortcode;
				}
				if ( ! shortcode ) {
					shortcode = wp.shortcode.next( 'tutorial', shortcodeRaw )
						?.shortcode;
				}
				if ( ! shortcode ) {
					shortcode = wp.shortcode.next( 'warning', shortcodeRaw )
						?.shortcode;
				}

				const innerBlocks = [
					createBlock( 'core/paragraph', {
						content: shortcode.content,
					} ),
				];

				return createBlock(
					'wporg/callout',
					{ type: shortcode.tag },
					innerBlocks
				);
			},
		},
	],
	to: [
		{
			type: 'block',
			blocks: [ 'core/paragraph' ],
			transform: ( attributes, innerBlocks ) => {
				return innerBlocks;
			},
		},
		// TODO: Transform to other callout types: https://github.com/WordPress/gutenberg/issues/20584
	],
};

export default transforms;
