/**
 * Dynamic functionality for comments as user submitted notes.
 *
 */

( function( $ ) {

	var commentForm = $( '.comment-form textarea' );
	var commentID = window.location.hash;
	var wpAdminBar = 0;

	// Check if the fragment identifier is a comment ID (e.g. #comment-63)
	if ( ! commentID.match( /#comment\-[0-9]+$/ ) ) {
		commentID = '';
	}

	// Actions for when the page is ready
	$( document ).ready( function() {
		// Set wpAdminBar
		wpAdminBar = $( '#wpadminbar' ).length ? 32 : 0;

		// Display form and scroll to it
		if ( '#respond' === window.location.hash ) {
			showCommentForm();
		}

		if( ! wpAdminBar || ! commentID ) {
			return;
		}

		var comment = $('#comments').find( commentID + '.depth-1' ).first();
		if( ! comment.length  ) {
			return;
		}

		// Scroll to top level comment and adjust for admin bar.
		var pos = comment.offset();
		$( 'html,body' ).animate( {
			scrollTop: pos.top - wpAdminBar
		}, 1 );
	} );

	// Scroll to comment if comment date link is clicked
	$( '#comments' ).on( 'click', '.comment-date', function( e ) {
		// Scroll to comment and adjust for admin bar
		// Add 16px for child comments
		var pos = $( this ).offset();
		$( 'html,body' ).animate( {
			scrollTop: pos.top - wpAdminBar - 16
		}, 1 );
	} );

	function showCommentForm() {
		var target = $( '#commentform #add-note-or-feedback' );
		if ( target.length ) {
			var pos = target.offset();

			$( 'html,body' ).animate( {
				scrollTop: pos.top - wpAdminBar
			}, 1000 );

			$('.wp-editor-area').focus();
		}
	}

	if ( ! commentForm.length ) {
		return;
	}

	$( '.table-of-contents a[href="#add-note-or-feedback"]' ).click( function( e ) {
		e.preventDefault();
		showCommentForm();
	} );

	// Add php and js buttons to QuickTags.
	QTags.addButton( 'php', 'php', '[php]', '[/php]', '', '', '', 'comment' );
	QTags.addButton( 'js', 'js', '[js]', '[/js]', '', '', '', 'comment' );
	QTags.addButton( 'inline-code', 'inline code', '<code>', '</code>', '', '', '', 'comment' );

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
