( function ( $ ) {
	const $document = $( document );

	function closePopover( $popover, $trigger ) {
		$popover.removeClass( 'is-visible' );
		$trigger.attr( 'aria-expanded', 'false' );
		$document.off( 'click.popover-close keydown.popover-close' );
	}

	$( '.popover-trigger' ).each( function () {
		const $el = $( this );
		const target = $el.data( 'target' );
		const $target = $( '#' + target );

		if ( ! $target.length ) {
			return;
		}

		$el.on( 'click', function ( event ) {
			if ( $target.hasClass( 'is-visible' ) ) {
				return;
			}

			event.stopPropagation();

			$target.addClass( 'is-visible' );
			$el.attr( 'aria-expanded', 'true' );

			const $closeButton = $target.find( '.popover-close' );

			$closeButton.on( 'click.popover-close', function () {
				closePopover( $target, $el );
			} );

			$closeButton.focus();

			$document.on(
				'click.popover-close keydown.popover-close',
				function ( closeEvent ) {
					if (
						'keydown' === closeEvent.type &&
						27 === closeEvent.which
					) {
						// Esc key.
						closePopover( $target, $el );
					} else if (
						$target[ 0 ] !== closeEvent.target &&
						! $.contains( $target[ 0 ], closeEvent.target )
					) {
						closePopover( $target, $el );
					}
				}
			);
		} );
	} );
} )( window.jQuery );
