( function( $ ) {
	$( 'dt', '#faq' )
		.each( function( index, question ) {
			var $question = $( question ),
				$button   = $( '<button type="button" class="button-link" aria-expanded="false" />' );

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
		} );
} )( jQuery );
