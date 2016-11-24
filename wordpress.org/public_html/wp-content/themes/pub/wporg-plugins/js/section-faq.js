( function( $ ) {
	$( '#faq' ).on( 'click', 'dt', function( event ) {
		var $question = $( event.target );

		if ( ! $question.is( '.open' ) ) {
			$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false ).next( 'dd' ).slideToggle( 200 );
		}

		$question.toggleClass( 'open' ).attr( 'aria-expanded', function( index, attribute ) {
			return 'true' !== attribute;
		} ).next( 'dd' ).slideToggle( 200 );
	});
} )( jQuery );
