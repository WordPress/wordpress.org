/* global ajaxurl:true */
jQuery( function( $ ) {
	$( '#two-factor-active' ).on( 'click', '.two-factor-disable', function( event ) {
		event.preventDefault();

		$.post(
			two_factor_edit.ajaxurl,
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
					$( '#two-factor-active' ).find( '> div:first-of-type' ).prepend(
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
		.not( '#two-factor-active' ).on( 'click', '.two-factor-cancel', function( event ) {
		event.preventDefault();

		$( '.two-factor .bbp-template-notice' ).remove();

		$( this ).parents( 'fieldset.two-factor' )
			.hide()
			.find( '[type="tel"]').val( '' );
		$( '#two-factor-start' ).show();
	} )
		.on( 'click', '.two-factor-submit', function( event ) {
			event.preventDefault();

			$( '.two-factor .bbp-template-notice' ).remove();

			$.post(
				two_factor_edit.ajaxurl,
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
			two_factor_edit.ajaxurl,
			{
				action: 'two_factor_backup_codes_generate',
				user_id: $( '#user_id' ).val(),
				nonce: $( '#_nonce-backup-codes' ).val(),
			},
			function( response ) {
				if ( response.success ) {
					var $codesList = $( '#two-factor-backup-codes-list' ),
						txt_data = 'data:application/text;charset=utf-8,' + '\n';

					$( '#two-factor-backup-codes-button' ).hide();
					$( '.two-factor-backup-codes-wrapper' ).show();
					$codesList.html( '' );

					// Append the codes.
					for ( i = 0; i < response.data.codes.length; i++ ) {
						$codesList.append( '<li>' + response.data.codes[ i ] + '</li>' );
					}

					// Build the download link
					txt_data += response.data.i18n.title.replace( /%s/g, document.domain ) + '\n\n';

					for ( i = 0; i < response.data.codes.length; i++ ) {
						txt_data += i + 1 + '. ' + response.data.codes[ i ] + '\n';
					}

					$( '#two-factor-backup-codes-download' ).attr( 'href', encodeURI( txt_data ) );
				}
			}
		);
	} );

	var $printAgreement   = $( '#print-agreement' ),
		$backupDoneButton = $( '.two-factor-backup-codes-wrapper .two-factor-submit' );

	$printAgreement.on( 'change', function() {
		$backupDoneButton.prop( 'disabled', ! $printAgreement.prop( 'checked' ) );
	} );

	$backupDoneButton.on( 'click', function( event ) {
		event.preventDefault();

		$( '.two-factor-backup-codes-wrapper' ).hide();
		$( '#two-factor-backup-codes-button' ).show();
		$printAgreement.prop( 'checked', false );
		$backupDoneButton.prop( 'disabled', true );
	} );

	$( '#two-factor-backup-codes-copy' ).on( 'click', function() {
		var $temp = $( '<textarea>' ),
			list = '';

		$( 'body' ).append( $temp );
		$( '#two-factor-backup-codes-list' ).children().each( function( index, node ) {
			list += node.innerText + "\n";
		} );

		$temp.val( list ).select();
		document.execCommand( 'copy' );
		$temp.remove();
	} );

	$( '#two-factor-backup-codes-print' ).on( 'click', function() {
		var printer = window.open('', '_blank' );
		printer.document.writeln( '<ol>' + $( '#two-factor-backup-codes-list' ).html() + '</ol>' );
		printer.document.close();
		printer.focus();
		printer.print();
		printer.close();
	} );
} );
