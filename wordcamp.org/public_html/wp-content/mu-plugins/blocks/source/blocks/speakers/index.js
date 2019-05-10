/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { Edit } from './edit.js';

export const NAME  = 'wordcamp/speakers';
export const LABEL = __( 'Speakers', 'wordcamporg' );
export const ICON  = 'megaphone';

const supports = {
	align: [ 'wide', 'full' ],
};

export const SETTINGS = {
	title       : __( 'Speakers', 'wordcamporg' ),
	description : __( 'Add a list of speakers.', 'wordcamporg' ),
	icon        : ICON,
	category    : 'wordcamp',
	supports    : supports,
	edit        : Edit,
	save        : () => null,
};
