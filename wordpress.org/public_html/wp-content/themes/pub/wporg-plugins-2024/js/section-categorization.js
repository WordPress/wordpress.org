( function ( wp, pluginDir ) {
	const logError = function ( result ) {
		document.querySelector( '.spinner' )?.classList.remove( 'spinner' );
		const error = result.status + ': ' + result.statusText;
		//result = JSON.parse( result.responseText );
		//if ( typeof result.message !== 'undefined' ) {
		// eslint-disable-next-line no-alert
		window.alert( error );
		//}
	};

	document.addEventListener( 'submit', ( event ) => {
		const form = event.target.closest( 'form' );

		if ( ! form || ! [ 'commercial', 'community' ].includes( form.id ) ) {
			return;
		}

		const submitButton = form.querySelector( 'button[type="submit"]' );
		const successMsg = form.querySelector( '.success-msg' );

		event.preventDefault();

		successMsg?.classList.remove( 'saved' );

		let fieldName = '';
		let restName = '';

		if ( 'commercial' === form.id ) {
			fieldName = 'external_support_url';
			restName = 'supportURL';
		} else {
			fieldName = 'external_repository_url';
			restName = 'repositoryURL';
		}

		const fieldInput = form.querySelector(
				'input[name="' + fieldName + '"]'
			),
			button = form.querySelector( '.button-small' ),
			url =
				pluginDir.restUrl +
				'plugins/v1/plugin/' +
				pluginDir.pluginSlug +
				'/' +
				form.id +
				'/?_wpnonce=' +
				pluginDir.restNonce +
				'&_wporg_action=' +
				pluginDir.actionNonce,
			originalValue = fieldInput.dataset.originalValue ?? '';

		button?.classList.add( 'spinner' );
		submitButton.disabled = true;

		fetch( url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify( {
				[ restName ]: fieldInput.value,
			} ),
		} )
			.then( ( response ) => {
				if ( ! response.ok ) {
					logError( response );
				}
				return response;
			} )
			.then( ( response ) => {
				return response.json();
			} )
			.then( ( data ) => {
				let fieldValue;
				if ( typeof data[ restName ] !== 'undefined' ) {
					successMsg?.classList.add( 'saved' );
					// Use value sanitized and saved by server.
					fieldValue = data[ restName ];
				} else {
					// Restore original value.
					fieldValue = originalValue;
				}
				fieldInput.value = fieldValue;
				// Update widget.
				const widgetLink = document.querySelector(
					'.widget.plugin-categorization .widget-head a'
				);
				if ( widgetLink ) {
					widgetLink.attributes.href.value = fieldValue;
				}
				submitButton.disabled = false;
				button?.classList.remove( 'spinner' );
			} )
			.catch( ( error ) => {
				logError( error );
				fieldInput.value = originalValue;
				submitButton.disabled = false;
			} );
	} );
} )( window.wp, window.categorizationOptions );
