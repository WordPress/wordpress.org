( function( $ ) {
	var questions = $( 'dt', '#faq' );
	var hash      = window.location.hash.replace( /[^a-z0-9-#]/gi, '' );

	questions
		.each( function( index, question ) {
			var $question = $( question ),
				$button   = $( '<a href="#" />' ),
				id        = $question.attr( 'id' );

			// If there is no ID, create our own.
			if ( ! id ) {
				id = '#' + $question.text().toLowerCase().replace( /[^\w\s]/gi, '' ).replace( /\s/gi, '-' );
				$question.attr( 'id', id );
			}

			$button.attr( 'href', id ).on( 'click', function( event ) {
				event.preventDefault();
			} );

			$question.html( $button.text( $question.text() ) );
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

			if ( hash ) {
				window.scrollTo( 0, $question.offset().top );
			}
		} );

	if ( hash ) {
		questions.find( '[href="' + hash + '"]' ).trigger( 'click' );
	}
} )( jQuery );
