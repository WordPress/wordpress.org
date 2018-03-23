( function( $ ) {

	if ( undefined === wporg_note_feedback ) {
		return;
	}

	var options        = wporg_note_feedback;
	var wpAdminBar     = $('#page.admin-bar').length ? 32 : 0;
	var feedbackToggle = $( '<a class="feedback-toggle" href="#">' + options.show + '</a>' );
	var hash           = window.location.hash;

	// Check if the fragment identifier is a comment ID (e.g. #comment-63)
	if ( !hash.match( /#comment\-[0-9]+$/ ) ) {
		hash = '';
	}
	
	$( '.feedback-editor' ).each( function() {

		// Hide hidden editor with 'hide-if-js' class.
		if( 'none' === $(this).css('display') ) {
			$( this ).show().addClass( 'hide-if-js' );
		}

		// Add quicktag 'inline code' button to editor. 
		var id = $( this ).find( 'textarea' ).attr( 'id' );
		if ( id.length ) {
			QTags.addButton( 'inline-code', 'inline code', '<code>', '</code>', '', '', '', id );
		}
	} );

	// Loop through feedback notes
	$( '.comment' ).each( function() {
		var feedbackLinks = $( this ).find( '.feedback-links' );
		var childComments = $( this ).find( 'ul.children' );
		
		if ( childComments.length && feedbackLinks.length ) {
			var feedback = $( this ).find( '.feedback' );
			var toggle = feedbackToggle.clone();

			toggle.attr( {
				'aria-expanded': 'false',
				'aria-controls': 'feedback-' + getCommentID( $(this) )
			} );

			// Set text to 'Hide Feedback' if feedback is displayed
			if ( !feedback.hasClass( 'hide-if-js' ) ) {
				toggle.text( options.hide );
			}

			feedbackLinks.find( '.feedback-add' ).show();
			feedbackLinks.append( toggle );
		}

		if ( feedbackLinks.length ) {
			// Move feedback links before feedback.
			var clonedElements = feedbackLinks.clone().children();
			var feedbackLinksTop = $( '<div class="feedback-links"></div>' ).append( clonedElements );
			$( this ).find( '.feedback' ).first().before( feedbackLinksTop );
			feedbackLinks.addClass( 'bottom hide-if-js' );
		}
	} );

	// Returns comment ID from data attribute.
	function getCommentID( el ) {
		return $(el).is("[data-comment-id]") ? el.data( 'comment-id' ) : 0;
	}

	// Removes added elements 
	function resetComment( el ) {

		var children = el.find( 'ul.children' );
		if ( !children.length ) {
			el.find( '.feedback-toggle' ).remove();
		}

		el.find( '.feedback-links.bottom' ).addClass( 'hide-if-js' );
	}

	// Show hidden child comments if the fragment identifier is a comment ID (e.g. #comment-63).  
	$( document ).ready( function() {

		var childComments = $( '.comment' ).find( 'ul.children' );

		if ( !( hash.length && childComments.length ) ) {
			return;
		}

		var hashComment = childComments.find( hash ).first();
		if ( hashComment.length ) {
			// Child comment exists.

			var parent = hashComment.closest( '.comment.depth-1' );
			if ( parent.find( '.feedback' ).hasClass( 'hide-if-js' ) ) {
				// Show child comments.
				parent.find( '.feedback-toggle' ).first().trigger( 'click' );
			}

			// Scroll to the child comment.
			var pos = hashComment.offset();
			$( 'html,body' ).animate( {
				scrollTop: pos.top - wpAdminBar
			}, 1 );
		}
	} );

	// Show/Hide feedback toggle link.
	$( document ).on( 'click', '.feedback-toggle', function( e ) {
		e.preventDefault();

		var parent = $( this ).closest( '.comment.depth-1' );
		if ( !parent.length ) {
			return;
		}

		resetComment( parent );
		var feedback = parent.find( '.feedback' );
		var toggleLinks = parent.find( '.feedback-toggle' );

		if ( feedback.hasClass( 'hide-if-js' ) ) {
			// Feedback is hidden.

			// Show feedback.
			toggleLinks.text( options.hide );
			feedback.removeClass( 'hide-if-js' );
			toggleLinks.attr( 'aria-expanded', 'true' );

			// Go to the clicked feedback toggle link.
			var pos = $( this ).offset();
			$( 'html,body' ).animate( {
				scrollTop: pos.top - wpAdminBar
			}, 1000 );

			// Add feedback links at the bottom if there are over 3 feedback notes.
			var children = parent.find( 'ul.children > li' );
			if ( 3 < children.length ) {
				var feedbackLinks = parent.find( '.feedback-links.bottom' );
				feedbackLinks.removeClass( 'hide-if-js' );
			}

		} else {
			// Hide feedback.
			toggleLinks.text( options.show );
			feedback.addClass( 'hide-if-js' );
			toggleLinks.attr( 'aria-expanded', 'false' );

			// Hide editor.
			var editor = feedback.find( '.feedback-editor' );
			editor.addClass( 'hide-if-js' );

			parent.find( '.feedback-add' ).attr( 'aria-expanded', 'false' );
		}
	} );

	// Show editor when the add feedback link is clicked.
	$( document ).on( 'click', '.feedback-add', function( e ) {
		e.preventDefault();

		var parent = $( this ).closest( '.comment.depth-1' );
		if ( !parent.length ) {
			return;
		}

		resetComment( parent );

		var feedback = parent.find( '.feedback' );
		var children = parent.find( 'ul.children' );
		var feedbackLinks = parent.find( '.feedback-add' );

		// Show feedback.
		feedback.removeClass( 'hide-if-js' );
		feedbackLinks.attr( 'aria-expanded', 'true' );

		// Show the feedback editor.
		var editor = feedback.find( '.feedback-editor' );
		editor.removeClass( 'hide-if-js' );

		// Change the toggle link text to 'Hide Feedback'.
		var toggleLinks = parent.find( '.feedback-toggle' );
		if ( toggleLinks.length ) {
			toggleLinks.attr( 'aria-expanded', 'true' );
			toggleLinks.text( options.hide );
		}

		// If there are no child comments add a 'Hide Feedback' link.
		if ( !children.length ) {
			var hide = feedbackToggle.clone();
			hide.text( options.hide );
			hide.attr( {
				'aria-expanded': 'true',
				'aria-controls': 'feedback-' + getCommentID( parent )
			} );
			parent.find( '.feedback-links' ).append( hide );
		}

		// Go to the feedback editor and give it focus.
		var pos = editor.offset();
		$( 'html,body' ).animate( {
			scrollTop: pos.top - wpAdminBar
		}, 1000, function() {
			editor.find( 'textarea' ).focus();
		} );
	} );

} )( jQuery );
