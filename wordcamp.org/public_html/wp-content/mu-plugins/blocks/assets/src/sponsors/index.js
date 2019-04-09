/**
 * WordPress dependencies
 */
const { __ } = wp.i18n;

/**
 * Internal dependencies
 */
import { edit } from './edit.js';

export const name  = 'wordcamp/sponsors';
export const LABEL = __( 'Sponsors', 'wordcamporg' );
export const ICON  = 'heart';

export const settings = {
	title       : __( 'Sponsors', 'wordcamporg' ),
	description : __( "We wouldn't have WordCamp without their support.", 'wordcamporg' ),
	icon        : ICON,
	category    : 'wordcamp',
	edit        : edit,
	save        : () => null,
};
