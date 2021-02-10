/**
 * WordPress.org bbPress Also Viewing.
 * 
 * This was based on the Also Viewing User Script by Jason Stallings
 * @see https://gist.github.com/Clorith/20596176f85495da865cf41a90b43576
 */
(function() {
	var options = window._alsoViewing,
		currentlyViewing = options.currentlyViewing || [],
		page = options.currentPage || '',
		banner = false,
		isTyping = false,
		_n = wp.i18n._n,
		__ = wp.i18n.__,
		NoLongerTyping;

	if ( ! options || ! page ) {
		return;
	}

	jQuery( document ).on( 'ready', function($) {
		maybeDisplay();

		if ( options.heartbeatTime ) {
			setInterval( updateState, options.heartbeatTime * 1000 );
		}

		if ( options.refreshInterval ) {
			setInterval( refreshViewers, options.refreshInterval * 1000  );
		}
	} );

	// Display the banner when required.
	function maybeDisplay() {
		var userCount = currentlyViewing.length,
			userList = [],
			userlistPretty;

		if ( ! banner ) {
			if ( ! userCount ) {
				return;
			}

			jQuery('#main').before(
				'<div id="also-viewing-banner" style="display: none; font-size: 14px; color: #fff; line-height: 30px; font-family: Helvetica,sans-serif; background: #d54e21; border-bottom: 1px solid #dfdfdf; width:100%; height:30px; text-align: center; position: initial; top: 32px; left: 0; z-index: 9999;"></div>'
			);
			banner = jQuery( '#also-viewing-banner' );
		}

		if ( ! userCount ) {
			banner.show( false );
		} else {
			userList = currentlyViewing.map( function( item ) {
				return item.who + ( item.isTyping ? ' ' + __( '(is typing)', 'wporg-bbp-also-viewing' ) : '' );
			} );

			if ( userCount > 1 ) {
				userlistPretty = __( '%1$s and %2$s' )
				.replace( '%1$s', userList.slice( 0, -1 ).join( ', ' ) + ( userCount > 2 ? ',' : '' ) )
				.replace( '%2$s', userList.slice( -1 ) );
			} else {
				userlistPretty = userList.join( ', ' ); // only one element.
			}

			banner.text(
				_n( '%s is also viewing this page.', '%s are also viewing this page.', userCount, 'wporg-bbp-also-viewing' )
				.replace( '%s', userlistPretty )
			);
			banner.show( true );
		}
	}

	// Pin the banner to the top of the screen when scrolling.
	jQuery(window).scroll( function() {
		if ( ! banner ) {
			return;
		}

		if ( jQuery(window).scrollTop() > 200 ) {
			banner.css( 'position', 'fixed' );
		} else {
			banner.css( 'position', 'initial' );
		}
	});

	// When a textarea is focused, mark the user as typing.
	jQuery(document).on( 'keydown', 'textarea', function() {
		transmitIsTyping();

		clearInterval( NoLongerTyping );

		NoLongerTyping = setTimeout(function() {
			transmitNoLongerTyping();
		}, 15000 );
	} );

	function transmitIsTyping() {
		// Avoid re-transmitting repeatedly, we only need to do so on state changes.
		if ( isTyping ) {
			return;
		}

		isTyping = true;

		updateState();
	}

	function transmitNoLongerTyping() {
		// Avoid re-transmitting repeatedly, we only need to do so on state changes.
		if ( ! isTyping ) {
			return;
		}

		isTyping = false;

		updateState();
	}

	// Update the viewing state of the browser on the server.
	// This ensures that we're still shown as viewing / typing.
	function updateState() {
		jQuery.ajax( {
			url: options.restAPIEndpoint + page,
			method: 'POST',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', options.restAPINonce );
			},
			data: {
				isTyping: isTyping,
			}
		} );
	}

	// Update the list of users currently viewing this page.
	function refreshViewers() {
		jQuery.ajax( {
			url: options.restAPIEndpoint + page,
			method: 'GET',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', options.restAPINonce );
			}
		} ).done( function( response ) {
			currentlyViewing = response;
			maybeDisplay();
		} );
	}

	// Remove us as viewing this page.
	jQuery(window).on( 'beforeunload', function() {
		jQuery.ajax( {
			url: options.restAPIEndpoint + page,
			method: 'DELETE',
			beforeSend: function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', options.restAPINonce );
			}
		} );
	} );

	// DEBUG. Expose the functions for testing.
	window.alsoViewing = {
		refreshViewers: refreshViewers,
		updateState: updateState,
		transmitIsTyping: transmitIsTyping,
		transmitNoLongerTyping: transmitNoLongerTyping,
	}
})();