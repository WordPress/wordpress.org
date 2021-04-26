/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

/**
 * Uses a hidden textarea that is added and removed from the DOM in order to copy to clipboard via the Browser.
 *
 * @param {string} stringToCopy A string that will be copied to the clipboard
 * @return {boolean} Whether the copy function succeeded
 */
const copyToClipboard = ( stringToCopy ) => {
	const element = document.createElement( 'textarea' );

	// We don't want the text area to be selected since it's temporary.
	element.setAttribute( 'readonly', '' );

	// We don't want the text area to be visible since it's temporary.
	element.style.position = 'absolute';
	element.style.left = '-9999px';

	element.value = stringToCopy;

	document.body.appendChild( element );
	element.select();

	const success = document.execCommand( 'copy' );
	document.body.removeChild( element );

	return success;
};

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
