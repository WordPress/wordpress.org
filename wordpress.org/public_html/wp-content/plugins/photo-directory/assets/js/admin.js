document.addEventListener('DOMContentLoaded', function () {
	// Convert Orientations taxonomy checkboxes to radio buttons.
	document.querySelectorAll('#photo_orientationchecklist input[type="checkbox"]').forEach((item) => {item.setAttribute('type', 'radio');});

	// Unblur blurred photo on photo edit page when clicked.
	function unblurPhoto(event) {
		event.target.classList.remove('blurred');
		event.target.removeEventListener('click', unblurPhoto, true);
		event.preventDefault();
	}
	document.querySelector('#photos_photo .photos-photo-link img.blurred')?.addEventListener('click', unblurPhoto, true);

	// Hide 'Post submitted' admin notice if post publication was actually blocked due to missing taxonomies.
	const successNoticeAlongsideMissingTerms = document.querySelector('.notice-missing-taxonomies ~ #message.notice-success');
	if (successNoticeAlongsideMissingTerms) {
		successNoticeAlongsideMissingTerms.hidden = true;
	}

	// Hide 'Orientations' metabox when a value is assigned.
	//  Orientation shouldn't need direct assignment or changing, so don't show it (as long as a value was set).
	const orientation_value = document.querySelector('input[name="tax_input[photo_orientation][]"]:checked' );
	if ( orientation_value ) {
		document.getElementById('photo_orientationdiv').hidden = true;
	}
}, false);
