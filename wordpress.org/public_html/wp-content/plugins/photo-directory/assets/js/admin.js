document.addEventListener('DOMContentLoaded', function () {
	// Convert Orientations taxonomy checkboxes to radio buttons.
	document.querySelectorAll('#photo_orientationchecklist input[type="checkbox"]').forEach((item) => {item.setAttribute('type', 'radio');});
}, false);
