jQuery(document).ready( function($) {
	// The block editor may have multiple `<code>` within a `<pre>`.
	$( '.bbp-topic-content > pre, .bbp-reply-content > pre' ).each( function() {
		var $el = $( this );

		if (
			typeof this.scrollHeight === 'undefined' ||
			$el.outerHeight( true ) >= this.scrollHeight
		) {
			return;
		}

		// Add a CSS selector.
		$el.addClass( 'wporg-bbp-code-expander-code-wrapper' );

		var btn = $( '<div class="wporg-bbp-code-tools"><a class="wporg-bbp-code-expand" href="#"></a></div>' );

		btn.find( 'a' ).text( bbpCodeBlocksExpandContract.expand );

		btn.insertAfter( $el );
	});

	$( '.wporg-bbp-code-expand' ).on( 'click', function(e) {
		e.preventDefault();

		var pre = $( this ).closest( 'div' ).prev( 'pre' ),
			heightGoal,
			maxHeightGoal,
			scrollGoal;

		if ( $( this ).hasClass( 'wporg-bbp-code-expanded' ) ) {
			maxHeightGoal = pre.data( 'bbpInitHeight' );
			scrollGoal = pre.offset().top - 45;

			// Account for the WordPress.org headers.
			scrollGoal -= jQuery( 'header.wp-block-group.global-header' ).height() || 0;
			scrollGoal -= jQuery( '#wpadminbar' ).height() || 0;

		} else {
			pre.data( 'bbpInitHeight', pre.css( 'max-height' ) );
			heightGoal = pre.get(0).scrollHeight + 2 * pre.css('padding-top');
			maxHeightGoal = 'none';
		}

		if ( typeof heightGoal !== 'undefined' ) {
			pre.css( 'max-height', maxHeightGoal );
			pre.animate( { height: heightGoal } );
			$( this ).text( bbpCodeBlocksExpandContract.contract );
		} else {
			$( [document.documentElement, document.body] ).animate({
					scrollTop: scrollGoal
				},
				300,
				'swing',
				function() {
					pre.css( 'max-height', maxHeightGoal );
				}
			);

			$( this ).text( bbpCodeBlocksExpandContract.expand );
		}

		$( this ).toggleClass( 'wporg-bbp-code-expanded' );

		return false;
	});
});