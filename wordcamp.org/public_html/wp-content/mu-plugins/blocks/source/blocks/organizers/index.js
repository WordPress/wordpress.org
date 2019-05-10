/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { Edit } from './edit';

export const NAME  = 'wordcamp/organizers';
export const LABEL = __( 'Organizers', 'wordcamporg' );
export const ICON  = 'groups';

const supports = {
	align: [ 'wide', 'full' ],
};

export const SETTINGS = {
	title       : __( 'Organizers', 'wordcamporg' ),
	description : __( 'Add a list of organizers.', 'wordcamporg' ),
	icon        : ICON,
	category    : 'wordcamp',
	supports    : supports,
	edit        : Edit,
	save        : () => null,
};
