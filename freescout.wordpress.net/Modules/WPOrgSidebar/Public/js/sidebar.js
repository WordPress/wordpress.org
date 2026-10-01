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

	/**
	 * Lets the page grow with the sidebar, which core only measures once the page has loaded.
	 *
	 * The sidebar is positioned absolutely next to the conversation from 1100px up, so its height doesn't count.
	 */
	function fitLayout() {
		if (
			typeof window.adjustCustomerSidebarHeight === 'function' &&
			$( '#conv-layout-customer' ).length &&
			$( window ).outerWidth() >= 1100
		) {
			window.adjustCustomerSidebarHeight();
		}
	}

	$( function () {
		const strings = $( '.wporg-sidebar' ).data( 'strings' ) || {};

		$( '.wporg-sidebar' ).on( 'shown.bs.collapse', fitLayout );

		const $panels = $( '.wporg-sidebar-panel[data-url]' );

		$panels.each( function () {
			loadPanel( $( this ), false );
		} );

		// Bounces and Slack notifications name someone else's account, which the profile panel offers to switch to, and back.
		$( '.wporg-sidebar' ).on(
			'click',
			'.wporg-sidebar-show-related, .wporg-sidebar-show-sender',
			function ( event ) {
				const related = $( this ).hasClass(
					'wporg-sidebar-show-related'
				);

				event.preventDefault();
				$panels.each( function () {
					loadPanel( $( this ), related );
				} );
			}
		);

		/**
		 * Loads a panel, hiding it if it has nothing to show.
		 *
		 * @param {jQuery}  $panel  Panel.
		 * @param {boolean} related Whether it's about the account a bounce or Slack notification names, not the sender's.
		 */
		function loadPanel( $panel, related ) {
			const $block = $panel.closest( '.conv-sidebar-block' );
			const previous = $panel.data( 'request' );

			// A late answer to an earlier load would show the other person.
			if ( previous ) {
				previous.abort();
			}

			const request = $.getJSON(
				$panel.data( 'url' ),
				related ? { related: 1 } : {}
			);
			$panel.data( 'request', request );

			request
				.done( function ( response ) {
					if ( response && response.html ) {
						// Trusted: rendered by the signed api.wordpress.org endpoints.
						$panel.html( response.html );
						shortenLists( $panel );
						$block.show();
					} else {
						// Not removed: the other account may have something to show.
						$block.hide();
					}
					fitLayout();
				} )
				.fail( function ( xhr, textStatus ) {
					if ( 'abort' === textStatus ) {
						return;
					}

					$panel.html(
						$( '<p class="wporg-sidebar-empty">' ).text(
							strings.failed
						)
					);
					$block.show();
				} );
		}

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
						fitLayout();
					} )
					.insertAfter( this );
			} );
		}
	} );
} )( jQuery );
