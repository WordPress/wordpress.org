/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { copyToClipboard } from '../../utils';

const CopyPatternButton = ( { onSuccess } ) => {
	const handleClick = ( { target } ) => {
		// Grab the pattern markup from hidden input
		const blockData = document.getElementById( 'block-data' );
		const blockPattern = JSON.parse( decodeURIComponent( blockData.value ) );

		const success = copyToClipboard( blockPattern );

		// Make sure we reset focus in case it was lost in the 'copy' command.
		target.focus();

		if ( success ) {
			onSuccess();
		} else {
			// TODO Handle error case
		}
	};

	return (
		<Button isPrimary onClick={ handleClick }>
			{ __( 'Copy Pattern', 'wporg-patterns' ) }
		</Button>
	);
};

export default CopyPatternButton;
