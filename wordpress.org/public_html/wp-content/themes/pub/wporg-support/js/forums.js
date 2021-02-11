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

	if ( $('body.bbp-is-view' ) ) {
		$( '.bbp-body .bbp-admin-links a' ).click( function( e ) {
			var $this = $( this ),
				$element = $this.closest( '.bbp-body' ),
				type;

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

			$.get( $this.prop( 'href' ) ).done( function() {
				$element.fadeOut( 250, function() {
					$element.html(
						'<div class="bbp-template-notice">' + wporgSupport.strings[ type ] + '</div>'
					);
				} ).fadeIn( 250 );
			} ).error( function() {
				$element.fadeOut( 250, function() {
					$element.find( '.bbp-topic-content' ).prepend(
						'<div class="bbp-template-notice">' + wporgSupport.strings.action_failed + '</div>'
					);
				} ).fadeIn( 250 );
			} );
		} );
	}

}( window.jQuery ) );
