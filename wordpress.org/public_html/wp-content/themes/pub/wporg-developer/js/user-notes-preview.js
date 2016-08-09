/**
 * Preview for user contributed notes.
 *
 */

var wporg_developer_note_preview = ( function( $ ) {

	var textarea, preview, previewContent, spinner;

	function init( textarea_selector, preview_selector ) {

		textarea = $( textarea_selector );
		preview = $( preview_selector );

		if ( textarea.length && preview.length && ( undefined !== wporg_note_preview ) ) {

			previewContent = $( '.comment-content', preview );
			spinner = $( '.spinner', preview );

			if ( previewContent.length && spinner.length ) {

				add_preview_button();

				var current_text = textarea.val();

				if ( current_text.length ) {
					update_preview( current_text );
				}

				add_preview_events();
			}
		}
	}

	function add_preview_button() {
		QTags.addButton( 'preview', wporg_note_preview.preview, function() {
			var pos = preview.position();
			$( 'html,body' ).animate( {
				scrollTop: pos.top
			}, 1000 );
		} );
	}

	function add_preview_events() {

		// Update Preview after QuickTag button is clicked.
		var buttons = $( '#qt_comment_toolbar' ).find( 'input' ).not( '#qt_comment_preview' );
		buttons.on( 'click', function() {
			// Set timeout to let the quicktags do it's thing first.
			setTimeout( function() {
				update_preview( textarea.val() );
			}, 500 );
		} );

		// Update Preview after keykup event.
		// Delay updating the preview by 2 seconds to not overload the server.
		textarea.bind( 'keyup', debounce( function( e ) {
			update_preview( $( this ).val() );
		}, 2000 ) );

		// Display a spinner as soon as the comment form changes input.
		textarea.bind( 'input propertychange selectionchange', function( e ) {
			spinner.show();
		} );
	}

	function update_preview( content ) {
		spinner.show();
		$.post( wporg_note_preview.ajaxurl, {
			action: "preview_comment",
			preview_nonce: wporg_note_preview.nonce,
			preview_comment: content
		} )

		.done( function( response ) {
			update_preview_html( response.data.comment );
		} )

		.fail( function( response ) {
			//console.log( 'fail', response );
		} )

		.always( function( response ) {
			spinner.hide();
		} );
	}

	// Add toggle links to source code in preview if needed.
	function update_source_code() {

		if ( undefined !== wporg_developer ) {
			wporg_developer.sourceCodeDisplay( preview );
		}
	}

	function update_preview_html( content ) {
		// Update preview content
		previewContent.html( content );

		if ( undefined !== window.SyntaxHighlighter ) {
			SyntaxHighlighter.highlight();
		}

		// Add toggle link to source code in preview if needed.
		update_source_code();
		spinner.hide();
	}

	// https://remysharp.com/2010/07/21/throttling-function-calls
	function debounce( fn, delay ) {
		var timer = null;
		return function() {
			var context = this,
				args = arguments;
			clearTimeout( timer );
			timer = setTimeout( function() {
				fn.apply( context, args );
			}, delay );
		};
	}

	return {
		init: init
	}

} )( jQuery );