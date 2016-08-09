/**
 * Dynamic functionality for comments as user submitted notes.
 *
 */

( function( $ ) {

	var commentForm = $( '.comment-form textarea' );

	if ( !commentForm.length ) {
		return;
	}

	function showCommentForm() {
		$( '#respond, #add-user-note' ).toggle();
		
		var preview = $( '#comment-preview' );
		if( preview.length && ( wporg_developer_note_preview !== undefined ) ) {
			preview.show();

			//Initialize preview with textarea and preview selectors
			wporg_developer_note_preview.init( '.comment-form textarea', '#comment-preview' );
		}

		if ( pos = $( '#submit' ).position() ) {
			if ( pos.top < $(window).scrollTop() ) {
				// Scroll up
				$( 'html,body' ).animate( {scrollTop:pos.top}, 1000 );
			}
			else if ( pos.top + jQuery("selector").height() > $(window).scrollTop() + (window.innerHeight || document.documentElement.clientHeight) ){
				// Scroll down
				$( 'html,body' ).animate( {scrollTop:pos.top - (window.innerHeight || document.documentElement.clientHeight) + $( '#submit' ).height() + 30}, 1000 );
			}
		}
	}

	$( '#respond, #add-user-note' ).toggle();
	$( '#add-user-note' ).click( function( e ) {
		e.preventDefault();

		showCommentForm();
	} );

	if ( '#respond' === document.location.hash ) {
		showCommentForm();
	}

	// Add php and js buttons to QuickTags.
	QTags.addButton( 'php', 'php', '[php]', '[/php]' );
	QTags.addButton( 'js', 'js', '[js]', '[/js]' );
	QTags.addButton( 'inline-code', 'inline code', '<code>', '</code>' );

	// Override tab within user notes textarea to actually insert a tab character.
	// Copied from code within core's wp-admin/js/common.js.
	commentForm.bind('keydown.wpevent_InsertTab', function(e) {
		var el = e.target, selStart, selEnd, val, scroll, sel;

		if ( e.keyCode == 27 ) { // escape key
			// when pressing Escape: Opera 12 and 27 blur form fields, IE 8 clears them
			e.preventDefault();
			$(el).data('tab-out', true);
			return;
		}

		if ( e.keyCode != 9 || e.ctrlKey || e.altKey || e.shiftKey ) // tab key
			return;

		if ( $(el).data('tab-out') ) {
			$(el).data('tab-out', false);
			return;
		}

		selStart = el.selectionStart;
		selEnd = el.selectionEnd;
		val = el.value;

		if ( document.selection ) {
			el.focus();
			sel = document.selection.createRange();
			sel.text = '\t';
		} else if ( selStart >= 0 ) {
			scroll = this.scrollTop;
			el.value = val.substring(0, selStart).concat('\t', val.substring(selEnd) );
			el.selectionStart = el.selectionEnd = selStart + 1;
			this.scrollTop = scroll;
		}

		if ( e.stopPropagation )
			e.stopPropagation();
		if ( e.preventDefault )
			e.preventDefault();
	});

} )( jQuery );
