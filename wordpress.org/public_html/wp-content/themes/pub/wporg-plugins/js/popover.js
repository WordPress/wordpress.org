( function( $ ) {
	var $document = $( document );

	function closePopover( $popover, $trigger ) {
		$popover.removeClass( 'is-visible' );
		$trigger.attr( 'aria-expanded', 'false' );
		$document.off( 'click.popover-close keydown.popover-close' );
	}

	$( '.popover-trigger' ).each( function() {
		var $el = $( this );
		var target = $el.data( 'target' );
		var $target = $( '#' + target );

		if ( ! $target.length ) {
			return;
		}

		$el.on( 'click', function( event ) {
			if ( $target.hasClass( 'is-visible' ) ) {
				return;
			}

			event.stopPropagation();

			$target.addClass( 'is-visible' );
			$el.attr( 'aria-expanded', 'true' );

			var $closeButton = $target.find( '.popover-close' );

			$closeButton.on( 'click.popover-close', function() {
				closePopover( $target, $el );
			} );

			$closeButton.focus();

			$document.on( 'click.popover-close keydown.popover-close', function( event ) {
				if ( 'keydown' === event.type && 27 === event.which ) { // Esc key.
					closePopover( $target, $el );
				} else if (  $target[0] !== event.target && ! $.contains( $target[0], event.target ) ) {
					closePopover( $target, $el );
				}
			} );
		} );
	} );
} )( jQuery );
