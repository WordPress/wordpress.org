( function( wp, pluginDir ) {
	const logError = function( result ) {
		document.querySelector( '.spinner' )?.classList.remove( 'spinner' );
		const error = result.status + ': ' + result.statusText;
		//result = JSON.parse( result.responseText );
		//if ( typeof result.message !== 'undefined' ) {
			alert( error );
		//}
	};

	document.addEventListener( 'submit', event => {
		const form = event.target.closest('form');
		const submitButton = form.querySelector('button[type="submit"]');
		const successMsg = form.querySelector('.success-msg');

		if ( ! form || ! ['commercial', 'community'].includes(form.id) ) {
			return;
		}

		event.preventDefault();

		successMsg?.classList.remove( 'saved' );

		let field_name = '';

		if ( 'commercial' === form.id ) {
			field_name = 'external_support_url';
			rest_name  = 'supportURL';
		} else {
			field_name = 'external_repository_url';
			rest_name  = 'repositoryURL';
		}

		let fieldInput = form.querySelector( 'input[name="' + field_name + '"]' ),
			button = form.querySelector( '.button-small' )?.classList.add( 'spinner' ),
			url = pluginDir.restUrl + 'plugins/v1/plugin/' + pluginDir.pluginSlug + '/' + form.id + '/?_wpnonce=' + pluginDir.restNonce;
			originalValue = fieldInput.dataset.originalValue ?? '';

		submitButton.disabled = true;

		fetch( url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({
				[rest_name]: fieldInput.value,
			}),
		})
			.then((response) => {
				if ( !response.ok ) {
					logError(response);
				}
				return response;
			})
			.then((response) => {
				return response.json();
			})
			.then((data) => {
				let fieldValue;
				if ( typeof data[rest_name] !== 'undefined' ) {
					successMsg?.classList.add( 'saved' );
					// Use value sanitized and saved by server.
					fieldValue = data[rest_name];
				} else {
					// Restore original value.
					fieldValue = originalValue;
				}
				fieldInput.value = fieldValue;
				// Update widget.
				const widgetLink = document.querySelector('.widget.plugin-categorization .widget-head a');
				if ( widgetLink ) {
					widgetLink.attributes.href.value = fieldValue;
				}
				submitButton.disabled = false;
				button?.classList.remove( 'spinner' );
			})
			.catch((error) => {
				logError(error);
				fieldInput.value = originalValue;
				submitButton.disabled = false;
			})
	} );
} )( window.wp, window.categorizationOptions );

