function photoSubmitLoaded() {

	// Remove query parameters added by Frontend Uploader.
	const photo_upload_url = new URL(document.location);
	if ( photo_upload_url.searchParams.has('response') ) {
		photo_upload_url.searchParams.delete('response');
		history.replaceState( null, null, photo_upload_url) ;
	}

	// Prevent double-submission of upload form.
	const photo_fieldset = document.getElementById('wporg-photo-upload');
	if ( null === photo_fieldset ) {
		return;
	}

	const photo_upload_form = photo_fieldset.querySelector('form');
	if ( null === photo_upload_form ) {
		return;
	}

	const photo_upload_submit   = photo_upload_form.querySelector('input[type="submit"]');
	if ( null === photo_upload_submit ) {
		return;
	}

	const photo_upload_field = document.getElementById('ug_photo');

	if ( photo_upload_field ) {
		// Add custom validation for upload file size.
		photo_upload_field.addEventListener( 'change', e => {
			photoShowFileSizeError( photo_upload_field );
		} );
	}

	// Customize jQuery Validator, if still in use.
	if ( window.jQuery && window.jQuery.validator ) {
		// Customize error message for invalid file mimetype.
		jQuery.validator.messages.accept = PhotoDir.err_invalid_mimetype;
	}

	// Check validity of form fields on submission.
	photo_upload_form.addEventListener( 'submit', e => {
		photoShowFileSizeError( photo_upload_field );
		if ( photo_upload_form.checkValidity() ) {
			photo_upload_submit.disabled = true;
		}
	} );

}

/**
 * Checks if the file selected via the file input field object is within an
 * acceptable file size range and sets custom validity message accordingly.
 *
 * An appropriate error message is defined if the file is too large or too small.
 * If there is no file selected, or the file is of sufficient size, then any
 * existing custom validity message is cleared.
 *
 * @param {Object} field - The HTML file input field object.
 */
function photoCheckFileSize( field ) {
	const MAX_SIZE = PhotoDir.max_file_size; // In bytes.
	const MIN_SIZE = PhotoDir.min_file_size; // In bytes.

	const files = field.files;

	if ( files.length > 0 ) {
		const file_size = files[0].size;

		// Note: If changing the error message for either case, ensure the "// Don't show error message..."
		// regex in `photoShowFileSizeError()` still matches them both.
		if ( file_size >= MAX_SIZE ) {
			field.setCustomValidity( PhotoDir.err_file_too_large );
			return;
		} else if ( file_size <= MIN_SIZE ) {
			field.setCustomValidity( PhotoDir.err_file_too_small );
			return;
		}
	}

	// No custom constraint violation.
	field.setCustomValidity('');
}

/**
 * Handles the display of the error message for the file upload input.
 *
 * If the file input field has a file selected, it is checked to see if it is
 * too large or too small. An appropriate error message is shown in either case.
 * If no file is selected, or the file is of sufficient size, then no error is
 * shown and any existing error for the field is cleared.
 *
 * This pseudo-mimics and also works around the jQuery Validation handling that
 * doesn't play well with custom error reporting like this.
 *
 * @param {Object} field - The HTML file input field object.
 */
function photoShowFileSizeError( field ) {
	// Check field for file size validation errors.
	photoCheckFileSize( field );

	const errorMessage = field.validationMessage;
	const errorId = `${field.id}-error`;
	let errorEl = document.getElementById( errorId );

	// Don't show error message for any other error.
	if ( ! errorMessage || ! field.validity.customError || ! / MB\.$/.test( errorMessage ) ) {
		errorEl?.remove();
		return;
	}

	if ( ! errorEl ) {
		errorEl = document.createElement( 'label' );
		field.after( errorEl );
	}

	errorEl.setAttribute( 'id', errorId );
	errorEl.setAttribute( 'class', 'custom-error' );
	errorEl.setAttribute( 'for', field.id );
	errorEl.removeAttribute( 'style' );
	errorEl.innerHTML = errorMessage;
}

document.addEventListener( 'DOMContentLoaded', () => {
	photoSubmitLoaded();
} );
