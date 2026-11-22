/* Legacy wptv2 upload behavior, scoped to the fixed submission form. */
/* global jQuery */
jQuery(($) => {
	'use strict';
	const form = document.getElementById('video-upload-form');
	if (!form) return;
	const $form = $(form);
	const $file = $('#wptv_file');
	const $event = $('#wptv_event');
	const $progress = $('#upload-progress');
	const $status = $progress.find('.status');
	const $abort = $progress.find('.abort');
	let activeRequest = null;

	$('#wptv_video_upload').prop('hidden', false);
	// Keep lengths in sync with Submissions\field_lengths().
	$form.find('input[type="text"]').prop('maxLength', 100);
	$('#wptv_slides_url').prop('maxLength', 200);
	$('#wptv_video_description').prop('maxLength', 1500);
	$form.find('.cats-checkboxes input').prop('disabled', false);
	$form.find('.cats-checkboxes').each(function () {
		const list = $(this);
		list.find(`input:checked, #in-category-${form.dataset.world}`).each(function () {
			$(this).closest('li').prependTo(list);
		});
	});

	$('#wptv_video_wordcamp').on('change', function () {
		$(`#in-category-${form.dataset.wordcamp}`).prop('checked', this.checked);
	});
	$('#wptv_video_wordcamp, .location input, .cats input, #wptv_date').on('change', () => {
		if ($event.data('user-altered')) return;
		let title = $('.location input:checked').parent().text().trim() + ' ' + $('#wptv_date').val().substring(0, 4);
		if (!title.trim()) return;
		const named = (form.dataset.named || '').split(',').filter(Boolean).map((id) => `#in-category-${id}:checked`).join(',');
		const prefix = $('#wptv_video_wordcamp').prop('checked') ? 'WordCamp'
			: named && $form.find(named).length ? $form.find(named).parent().text().trim() : 'WordPress Meetup';
		title = prefix + ' ' + title;
		$event.val(title.trim());
	});
	$event.on('focus', () => $event.data('user-altered', true));
	$('#wptv_speakers').on('change', function () {
		this.value = this.value.replace(/\s(and|&|\+)\s/g, ', ').replace(/[ ]{2,}/g, ' ');
	});
	$form.on('input change', '.invalid', function () {
		$(this).removeClass('invalid').removeAttr('aria-invalid');
	});
	$form.on('submit', (event) => {
		const invalid = [];
		const check = (selector, valid) => {
			const input = $form.find(selector);
			if (input.length && !valid(input.val())) {
				input.addClass('invalid').attr('aria-invalid', 'true');
				invalid.push(input[0]);
			}
		};
		check('#wptv_uploaded_by', (value) => !!value);
		check('#wptv_email', (value) => /\S+@\S+\.\S+/.test(value));
		// Keep extensions in sync with Submissions\save().
		check('#wptv_file', (value) => /\.(avi|mov|qt|mpeg|mpg|mpe|mp4|m4v|wmv|ogv|3gp|3g2)$/i.test(value));
		check('#wptv_honey', (value) => !value.length);
		if (invalid.length) {
			event.preventDefault();
			invalid[0].scrollIntoView({ block: 'center' });
			invalid[0].focus();
			return;
		}
		if (typeof XMLHttpRequest !== 'undefined' && typeof FormData !== 'undefined') {
			event.preventDefault();
			upload();
		}
	});

	function enableForm() {
		$form.find('input,select,option,textarea').prop('disabled', false);
		$form.find('p.last').show();
		$abort.hide();
		activeRequest = null;
	}
	function upload() {
		const payload = new FormData(form);
		const xhr = new XMLHttpRequest();
		const started = Date.now();
		activeRequest = xhr;
		$form.find('input,select,option,textarea').prop('disabled', true);
		$form.find('p.last').hide();
		$progress.prop('hidden', false);
		$progress.find('progress').val(0);
		$progress.find('.percent').text('0%');
		$status.text('Preparing upload..');
		$abort.show();
		xhr.upload.addEventListener('progress', (event) => {
			if (!event.lengthComputable || !event.total) return;
			const percent = Math.round(event.loaded / event.total * 100);
			const elapsed = Math.max((Date.now() - started) / 1000, 0.001);
			const speed = event.loaded / elapsed / 1024;
			const seconds = speed ? Math.round((event.total - event.loaded) / 1024 / speed) : 0;
			const eta = elapsed > 10 || percent > 5 ? `${Math.floor(seconds / 60)}m ${seconds % 60}s` : 'Calculating..';
			const rate = speed > 1024 ? `${(speed / 1024).toFixed(2)}mb/s` : `${Math.round(speed)}kb/s`;
			$progress.find('progress').val(percent);
			$progress.find('.percent').text(`${percent}%`);
			$status.text(`Uploaded ${(event.loaded / 1048576).toFixed(1)}MB of ${(event.total / 1048576).toFixed(1)}MB. ${rate} Remaining: ${eta}`);
			if (percent >= 100) {
				$status.text('Processing upload.. please wait..');
				$abort.hide();
			}
		});
		xhr.addEventListener('load', () => {
			try {
				const url = new URL(xhr.responseText.trim());
				if (xhr.status < 200 || xhr.status >= 300 || url.origin !== location.origin || url.pathname !== location.pathname) throw new Error('Invalid upload response');
				$status.text('Done.. please wait..');
				location.assign(url.href);
			} catch {
				$status.text('Upload failed. The server returned an unexpected response.');
				enableForm();
			}
		});
		xhr.addEventListener('error', () => {
			$status.text('Upload failed. Network or Browser issue encountered.');
			enableForm();
		});
		xhr.addEventListener('abort', () => {
			enableForm();
			$status.text('Aborted.');
		});
		xhr.withCredentials = true;
		xhr.open(form.method, form.dataset.xhrAction);
		xhr.send(payload);
	}
	$abort.on('click', (event) => {
		event.preventDefault();
		activeRequest?.abort();
	});
});
