jQuery(document).ready( function($) {
	$( '.bbp-topic-content > pre > code, .bbp-reply-content > pre > code' ).each( function() {
		var $el = $( this );

		if ( typeof this.scrollHeight !== 'undefined' && $el.height() < this.scrollHeight ) {
			var btn = $( '<div class="wporg-bbp-code-tools"><a class="wporg-bbp-code-expand" href="#"></a></div>' );

			btn.find( 'a' ).text( bbpCodeBlocksExpandContract.expand );

			btn.insertAfter( $el.closest( 'pre' ) )
		}
	});

	$( '.wporg-bbp-code-expand' ).on( 'click', function(el) {
		var pre = $( this ).closest( 'div' ).prev( 'pre' ),
			code = pre.find( 'code' ),
			heightGoal,
			maxHeightGoal,
			scrollGoal;

		if ( $( this ).hasClass( 'wporg-bbp-code-expanded' ) ) {
			maxHeightGoal = pre.data( 'bbpInitHeight' );
			scrollGoal = pre.offset().top - 45;
		} else {
			pre.data( 'bbpInitHeight', pre.css( 'max-height' ) );
			heightGoal = code.get( 0 ).scrollHeight;
			maxHeightGoal = 'none';
		}

		if ( typeof heightGoal !== 'undefined' ) {
			pre.css( 'max-height', maxHeightGoal );
			code.css( 'max-height', maxHeightGoal );
			code.animate( { height: heightGoal });
			$( this ).text( bbpCodeBlocksExpandContract.contract );
		} else {
			$( [document.documentElement, document.body] ).animate({
			        scrollTop: scrollGoal
			    },
			    600,
			    'swing',
			    function() {
					pre.css( 'max-height', maxHeightGoal );
					code.css( 'max-height', maxHeightGoal );
			    }
		    );

			$( this ).text( bbpCodeBlocksExpandContract.expand );
		}

		$( this ).toggleClass( 'wporg-bbp-code-expanded' );

		return false;
	});
});