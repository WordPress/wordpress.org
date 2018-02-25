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

	$( '#generate-backup-codes' ).on( 'click', function() {
		$.post(
			ajaxurl,
			{
				action: 'two_factor_backup_codes_generate',
				user_id: $( '#user_id' ).val(),
				nonce: $( '#_nonce-backup-codes' ).val(),
			},
			function( response ) {
				if ( response.success ) {
					var $codesList = $( '.two-factor-backup-codes-unused-codes' );

					$( '#generate-backup-codes' ).remove();
					$( '.two-factor-backup-codes-wrapper' ).show();
					$codesList.html( '' );

					// Append the codes.
					for ( i = 0; i < response.data.codes.length; i++ ) {
						$codesList.append( '<li>' + response.data.codes[ i ] + '</li>' );
					}

					// Update counter.
					$( '.two-factor-backup-codes-count' ).html( response.data.i18n.count );

					// Build the download link
					var txt_data = 'data:application/text;charset=utf-8,' + '\n';
					txt_data += response.data.i18n.title.replace( /%s/g, document.domain ) + '\n\n';

					for ( i = 0; i < response.data.codes.length; i++ ) {
						txt_data += i + 1 + '. ' + response.data.codes[ i ] + '\n';
					}

					$( '#two-factor-backup-codes-download-link' ).attr( 'href', encodeURI( txt_data ) );
				}
			}
		);
	} );

	$( '#print-agreement' ).on( 'change', function() {
		$( '.two-factor-backup-codes-wrapper button[type="submit"]' ).prop( 'disabled', ! $( this ).prop( 'checked' ) );
	} );

} );
