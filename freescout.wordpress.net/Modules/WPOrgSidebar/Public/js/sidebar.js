/**
 * Loads the WordPress.org sidebar panels.
 *
 * Panels are fetched after page load, so a slow or failing api.wordpress.org never blocks the conversation view.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * How many items a list shows before "View all".
	 *
	 * @type {number}
	 */
	const VISIBLE_ITEMS = 5;

	$( function () {
		const strings = $( '.wporg-sidebar' ).data( 'strings' ) || {};

		$( '.wporg-sidebar-panel[data-url]' ).each( function () {
			const $panel = $( this );

			$.getJSON( $panel.data( 'url' ) )
				.done( function ( response ) {
					if ( response && response.html ) {
						// Trusted: rendered by the signed api.wordpress.org endpoints.
						$panel.html( response.html );
						shortenLists( $panel );
					} else {
						$panel.closest( '.conv-sidebar-block' ).remove();
					}
				} )
				.fail( function () {
					$panel.html(
						$( '<p class="wporg-sidebar-empty">' ).text(
							strings.failed
						)
					);
				} );
		} );

		/**
		 * Hides all but the first items of long lists, behind a "View all" link like core's.
		 *
		 * @param {jQuery} $panel Panel.
		 */
		function shortenLists( $panel ) {
			$panel.find( '.wporg-sidebar-items' ).each( function () {
				const $items = $( this ).children();

				// Hiding a single item would save no space.
				if ( $items.length <= VISIBLE_ITEMS + 1 ) {
					return;
				}

				$items.slice( VISIBLE_ITEMS ).hide();

				$( '<a href="#" class="sidebar-block-link link-blue">' )
					.text(
						String( strings.view_all ).replace(
							':number',
							() => $items.length
						)
					)
					.on( 'click', function ( event ) {
						event.preventDefault();
						$items.show();
						$( this ).remove();
					} )
					.insertAfter( this );
			} );
		}
	} );
} )( jQuery );
