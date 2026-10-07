/**
 * Subtitles Upload Nonce Refresh Handler.
 *
 * Fetches a fresh nonce before submission to bypass page cache staleness.
 */
( function () {
	const form = document.getElementById( 'video-upload-form' );
	const nonceField = document.getElementById( 'wptv-upload-subtitles-nonce' );

	if ( ! form || ! nonceField || ! window.wptvSubtitlesConfig?.ajaxUrl ) {
		return;
	}

	// WordPress nonces expire after 12–24 hours; refresh if older than 10 hours.
	const NONCE_TTL_MS = 10 * 60 * 60 * 1000;

	let refreshPromise = null;
	let lastRefreshedAt = 0;
	let isSubmitting = false;

	/**
	 * Checks whether the current nonce is still fresh.
	 *
	 * @return {boolean}
	 */
	function isNonceFresh() {
		return lastRefreshedAt > 0 && ( Date.now() - lastRefreshedAt < NONCE_TTL_MS );
	}

	/**
	 * Requests a new nonce from the server via AJAX.
	 *
	 * @return {Promise<string|null>} Resolves with the new nonce or null on failure.
	 */
	function refreshNonce() {
		if ( isNonceFresh() ) {
			return Promise.resolve( nonceField.value );
		}

		if ( refreshPromise ) {
			return refreshPromise;
		}

		const requestUrl = new URL( window.wptvSubtitlesConfig.ajaxUrl, window.location.origin );
		requestUrl.searchParams.set( 'action', 'wptv_get_subtitles_nonce' );
		requestUrl.searchParams.set( '_', Date.now().toString() );

		refreshPromise = fetch( requestUrl.toString(), {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Network response was not ok' );
				}
				return response.json();
			} )
			.then( function ( data ) {
				if ( data?.success && data?.data?.nonce ) {
					nonceField.value = data.data.nonce;
					lastRefreshedAt = Date.now();
					return data.data.nonce;
				}
				return null;
			} )
			.catch( function () {
				return null;
			} )
			.finally( function () {
				refreshPromise = null;
			} );

		return refreshPromise;
	}

	form.addEventListener( 'focusin', function () {
		refreshNonce();
	}, { once: true } );

	form.addEventListener( 'submit', function ( e ) {
		if ( isNonceFresh() ) {
			return;
		}

		e.preventDefault();

		if ( isSubmitting ) {
			return;
		}

		if ( form.checkValidity && ! form.checkValidity() ) {
			if ( form.reportValidity ) {
				form.reportValidity();
			}
			return;
		}

		isSubmitting = true;
		const submitter = e.submitter;
		if ( submitter ) {
			submitter.disabled = true;
		}

		refreshNonce().then( function ( newNonce ) {
			isSubmitting = false;
			if ( submitter ) {
				submitter.disabled = false;
			}

			if ( newNonce && typeof form.requestSubmit === 'function' ) {
				form.requestSubmit( submitter );
			} else {
				form.submit();
			}
		} );
	} );
} )();
