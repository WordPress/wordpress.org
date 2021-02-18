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
			$val  = $this.val(),
			paste = ( e.originalEvent.clipboardData || window.clipboardData ).getData('text');

		// If no pasted text, or no textarea value, skip.
		if ( ! paste.length || ! $val.length ) {
			return;
		}

		if (
			paste.length < 1000 &&        // Super long pastes get code wrapped
			paste.split("\n").length < 10 // in addition to many-lines pastes.
		) {
			return;
		}
	
		$this.val(
			$val.substring( 0, $this.prop('selectionStart') ) +       // Text before cusor/selection
			"`" + paste.trim().replace(/^`|`$/g, '') + "`" +          // The pasted text, trimming ` off it and wrapping with `
			$val.substring( $this.prop('selectionEnd'), $val.length ) // Text after cursor position/selection
		);

		e.preventDefault();
	} );

	if ( $( 'body' ).is( '.bbp-view' ) ) {
		$( '.bbp-body .bbp-admin-links a' ).click( function( e ) {
			var $this = $( this ),
				$element = $this.closest( '.bbp-body' ),
				$content = $element.find( '.bbp-topic-content' ),
				type;

			// Don't affect open-in-new-tab.
			if ( e.metaKey || e.ctrlKey || e.which === 2 ) {
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
				$content.append(
					'<div class="bbp-template-notice">' + wporgSupport.strings[ type ] + '</div>'
				);

				// Remove actions.
				$this.parent().find('a:not(.bbp-topic-edit-link)').remove();

				$element.fadeTo( 250, 1 );
			} ).error( function() {
				$content.append(
					'<div class="bbp-template-notice">' + wporgSupport.strings.action_failed + '</div>'
				);

				$element.fadeTo( 250, 1 );
			} );
		} );
	}

}( window.jQuery ) );
