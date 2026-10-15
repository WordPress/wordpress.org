/**
 * Shows the settings FreeScout keeps in `.env` as read-only, with a note: the server's `.env` can't be written, and
 * the module doesn't save them. Disabled fields aren't sent, so the rest of the section saves.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	$( function () {
		$( '.wporgsite-env-settings' ).each( function () {
			const $form = $( this );
			const note = $form.data( 'note' );

			( $form.data( 'settings' ) || [] ).forEach( function ( key ) {
				const $fields = $form
					.find(
						'[name="settings[' +
							key +
							']"], [name="settings[' +
							key +
							'][]"]'
					)
					.prop( 'disabled', true );

				if ( ! $fields.length ) {
					return;
				}

				// Under the fields, once per row: Logs Monitoring's frequency is in the same row as its switch.
				const $place = $fields
					.first()
					.closest( '.form-group' )
					.children()
					.has( $fields.first() );

				if ( $place.children( '.wporgsite-env-note' ).length ) {
					return;
				}

				$place.append(
					$( '<p class="help-block wporgsite-env-note">' ).text(
						note
					)
				);
			} );
		} );
	} );
} )( jQuery );
