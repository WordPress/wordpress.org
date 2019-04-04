/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { edit } from './edit.js';

export const name = 'wordcamp/speakers';
export const LABEL         = __( 'Speakers', 'wordcamporg' );
export const ICON  = 'megaphone';

export const settings = {
	title       : __( 'Speakers', 'wordcamporg' ),
	description : __( 'Add a list of speakers.', 'wordcamporg' ),
	icon        : ICON,
	category    : 'wordcamp',
	edit,
	save        : function() {
		return null;
	},
};
