/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { edit } from './edit.js';

export const name = 'wordcamp/sessions';
export const LABEL         = __( 'Sessions', 'wordcamporg' );
export const ICON  = 'list-view';

export const settings = {
	title       : __( 'Sessions', 'wordcamporg' ),
	description : __( 'Add a list of sessions.', 'wordcamporg' ),
	icon        : ICON,
	category    : 'wordcamp',
	edit        : edit,
	save        : () => null,
};
