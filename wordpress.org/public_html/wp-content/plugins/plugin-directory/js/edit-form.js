/*
 * JS for the Plugin Admin screens.
 */

jQuery(document).ready( function($) {

	var updateText,
		$testedWithSelect = $('#tested-with-select'),
		$pluginStatusSelect = $('#plugin-status-select');

	// submitdiv
	if ( $('#submitdiv').length ) {

		updateText = function() {
			$('#plugin-status-display').html( $('option:selected', $pluginStatusSelect).text() );
			$('#tested-with-display').html( $('option:selected', $testedWithSelect).text() );
			return true;
		};

		// Plugin Status / post_status
		$pluginStatusSelect.siblings('a.edit-plugin-status').click( function( event ) {
			if ( $pluginStatusSelect.is( ':hidden' ) ) {
				$pluginStatusSelect.slideDown( 'fast', function() {
					$pluginStatusSelect.find('select').focus();
				} );
				$(this).hide();
			}
			event.preventDefault();
		});

		$pluginStatusSelect.find('.save-plugin-status').click( function( event ) {
			$pluginStatusSelect.slideUp( 'fast' ).siblings( 'a.edit-plugin-status' ).show().focus();
			updateText();
			event.preventDefault();
		});

		$pluginStatusSelect.find('.cancel-plugin-status').click( function( event ) {
			$pluginStatusSelect.slideUp( 'fast' ).siblings( 'a.edit-plugin-status' ).show().focus();
			$('#post_status').val( $('#hidden_post_status').val() );
			updateText();
			event.preventDefault();
		});

		// Tested With
		$testedWithSelect.siblings('a.edit-tested-with').click( function( event ) {
			if ( $testedWithSelect.is( ':hidden' ) ) {
				$testedWithSelect.slideDown( 'fast', function() {
					$testedWithSelect.find('select').focus();
				} );
				$(this).hide();
			}
			event.preventDefault();
		});

		$testedWithSelect.find('.save-tested-with').click( function( event ) {
			$testedWithSelect.slideUp( 'fast' ).siblings( 'a.edit-tested-with' ).show().focus();
			updateText();
			event.preventDefault();
		});

		$testedWithSelect.find('.cancel-tested-with').click( function( event ) {
			$testedWithSelect.slideUp( 'fast' ).siblings( 'a.edit-tested-with' ).show().focus();
			$('#tested_with').val( $('#hidden_tested_with').val() );
			updateText();
			event.preventDefault();
		});
	} // end submitdiv

} );