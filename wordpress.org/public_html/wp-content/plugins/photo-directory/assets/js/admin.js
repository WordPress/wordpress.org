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

	// Highlight custom taxonomies lacking terms.
	highlightCustomTaxonomiesWithoutTerms();
}, false);

/**
 * Highlights when a custom taxonomy lacks at least one term assignment.
 */
function highlightCustomTaxonomiesWithoutTerms() {
	// Highlight/unhighlight custom taxonomies lacking any term assignments.
	const customTaxMetaboxes = [
		['photo_categorydiv', 'tax_input[photo_category][]', 'checkbox'],
		['photo_colordiv', 'tax_input[photo_color][]', 'checkbox'],
		['photo_orientationdiv', 'tax_input[photo_orientation][]', 'checkbox'],
		['tagsdiv-photo_tag', 'newtag[photo_tag]', 'text', '.tagchecklist'],
	];

	customTaxMetaboxes.forEach(element => {
		// Decide on highlight initially.
		setMetaboxHighlight(element);

		// Listen for changes in value to re-determine highlight.
		document.querySelectorAll('input[name="' + element[1] + '"]').forEach(item => {
			item.addEventListener('input', customTaxChangeCB);
		});

		// The tag list is handled differently. Re-determine as tags are added/removed.
		if (element[3]) {
			const observer = new MutationObserver(function(mutations_list) {
				mutations_list.forEach(function(mutation) {
					if (mutation.addedNodes.length > 0 || mutation.removedNodes.length > 0) {
						setMetaboxHighlight(element);
					}
				});
			});

			observer.observe(document.querySelector(element[3]), { subtree: false, childList: true });
		}
	});

	// Callback to call setMetaboxHighlight() for proper event targets.
	function customTaxChangeCB(e) {
		const name = e.target.name;
		let customTaxMetabox = customTaxMetaboxes.find(n => { return n[1] === name; });
		if (!customTaxMetabox) {
			return;
		}
		setMetaboxHighlight(customTaxMetabox)
	}

	// Highlights or unhighlights a metabox based on presence of terms.
	function setMetaboxHighlight(customTaxMetabox) {
		[metaboxID, inputName, inputType, tagListClass] = customTaxMetabox;
		const missingTaxClass = 'photo-missing-taxonomy';
		const div = document.getElementById(metaboxID);

		let selector = 'input[name="' + inputName + '"]';
		if ('checkbox' === inputType) {
			selector += ':checked';
		}
		let value = document.querySelector(selector)?.value;
		// If tagListClass is present, see if it has any values if one not already found.
		if (!value && tagListClass) {
			value = document.querySelector(tagListClass).hasChildNodes();
		}

		value ? div?.classList.remove(missingTaxClass) : div?.classList.add(missingTaxClass);
	}
}
