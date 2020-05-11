/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

const variations = [
	{
		name: 'info',
		isDefault: true,
		title: __( 'Info Callout', 'wporg' ),
		icon: 'info',
		attributes: { type: 'info' },
	},
	{
		name: 'tip',
		title: __( 'Tip Callout', 'wporg' ),
		icon: 'lightbulb',
		attributes: { type: 'tip' },
	},
	{
		name: 'alert',
		title: __( 'Alert Callout', 'wporg' ),
		icon: 'flag',
		attributes: { type: 'alert' },
	},
	{
		name: 'tutorial',
		title: __( 'Tutorial Callout', 'wporg' ),
		icon: 'hammer',
		attributes: { type: 'tutorial' },
	},
	{
		name: 'warning',
		title: __( 'Warning Callout', 'wporg' ),
		icon: 'dismiss',
		attributes: { type: 'warning' },
	},
];

export default variations;
