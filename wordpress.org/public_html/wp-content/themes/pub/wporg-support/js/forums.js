( function( $ ) {
	if ( $( '#new-post' ).length ) {
		var requireConfirmClose = false;

		$( '#new-post' ).submit( function() {
			$( '[type="submit"]', $( this ) ).prop( 'disabled', 'disabled' );

			requireConfirmClose = false;
		} );

		$( '#new-post' ).one( 'input', 'input[type=text], textarea', function() {
			requireConfirmClose = true;
		} );

		window.addEventListener( 'beforeunload', function( e ) {
			if ( requireConfirmClose ) {
				e.preventDefault();

				// Chrome requires returnValue to be set.
				e.returnValue = '';
			}
		} );
	}

	// Wrap long pastes in code tags.
	$( '#new-post textarea' ).on( 'paste', function( e ) {
		var $this = $(this),
			val  = $this.val(),
			paste = ( e.originalEvent.clipboardData || window.clipboardData ).getData('text').trim();

		// If no pasted text, or no textarea value, skip.
		if ( ! paste.length || ! val.length ) {
			return;
		}

		if (
			paste.length < 500 &&        // Super long pastes get code wrapped
			paste.split("\n").length < 5 // in addition to many-lines pastes.
		) {
			return;
		}

		// See if the author is pasting into a code block already
		if ( '`' === val.substr( $this.prop('selectionStart') - 1 , 1 ) ) {
			return;
		}

		// If the code being pasted is already wrapped in backticks (well, starts with OR ends with), skip.
		if (
			'`' === paste.substr( 0, 1 ) ||
			'`' === paste.substr( -1, 1 )
		) { 
			return;
		}
	
		$this.val(
			val.substr( 0, $this.prop('selectionStart') ) +      // Text before cursor/selection
			"`" + paste + "`" +                                  // The pasted text, wrapping with `
			val.substr( $this.prop('selectionEnd'), val.length ) // Text after cursor position/selection
		);

		e.preventDefault();
	} );

	if ( $( 'body' ).is( '.bbp-view' ) ) {
		$( '.bbp-admin-links a' ).click( function( e ) {
			var $this = $( this ),
				$element = $this.parents('.reply,.topic'),
				type;

			// Don't affect open-in-new-tab.
			if ( e.metaKey || e.ctrlKey || e.shiftKey ) {
				return;
			}

			if ( $this.is( '[class*=approve]' ) ) {
				type = 'approve';
			} else if ( $this.is( '[class*=spam]' ) ) {
				type = 'spam';
			} else if ( $this.is( '[class*=archive]' ) ) {
				type = 'archive';
			} else {
				return;
			}

			e.preventDefault();

			$element.fadeTo( 500, .5 );

			$.get( $this.prop( 'href' ) ).done( function() {
				$this.parent().before(
					'<div class="bbp-template-notice">' + wporgSupport.strings[ type ] + '</div>'
				);

				$element.fadeTo( 250, 1 );
			} ).fail( function() {
				$this.parent().before(
					'<div class="bbp-template-notice">' + wporgSupport.strings.action_failed + '</div>'
				);

				$element.fadeTo( 250, 1 );
			} );
		} );
	}

}( window.jQuery ) );
