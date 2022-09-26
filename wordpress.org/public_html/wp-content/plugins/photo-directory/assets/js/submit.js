/**
 * Initializes things related to the photo upload form.
 */
function photoSubmitInit() {

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
			photoShowFileErrors( photo_upload_field );
		} );
	}

	// Disable jQuery Validation, if still in use.
	if ( window.jQuery && window.jQuery.validator ) {
		jQuery( photo_upload_form ).validate().settings.ignore = "*";
	}

	// Show/remove error when any form field value is changed.
	photoGetInputFields( photo_upload_form ).forEach( (input, i) => {
		input.addEventListener( 'change', e => {
			photoRemoveFieldError( e.target );
		} );
	} );

	// Check validity of form fields on submission.
	photo_upload_form.addEventListener( 'submit', e => {
		e.preventDefault();
		PhotoDir.doingSubmit = true;

		const photo_upload_form = e.target;
		const photo_upload_input = document.getElementById( 'ug_photo' );

		// Clear any server notices from a previous submit.
		document.querySelector( '.ugc-notice' )?.remove();

		// Disable submit button.
		photoSetSubmitButtonDisabled( photo_upload_form, true );

		// Show errors for fields that aren't the file input.
		photoShowFieldErrors( photo_upload_form );

		// Show errors for file input field.
		photoShowFileErrors( photo_upload_input );

		// Scroll top of form into view.
		document.getElementById( 'wporg-photo-upload' ).scrollIntoView( true );

		if ( photo_upload_form.checkValidity() ) {
			photo_upload_form.submit();
		}

		return false;
	} );

}

/**
 * Returns the input fields of interest for generic validation.
 *
 * Fields that have custom validation should be listed here as exclusions.
 *
 * @param {HTMLElement} form - The form.
 * @returns {HTMLElement[]} Array of input fields.
 */
function photoGetInputFields( form ) {
	return form.querySelectorAll( 'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="file"])');
}

/**
 * Sets the 'disabled' attribute for the submit button of a form.
 *
 * @param {HTMLElement} form - The form element.
 * @param {Boolean} state - True if the button should be disabled, else false.
 */
function photoSetSubmitButtonDisabled( form, state ) {
	const submitButton = form.querySelector('input[type="submit"]');

	submitButton.disabled = !!state;
}

/**
 * Shows errors for invalid input fields and remove previously reported errors
 * for valid input fields.
 *
 * Does not handle input that are:
 * - hidden
 * - buttons
 * - file
 *
 * @param {HTMLElement} form - A form.
 */
function photoShowFieldErrors( form ) {
	photoGetInputFields( form ).forEach( (input, i) => {
		photoShowFieldError( input );
	} );
}

/**
 * Removes an error message for the given input.
 *
 * @param {HTMLElement} field - An input field.
 */
function photoRemoveFieldError( field ) {
	field.parentNode.querySelectorAll( '.' + PhotoDir.error_class ).forEach( errorEl => errorEl.remove() );
	field.parentNode.querySelectorAll( '.processing' ).forEach( errorEl => errorEl.remove() );
}

/**
 * Shows the error if the input field is invalid, or removes a previously
 * reported error if the input field is valid.
 *
 * @param {HTMLElement} field - An input field.
 * @returns {String} Error message, if any.
 */
function photoShowFieldError( field ) {
	photoRemoveFieldError( field );

	let errorMessage = field.validationMessage;
	const stillProcessing = errorMessage === PhotoDir.msg_validating_dimensions;
	const cssClass = stillProcessing ? 'processing' : PhotoDir.error_class;

	if ( ! errorMessage ) {
		return '';
	}

	// Set custom error messages.
	if ( field.validity.valueMissing ) {
		errorMessage = PhotoDir.err_field_required;
	}

	if ( PhotoDir.doingSubmit || ! stillProcessing ) {
		errorEl = document.createElement( 'span' );
		errorEl.setAttribute( 'class', cssClass );
		errorEl.innerHTML = errorMessage;
		field.insertAdjacentElement( 'afterend', errorEl );
	}

	// Show an error and end pending submission state unless still processing.
	if ( ! stillProcessing ) {
		PhotoDir.doingSubmit = false;
		photoSetSubmitButtonDisabled( field.closest( 'form' ), false );
	}

	return '';
}

/**
 * Validates a file upload against multiple criteria.
 *
 * @param {HTMLElement} field - The HTML file input field element.
 * @return {Boolean} True if an error was encountered, else false.
 */
