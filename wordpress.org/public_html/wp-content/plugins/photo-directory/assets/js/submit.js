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

	photo_upload_form.addEventListener( 'submit', e => {
		if ( photo_upload_form.checkValidity() ) {
			photo_upload_submit.disabled = true;
		}
	} );

}

photoSubmitLoaded();