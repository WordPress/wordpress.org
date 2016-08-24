/**
 * Preview for user contributed notes.
 *
 */

( function( $ ) {

	var textarea, textareaHeight, text, preview, previewContent, tabs, processing, spinner;

	function init() {

		if ( undefined === wporg_note_preview ) {
			return;
		}

		textarea = $( '.comment-form textarea' );
		preview = $( '#comment-preview' );
		tabs = $( '#commentform .tablist' ).find( 'a' );
		spinner = $( '<span class="spinner" style="display:none;"></span>' );
		text = '';
		processing = false;

		if ( textarea.length && preview.length && tabs.length ) {

			// Append spinner to preview tab
			tabs.parents( 'li[role="presentation"]:last' ).append( spinner );

			previewContent = $( '.preview-content', preview );

			if ( previewContent.length ) {

				if ( !textarea.val().length ) {
					previewContent.text( wporg_note_preview.preview_empty );
				}

				previewEvents();
			}
		}
	}

	function previewEvents() {

		tabs.on( "keydown.note_preview, click.note_preview", function( e ) {

			if ( 'comment-preview' === $( this ).attr( 'aria-controls' ) ) {

				if ( !processing ) {
					current_text = $.trim( textarea.val() );
					if ( current_text.length && ( current_text !== wporg_note_preview.preview_empty ) ) {
						if ( wporg_note_preview.preview_empty === previewContent.text() ) {
							// Remove "Nothing to preview" if there's new current text.
							previewContent.text( '' );
						}
						// Update the preview.
						updatePreview( current_text );
					} else {
						previewContent.text( wporg_note_preview.preview_empty );
					}
				}

				// Remove outline from tab if clicked.
				if ( "click" === e.type ) {
					$( this ).blur();
				}
			} else {
				textarea.focus();
			}
		} );

		// Set preview heigth when the textarea is visible
		$( '#add-user-note, .table-of-contents a[href="#add-note-or-feedback"]' ).click( function( e ) {
			e.preventDefault();
			tabs.parents( '.tablist' ).show();
			setTimeout( function() {
				textareaHeight = textarea.outerHeight( true );
				if ( 0 < textareaHeight ) {
					preview.css( 'min-height', textareaHeight + 'px' );
				}
			}, 500 );
		} );
	}

	function updatePreview( content ) {

		// Don't update preview if nothing changed
		if ( text == content ) {
			spinner.hide();
			return;
		}

		spinner.show();
		text = content;
		processing = true;

		$.post( wporg_note_preview.ajaxurl, {
			action: "preview_comment",
			preview_nonce: wporg_note_preview.nonce,
			preview_comment: content
		} )

		.done( function( response ) {
			updatePreview_HTML( response.data.comment );
		} )

		.fail( function( response ) {
			//console.log( 'fail', response );
		} )

		.always( function( response ) {
			spinner.hide();
			processing = false;

			// Make first child of the preview focusable
			preview.children().first().attr( {
				'tabindex': '0'
			} );
		} );
	}

	// Add toggle links to source code in preview if needed.
	function updateSourceCode() {
		if ( undefined !== wporg_developer ) {
			wporg_developer.sourceCodeDisplay( preview );
		}
	}

	function updatePreview_HTML( content ) {
		// Update preview content
		previewContent.html( content );

		if ( undefined !== window.SyntaxHighlighter ) {
			SyntaxHighlighter.highlight();
		}

		// Add toggle link to source code in preview if needed.
		updateSourceCode();
		spinner.hide();
	}

	init();

} )( jQuery );