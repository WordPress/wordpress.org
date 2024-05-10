( function( $ ) {
	var questions = $( 'dt', '#faq' );

	questions
		.each( function( index, question ) {
			var $question = $( question ),
				$button   = $( '<button />' ),
				$h3       = $( '<h3 />' );

			$question.html( $h3.html( $button.text( $question.text() ) ) );
		} )
		.on( 'click', function( event ) {
			var $question = $( event.currentTarget );

			if ( 'keydown' === event.type && 13 !== event.which ) {
				return;
			}

			if ( ! $question.is( '.open' ) ) {
				$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false ).next( 'dd' ).slideToggle( 200 );
			}

			$question.toggleClass( 'open' ).attr( 'aria-expanded', function( index, attribute ) {
				return 'true' !== attribute;
			} ).next( 'dd' ).slideToggle( 200 );

			if ( window.location.hash ) {
				var scrollPaddingTop = parseInt( $('html').css('scroll-padding-top') || 0 );

				window.scrollTo( 0, $question.offset().top - scrollPaddingTop );
			}

			if ( $question.prop( 'id' ) ) {
				window.location.hash = $question.prop( 'id' );
			}
		} );

	if ( window.location.hash ) {
		jQuery(
			// Decode/Encode here is to work with any existing links that are not fully-encoded, the trim handles whitespace/newlines.
			document.getElementById( encodeURIComponent( decodeURIComponent( window.location.hash.substr(1) ).trim() ) )
		).trigger( 'click' );
	}
} )( jQuery );
