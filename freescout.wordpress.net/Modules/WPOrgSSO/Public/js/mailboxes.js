/**
 * Asks for the mailbox's name before it's deleted, instead of a FreeScout password, which users don't know.
 *
 * The server refuses deletions without the name; this only adds the field to core's dialog.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	$( function () {
		const $field = $( '#wporgsso-delete-mailbox' );

		if ( ! $field.length ) {
			return;
		}

		const name = $.trim( $field.attr( 'data-name' ) );

		// Core copies this template into the dialog each time it opens.
		$( '#delete_mailbox_modal .button-delete-mailbox' )
			.attr( 'disabled', 'disabled' )
			.parent()
			.before( $field.children() );

		$( document ).on( 'input', '.wporgsso-mailbox-name', function () {
			$( this )
				.closest( '.modal-body' )
				.find( '.button-delete-mailbox' )
				.prop( 'disabled', $.trim( $( this ).val() ) !== name );
		} );

		// Core's dialog posts a fixed set of fields.
		$.ajaxPrefilter( function ( options ) {
			if (
				'string' === typeof options.data &&
				/(^|&)action=delete_mailbox(&|$)/.test( options.data )
			) {
				options.data +=
					'&mailbox_name=' +
					encodeURIComponent(
						$( '.wporgsso-mailbox-name:visible:first' ).val() || ''
					);
			}
		} );
	} );
} )( jQuery );