async function photoCheckFileValidations( field ) {
	let error = false;

	// Check if no file chosen.
	if ( ! error ) {
		error = field.validity.valueMissing;
	}

	// Check for file size error.
	if ( ! error ) {
		error = photoCheckFileSize( field );
	}

	// Check for file dimension error.
	if ( ! error ) {
		// Hack: Wait for file dimensions check to complete before
		// determining true validity. Once it has done so, it will
		// potentially trigger submit if warranted.
		field.setCustomValidity( PhotoDir.msg_validating_dimensions );
		error = true;
		photoCheckFileDimensions( field );
	}

	return error;
}

/**
 * Checks if the file selected via the file input field object is within an
 * acceptable file dimension range and sets custom validity message accordingly.
 *
 * An appropriate error message is defined if the file is too long or too short.
 * If there is no file selected, or the file is of sufficient size, then any
 * existing custom validity message is cleared.
 *
 * @param {HTMLElement} field - The HTML file input field element.
 * @return {Promise} Promise where result is true if file dimensions are invalid, else false.
 */
function photoCheckFileDimensions( field ) {
	const MIN_SIZE = PhotoDir.min_file_dimension; // In px.
	const MAX_SIZE = PhotoDir.max_file_dimension; // In px.

	const files = field.files;

	if ( files.length > 0 ) {
		const reader = new FileReader();

		reader.addEventListener( 'load', async (e) => {
			const img = new Image();
			img.src = e.target.result;

			img.decode().then( () => {
				let file_width = img.width;
				let file_height = img.height;

				if ( file_height > MAX_SIZE || file_width > MAX_SIZE ) {
					field.setCustomValidity( PhotoDir.err_file_too_long );
				} else if ( file_height < MIN_SIZE || file_width < MIN_SIZE ) {
					field.setCustomValidity( PhotoDir.err_file_too_short );
				} else {
					// No custom constraint violation.
					field.setCustomValidity( '' );
				}

				photoShowFileError( field );
			} );
		}, false );

		reader.readAsDataURL( files.item( 0 ) );

		// Return true. This will be rectified once the actual image dimensions are checked.
		return true;
	}

	// No custom constraint violation.
	field.setCustomValidity( '' );
	return false;
}

/**
 * Checks if the file selected via the file input field object is within an
 * acceptable file size range and sets custom validity message accordingly.
 *
 * An appropriate error message is defined if the file is too large or too small.
 * If there is no file selected, or the file is of sufficient size, then any
 * existing custom validity message is cleared.
 *
 * @param {HTMLElement} field - The HTML file input field element.
 * @return {Boolean} True if file size is invalid, else false.
 */
function photoCheckFileSize( field ) {
	const MAX_SIZE = PhotoDir.max_file_size; // In bytes.
	const MIN_SIZE = PhotoDir.min_file_size; // In bytes.

	const files = field.files;

	if ( files.length > 0 ) {
		const file_size = files[0].size;

		if ( file_size >= MAX_SIZE ) {
			field.setCustomValidity( PhotoDir.err_file_too_large );
			return true;
		} else if ( file_size <= MIN_SIZE ) {
			field.setCustomValidity( PhotoDir.err_file_too_small );
			return true;
		}
	}

	// No custom constraint violation.
	field.setCustomValidity('');
	return false;
}

/**
 * Shows error messages for the file upload input.
 *
 * @param {HTMLElement} field - The HTML file input field element.
 * @return {Promise}
 */
async function photoShowFileErrors( field ) {
	// Checks custom file input validation. A custom validity error message gets
	// set on the field if a validation fails.
	photoCheckFileValidations( field ).then( () => {
		photoShowFileError( field )
	});
}

/**
 * Handles the display of the error message for the file upload input.
 *
 * @param {HTMLElement} field - The HTML file input field element.
 * @return {boolean} True if no error was shown, false if error was shown.
 */
function photoShowFileError( field ) {
	const errorMessage = field.validationMessage;

	// Remove any existing error message.
	photoRemoveFieldError( field );

	// Hack: If this gets called and everything validates, the form can be
	// submitted.
	const upload_form = field.closest( 'form' );
	if ( PhotoDir.doingSubmit ) {
		if ( upload_form.checkValidity() ) {
			upload_form.submit();
			return;
		} else {
			document.getElementById( 'wporg-photo-upload' ).scrollIntoView( true );
			photoSetSubmitButtonDisabled( field.closest( 'form' ), false );
		}
	}

	// Return if no legitimate custom error to report.
	if ( ! errorMessage ) {
		return;
	}

	photoShowFieldError( field );
}

document.addEventListener( 'DOMContentLoaded', () => {
	photoSubmitInit();
} );
