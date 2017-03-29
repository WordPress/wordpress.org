( function( $ ) {
	$( 'dt', '#faq' )
		.each( function( index, question ) {
			var $question = $( question ),
				$button   = $( '<button type="button" class="button-link" aria-expanded="false" />' );

			$question.html( $button.text( $question.text() ) );
		} )
		.on( 'mousedown keydown', '.button-link', function( event ) {
			var $question = $( event.target );

			if ( 'keydown' === event.type && 13 !== event.which ) {
				return;
			}

			$question.toggleClass( 'no-focus', 'mousedown' === event.type );

			if ( ! $question.is( '.open' ) ) {
				$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false ).parent().next( 'dd' ).slideToggle( 200 );
			}

			$question.parent().toggleClass( 'open' ).attr( 'aria-expanded', function( index, attribute ) {
				return 'true' !== attribute;
			} ).next( 'dd' ).slideToggle( 200 );
		} );
} )( jQuery );
