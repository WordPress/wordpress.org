/* global ajaxurl:true */
jQuery( function( $ ) {
	$( '#two-factor-active' ).on( 'click', '[type="cancel"]', function( event ) {
		event.preventDefault();

		$.post(
			ajaxurl,
			{
				action      : 'two-factor-disable',
				_ajax_nonce : $( '#_nonce_user_two_factor_totp_options' ).val(),
				user_id     : $( '#user_id' ).val(),
			},
			function( response ) {
				if ( response.success ) {
					$( '#two-factor-active' ).hide();
					$( '#two-factor-start' ).show().find( 'div:first-of-type' ).prepend(
						$( '<div class="bbp-template-notice info" />' ).text( response.data )
					);
				} else {
					$( '#two-factor-active' ).find( 'div:first-of-type' ).prepend(
						$( '<div class="bbp-template-notice error" />' ).text( response.data )
					);
				}
			}
		);
	} );
	$( '#two-factor-start-toggle' ).on( 'click', function() {
		$( '#two-factor-start' ).hide();
		$( '#two-factor-qr-code' ).show();
	} );

	$( '#two-factor-qr-code' ).on( 'click', '.button-link', function() {
		$( '#two-factor-qr-code' )
			.hide()
			.find( '[type="tel"]').val( '' );
		$( '#two-factor-key-code' ).show();
	} );

	$( '#two-factor-key-code' ).on( 'click', '.button-link', function() {
		$( '#two-factor-key-code' )
			.hide()
			.find( '[type="tel"]').val( '' );
		$( '#two-factor-qr-code' ).show();
	} );

	$( 'fieldset.two-factor' )
		.not( '#two-factor-active' ).on( 'click', '[type="cancel"]', function( event ) {
		event.preventDefault();

		$( '.two-factor .bbp-template-notice' ).remove();

		$( this ).parents( 'fieldset.two-factor' )
			.hide()
			.find( '[type="tel"]').val( '' );
		$( '#two-factor-start' ).show();
	} )
		.on( 'click', '[type="submit"]', function( event ) {
			event.preventDefault();

			$( '.two-factor .bbp-template-notice' ).remove();

			$.post(
				ajaxurl,
				{
					action      : 'two-factor-totp-verify-code',
					_ajax_nonce : $('#_nonce_user_two_factor_totp_options').val(),
					user_id     : $('#user_id').val(),
					key         : $('[name="two-factor-totp-key"]').val(),
					authcode    : $('[name="two-factor-totp-authcode"]').val(),
				},
				function( response ) {
					if ( response.success ) {
						$( 'fieldset.two-factor' ).hide().find( '[type="tel"]').val( '' );
						$( '#two-factor-active' ).show();
					} else {
						$( 'fieldset.two-factor:visible' ).find( 'div:first-of-type' ).prepend(
							$( '<div class="bbp-template-notice error" />' ).text( response.data )
						);
					}
				}
			);
		} );
} );
