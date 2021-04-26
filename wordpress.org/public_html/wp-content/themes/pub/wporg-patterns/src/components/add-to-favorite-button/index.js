/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

const AddToFavoriteButton = () => {
	return <button className="button">{ __( 'Add to favorites', 'wporg-patterns' ) }</button>;
};

export default AddToFavoriteButton;
