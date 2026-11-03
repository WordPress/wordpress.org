( function ( $ ) {
	const questions = $( 'dt', '#faq' );

	questions
		.each( function ( index, question ) {
			const $question = $( question ),
				$button = $( '<button />' ),
				$h3 = $( '<h3 />' );

			$question.html( $h3.html( $button.text( $question.text() ) ) );
		} )
		.on( 'click', function ( event, isDeepLink ) {
			if ( 'keydown' === event.type && 13 !== event.which ) {
				return;
			}

			const $question = $( event.currentTarget );

			if ( ! $question.is( '.open' ) ) {
				$question
					.siblings( '.open' )
					.toggleClass( 'open' )
					.attr( 'aria-expanded', false )
					.next( 'dd' )
					.slideToggle( 200 );
			}

			$question
				.toggleClass( 'open' )
				.attr( 'aria-expanded', function ( index, attribute ) {
					return 'true' !== attribute;
				} )
				.next( 'dd' )
				.slideToggle( 200 );

			if ( isDeepLink ) {
				const scrollPaddingTop =
					parseInt( $( 'html' ).css( 'scroll-padding-top' ), 10 ) ||
					0;

				window.scrollTo( 0, $question.offset().top - scrollPaddingTop );
			}

			if ( $question.prop( 'id' ) ) {
				// Assigning location.hash would make the browser jump to the question.
				window.history.replaceState(
					window.history.state,
					'',
					'#' + $question.prop( 'id' )
				);
			}
		} );

	if ( window.location.hash ) {
		let uriHash = window.location.hash.substr( 1 ),
			uriElement = document.getElementById( uriHash );

		if ( ! uriElement ) {
			// Decode/Encode here is to work with any existing links that are not fully-encoded, the trim handles whitespace/newlines.
			uriHash = encodeURIComponent(
				decodeURIComponent( uriHash )
			).trim();
			uriElement = document.getElementById( uriHash );

			if ( ! uriElement ) {
				// Perform PHP-style URI encoding. See https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURI#encoding_for_rfc3986
				uriHash = uriHash.replace(
					/[!'()*]/g,
					( c ) =>
						`%${ c.charCodeAt( 0 ).toString( 16 ).toUpperCase() }`
				);
				uriElement = document.getElementById( uriHash );
			}
		}

		$( uriElement ).trigger( 'click', [ true ] );
	}
} )( window.jQuery );
