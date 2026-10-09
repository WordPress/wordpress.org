/**
 * Loads the WordPress.org sidebar panels.
 *
 * Panels are fetched after page load, so a slow or failing api.wordpress.org never blocks the conversation view.
 * They come as blocks, built here with text as text and only web links as links.
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
	 * Badge tones the stylesheet colors.
	 *
	 * @type {string[]}
	 */
	const TONES = [ 'success', 'warning', 'error', 'neutral' ];

	/**
	 * Glyphicons an item's links may use.
	 *
	 * @type {string[]}
	 */
	const ICONS = [ 'link', 'download-alt' ];

	/**
	 * Profile panel actions, by the class of the link that does them.
	 *
	 * @type {Object<string, string>}
	 */
	const ACTIONS = {
		related: 'wporg-sidebar-show-related',
		sender: 'wporg-sidebar-show-sender',
	};

	/**
	 * How long to wait before asking again whether the queue has saved the sender's photo, in milliseconds.
	 *
	 * Doubles after each check, up to PHOTO_MAX_INTERVAL.
	 *
	 * @type {number}
	 */
	const PHOTO_INTERVAL = 3000;

	/**
	 * Longest wait between checks, in milliseconds.
	 *
	 * @type {number}
	 */
	const PHOTO_MAX_INTERVAL = 30000;

	/**
	 * How many times to ask: over three minutes, since outgoing email goes ahead of the job in the queue.
	 *
	 * @type {number}
	 */
	const PHOTO_CHECKS = 10;

	/**
	 * Parses a URL, if it's a web address.
	 *
	 * @param {string} url  URL.
	 * @param {string} base URL a relative one is relative to; none if it must be absolute.
	 * @return {string} The URL, or an empty string.
	 */
	function webUrl( url, base ) {
		try {
			const parsed = new URL( String( url ), base );

			return [ 'http:', 'https:' ].includes( parsed.protocol )
				? parsed.href
				: '';
		} catch {
			return '';
		}
	}

	/**
	 * Builds a link, or plain text if there's no web address to link to.
	 *
	 * @param {string} text      Link text.
	 * @param {string} url       URL.
	 * @param {string} className Class to add.
	 * @return {jQuery} Element.
	 */
	function textLink( text, url, className ) {
		const href = webUrl( url );
		const $element = href ? $( '<a>' ).attr( 'href', href ) : $( '<span>' );

		return $element.addClass( className || '' ).text( String( text ) );
	}

	/**
	 * Builds status badges, separated by spaces from what's before them.
	 *
	 * @param {Array} badges Badges: label, and tone.
	 * @return {Array} Nodes.
	 */
	function renderBadges( badges ) {
		const nodes = [];

		( badges || [] ).forEach( function ( badge ) {
			const tone = TONES.includes( badge.tone ) ? badge.tone : 'neutral';

			nodes.push(
				' ',
				$( '<span class="wporg-sidebar-badge">' )
					.addClass( 'is-' + tone )
					.text( String( badge.label ) )
			);
		} );

		return nodes;
	}

	/**
	 * Builds a list item: a plugin, a signup, a note, and the like.
	 *
	 * @param {Object} item Item: title, url, tooltip, badges, note, meta, and links; all optional.
	 * @return {jQuery} Element.
	 */
	function renderItem( item ) {
		const $item = $( '<li class="wporg-sidebar-item">' );

		if ( item.tooltip ) {
			$item.attr( 'title', String( item.tooltip ) );
		}

		if ( item.links && item.links.length ) {
			const $links = $( '<span class="wporg-sidebar-item-links">' );

			item.links.forEach( function ( link, index ) {
				const href = webUrl( link.url );
				const icon = ICONS.includes( link.icon ) ? link.icon : 'link';

				if ( ! href ) {
					return;
				}

				$links.append(
					index ? ' ' : '',
					$( '<a>' )
						.attr( {
							href,
							title: String( link.text ),
							'aria-label': String( link.text ),
						} )
						.append(
							$( '<i class="glyphicon">' ).addClass(
								'glyphicon-' + icon
							)
						)
				);
			} );
			$item.append( $links );
		}

		if ( item.title ) {
			$item.append(
				textLink( item.title, item.url, 'wporg-sidebar-item-title' ),
				renderBadges( item.badges )
			);
		}

		if ( item.note ) {
			$item.append(
				$( '<p class="wporg-sidebar-note">' ).text(
					String( item.note )
				)
			);
		}

		if ( item.meta && item.meta.length ) {
			const $meta = $( '<div class="wporg-sidebar-item-meta">' );

			item.meta.forEach( function ( part, index ) {
				const $part = textLink( part.text, part.url );

				if ( part.tooltip ) {
					$part.attr( 'title', String( part.tooltip ) );
				}
				$meta.append( index ? ' · ' : '', $part );
			} );
			$item.append( $meta );
		}

		return $item;
	}

	/**
	 * Builds one block of a panel.
	 *
	 * @param {Object} block Block, by type: lead, meta, empty, notice, links, heading, or items.
	 * @return {jQuery|null} Element, or null for a type this doesn't know.
	 */
	function renderBlock( block ) {
		switch ( block && block.type ) {
			case 'lead':
				return $( '<p class="wporg-sidebar-lead">' ).append(
					textLink( block.text, block.url ),
					renderBadges( block.badges )
				);

			case 'meta':
				return $( '<p class="wporg-sidebar-meta">' ).text(
					String( block.text )
				);

			case 'empty':
				return $( '<p class="wporg-sidebar-empty">' ).text(
					String( block.text )
				);

			case 'notice':
				if ( ! ACTIONS[ block.action ] ) {
					return null;
				}

				return $( '<p class="wporg-sidebar-meta">' ).append(
					document.createTextNode( String( block.text ) + ' ' ),
					$( '<a href="#">' )
						.addClass( ACTIONS[ block.action ] )
						.text( String( block.action_text ) )
				);

			case 'links':
				return $( '<ul class="wporg-sidebar-links">' ).append(
					( block.links || [] ).map( ( link ) =>
						$( '<li>' ).append( textLink( link.text, link.url ) )
					)
				);

			case 'heading': {
				const $heading = $(
					'<h5 class="wporg-sidebar-heading">'
				).append( textLink( block.text, block.url ) );

				if ( undefined !== block.count ) {
					$heading.append(
						' ',
						$( '<span class="wporg-sidebar-count">' ).text(
							String( block.count )
						)
					);
				}

				return $heading;
			}

			case 'items':
				return $( '<ul class="wporg-sidebar-items">' ).append(
					( block.items || [] ).map( renderItem )
				);
		}

		return null;
	}

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

	/**
	 * Shows the sender's photo once the queue has saved their WordPress.org avatar, since the page was drawn without it.
	 *
	 * @param {string} url Address that answers with the photo, once there is one.
	 */
	function showSenderPhoto( url ) {
		let checks = 0;
		let interval = PHOTO_INTERVAL;

		/**
		 * Asks again later, unless that was the last time.
		 */
		function retry() {
			if ( checks < PHOTO_CHECKS ) {
				setTimeout( check, interval );
				interval = Math.min( interval * 2, PHOTO_MAX_INTERVAL );
			}
		}

		/**
		 * Asks once; a failed request, like one over the rate limit, counts as a check.
		 */
		function check() {
			checks++;
			$.getJSON( url )
				.fail( retry )
				.done( function ( photo ) {
					const src =
						photo && photo.url
							? webUrl( photo.url, window.location.href )
							: '';

					if ( ! src ) {
						// Not once the job ran without saving one, like for an account without an avatar.
						if ( photo && photo.pending ) {
							retry();
						}
						return;
					}

					$( '.customer-photo' ).attr( 'src', src );

					// The sender's messages, which link to their page; not those of others on the conversation.
					$( '.thread-person a' )
						.filter( function () {
							return this.href === photo.sender;
						} )
						.closest( '.thread' )
						.find( '.thread-photo .person-photo' )
						.attr( 'src', src );
				} );
		}

		check();
	}

	$( function () {
		const strings = $( '.wporg-sidebar' ).data( 'strings' ) || {};
		let watchingPhoto = false;

		$( '.wporg-sidebar' ).on( 'shown.bs.collapse', fitLayout );

		const $panels = $( '.wporg-sidebar-panel[data-url]' );

		$panels.each( function () {
			loadPanel( $( this ), false );
		} );

		// Bounces and Slack notifications name someone else's account, which the profile panel offers to switch to, and back.
		$( '.wporg-sidebar' ).on(
			'click',
			'.' + ACTIONS.related + ', .' + ACTIONS.sender,
			function ( event ) {
				const related = $( this ).hasClass( ACTIONS.related );

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
					if (
						response &&
						response.sender_photo &&
						! watchingPhoto
					) {
						watchingPhoto = true;
						showSenderPhoto( response.sender_photo );
					}

					const blocks =
						response && Array.isArray( response.blocks )
							? response.blocks
									.map( renderBlock )
									.filter( Boolean )
							: [];

					if ( blocks.length ) {
						$panel.empty().append( blocks );
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
