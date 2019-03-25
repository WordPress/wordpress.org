/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { edit } from './edit.js';

export const name = 'wordcamp/sessions';

export const settings = {
	title       : __( 'Sessions', 'wordcamporg' ),
	description : __( 'Add a list of sessions.', 'wordcamporg' ),
	icon        : 'list-view',
	category    : 'wordcamp',
	edit,
	save        : function() {
		return null;
	},
};
