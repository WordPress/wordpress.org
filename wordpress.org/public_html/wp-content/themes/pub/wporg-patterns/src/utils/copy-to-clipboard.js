/**
 * Uses a hidden textarea that is added and removed from the DOM in order to copy to clipboard via the Browser.
 *
 * @param {string} stringToCopy A string that will be copied to the clipboard
 * @return {boolean} Whether the copy function succeeded
 */
export const copyToClipboard = ( stringToCopy ) => {
	const element = document.createElement( 'textarea' );

	// We don't want the text area to be selected since it's temporary.
	element.setAttribute( 'readonly', '' );

	// We don't want screen readers to read the content since it's pattern markup
	element.setAttribute( 'aria-hidden', 'true' );

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
