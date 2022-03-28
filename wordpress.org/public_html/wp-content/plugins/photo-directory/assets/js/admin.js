document.addEventListener('DOMContentLoaded', function () {
	// Convert Orientations taxonomy checkboxes to radio buttons.
	document.querySelectorAll('#photo_orientationchecklist input[type="checkbox"]').forEach((item) => {item.setAttribute('type', 'radio');});

	// Unblur blurred photo on photo edit page when clicked.
	function unblurPhoto(event) {
		event.target.classList.remove('blurred');
		event.target.removeEventListener('click', unblurPhoto, true);
		event.preventDefault();
	}
	document.querySelector('#photos_photo .photos-photo-link img.blurred').addEventListener('click', unblurPhoto, true);

}, false);
