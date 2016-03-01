/*
 * JS for the Plugin Admin screens.
 */

( function( $, wp ) {

	var PluginEdit = {
		$notesBox: {},
		$testedWith: {},
		$pluginStatus: {},

		ready: function() {
			PluginEdit.$notesBox     = $( '#plugin-notes' );
			PluginEdit.$testedWith   = $( '#tested-with-select' );
			PluginEdit.$pluginStatus = $( '#plugin-status-select' );

			$( '#submitdiv' )
				.on( 'click', '.edit-tested-with',     PluginEdit.editTestedWith )
				.on( 'click', '.edit-plugin-status',   PluginEdit.editPluginStatus )
				.on( 'click', '.save-tested-with',     PluginEdit.updateTestedWith )
				.on( 'click', '.save-plugin-status',   PluginEdit.updatePluginStatus )
				.on( 'click', '.cancel-tested-with',   PluginEdit.cancelTestedWith )
				.on( 'click', '.cancel-plugin-status', PluginEdit.cancelPluginStatus );

			PluginEdit.$notesBox
				.on( 'click', '.cancel-note', PluginEdit.showNote )
				.on( 'click', '.view-note',   PluginEdit.editNote )
				.on( 'click', '.save-note',   PluginEdit.saveNote );
		},

		editTestedWith: function() {
			if ( PluginEdit.$testedWith.is( ':hidden' ) ) {
				PluginEdit.$testedWith.slideDown( 'fast', function() {
					$( 'select', PluginEdit.$testedWith ).focus();
				} );
				$( this ).hide();
			}
		},

		editPluginStatus: function() {
			if ( PluginEdit.$pluginStatus.is( ':hidden' ) ) {
				PluginEdit.$pluginStatus.slideDown( 'fast', function() {
					$( 'select', PluginEdit.$pluginStatus ).focus();
				} );
				$( this ).hide();
			}
		},

		updateTestedWith: function() {
			PluginEdit.$testedWith.slideUp( 'fast' ).siblings( 'button.edit-tested-with' ).show().focus();
			$( '#tested-with-display' ).text( $( 'option:selected', PluginEdit.$testedWith ).text() );
		},

		updatePluginStatus: function() {
			PluginEdit.$pluginStatus.slideUp( 'fast' ).siblings( 'button.edit-plugin-status' ).show().focus();
			$( '#plugin-status-display' ).text( $( 'option:selected', PluginEdit.$pluginStatus ).text() );
		},

		cancelTestedWith: function() {
			$( '#tested-with' ).val( $( '#hidden-tested-with' ).val() );
			PluginEdit.updateTestedWith();
		},

		cancelPluginStatus: function() {
			$( '#post-status' ).val( $( '#hidden-post-status' ).val() );
			PluginEdit.updatePluginStatus( event );
		},

		showNote: function() {
			$( '.view-note', PluginEdit.$notesBox ).show();
			$( '.edit-note', PluginEdit.$notesBox ).hide();
		},

		editNote: function() {
			var $textarea = $( '.note-content', PluginEdit.$notesBox );

			$( '.view-note', PluginEdit.$notesBox ).hide();
			$( '.edit-note', PluginEdit.$notesBox ).show();
			$textarea.text( $textarea.val() ).focus();
		},

		saveNote: function() {
			wp.ajax.post( 'save-note', {
				id: $( '#post_ID' ).val(),
				note: $( '.note-content', PluginEdit.$notesBox ).val(),
				notce: $( '#notce' ).val()
			} )
				.done( function( response ) {
					var note = response.note ? response.note : 'Add note';

					$( '.view-note', PluginEdit.$notesBox ).html( note );
					PluginEdit.showNote();
				} );
		}
	};

	$( PluginEdit.ready );
} )( window.jQuery, window.wp );
