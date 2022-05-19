/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
import { chartBar } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import edit from './edit';

registerBlockType( 'wporg-chart-block/main', {
	title: __( 'Chart Block', 'wporg' ),
	icon: chartBar,
	category: 'widgets',
	attributes: {
		dataURL: {
			type: 'string',
			default: '',
		},
		title: {
			type: 'string',
			default: '',
		},
		notes: {
			type: 'string',
			default: '',
		},
		headings: {
			type: 'string',
			default: '',
		},
		chartType: {
			type: 'string',
			default: '',
		},
		chartOptions: {
			type: 'string',
		},
	},
	edit,
	save: () => null,
} );
