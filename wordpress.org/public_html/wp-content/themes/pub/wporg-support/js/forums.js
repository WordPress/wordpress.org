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
