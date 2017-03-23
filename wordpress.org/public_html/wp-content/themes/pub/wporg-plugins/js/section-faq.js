( function( $ ) {
	$( '#faq' ).on( 'mousedown keydown', '.button-link', function( event ) {
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
	})
		.on( 'click', 'dt', function( event ) {
		var $question = $( event.target );

		if ( ! $question.is( '.open' ) ) {
			$question.siblings( '.open' ).toggleClass( 'open' ).attr( 'aria-expanded', false ).next( 'dd' ).slideToggle( 200 );
		}

		$question.toggleClass( 'open' ).attr( 'aria-expanded', function( index, attribute ) {
			return 'true' !== attribute;
		} ).next( 'dd' ).slideToggle( 200 );
	});
} )( jQuery );
