/**
 * Makes the Plugin Review panel work: copying, jumping to issues, loading the plugin as it is now, flagging and
 * shortening the tab's title, and warning about a reply with another author's Review ID.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * What goes before the tab's title, by flag, in the order they go in.
	 *
	 * @type {Object<string, string>}
	 */
	const TITLE_MARKS = {
		UPD: '🔴',
		TRM: '🟠',
		OWN: '🟤',
	};

	/**
	 * How long "Copied" shows, in milliseconds.
	 *
	 * @type {number}
	 */
	const COPIED_FOR = 1200;

	/**
	 * Where the browser keeps whether the agent prefers the tab's full title.
	 *
	 * @type {string}
	 */
	const FULL_TITLE_KEY = 'wporgpluginreview.fullTitle';

	/**
	 * A date, as Review IDs write them, like 29Jul26.
	 *
	 * @type {RegExp}
	 */
	const DATE = /^\d{1,2}[A-Z][a-z]{2}\d{2}$/;

	/**
	 * How many times a Review ID's plugin was reviewed: T3, or TX when it isn't known.
	 *
	 * @type {RegExp}
	 */
	const TIMES = /^T(?:\d+|X)$/;

	/**
	 * What a Review ID writes for a piece the review couldn't tell; it's read as missing.
	 *
	 * @type {string}
	 */
	const UNKNOWN = 'unknown';

	/**
	 * A Review ID's plugin ID, which can be anywhere in it.
	 *
	 * @type {RegExp}
	 */
	const PLUGIN_ID = /P0TDX\d+HGN/;

	/**
	 * The mark before a Review ID's flags.
	 *
	 * @type {string}
	 */
	const FLAG_MARK = '\u2757';

	/**
	 * Copies text, and HTML if there is any, to the clipboard.
	 *
	 * @param {string} text Text.
	 * @param {string} html HTML; optional.
	 * @return {Promise} Resolves once it's copied.
	 */
	function copy( text, html ) {
		const clipboard = window.navigator.clipboard;

		// Only secure pages have a clipboard.
		if ( ! clipboard ) {
			return Promise.reject( new Error( 'No clipboard' ) );
		}

		if ( html && window.ClipboardItem && clipboard.write ) {
			return clipboard.write( [
				new window.ClipboardItem( {
					'text/plain': new Blob( [ text ], { type: 'text/plain' } ),
					'text/html': new Blob( [ html ], { type: 'text/html' } ),
				} ),
			] );
		}

		return clipboard.writeText( text );
	}

	/**
	 * Shows that something was copied, next to its button.
	 *
	 * @param {jQuery} $button Button.
	 * @param {string} label   Text to show.
	 */
	function showCopied( $button, label ) {
		const $note = $( '<span class="wporg-review-copied" role="status">' )
			.text( label )
			.insertAfter( $button );

		setTimeout( () => $note.remove(), COPIED_FOR );
	}

	/**
	 * A reply's text: its HTML with a line for each line break and paragraph.
	 *
	 * Read into a document of its own, which runs and loads nothing.
	 *
	 * @param {string} html The reply.
	 * @return {string} Text.
	 */
	function replyText( html ) {
		const body = new window.DOMParser().parseFromString(
			html,
			'text/html'
		).body;
		$( body ).find( 'br' ).replaceWith( '\n' );
		$( body ).find( 'p, li' ).append( '\n\n' );

		return body.textContent.replace( /\n{3,}/g, '\n\n' ).trim();
	}

	/**
	 * Finds where an issue's title starts in a thread.
	 *
	 * @param {Element} thread Thread.
	 * @param {string}  title  The issue's title.
	 * @return {Element|null} The element whose text starts the title.
	 */
	function findIssue( thread, title ) {
		const needles = [ title, title.slice( 0, 40 ) ].map( ( needle ) =>
			needle.toLowerCase()
		);
		const walker = document.createTreeWalker(
			thread,
			window.NodeFilter.SHOW_TEXT
		);

		while ( walker.nextNode() ) {
			const text = walker.currentNode.nodeValue.toLowerCase();
			if (
				needles.some( ( needle ) => needle && text.includes( needle ) )
			) {
				return walker.currentNode.parentElement;
			}
		}

		return null;
	}

	/**
	 * Scrolls to an issue in the review email, and highlights it for a moment.
	 *
	 * @param {jQuery} $button The issue's button.
	 */
	function showIssue( $button ) {
		const thread = document.getElementById(
			'thread-' + Number( $button.data( 'threadId' ) )
		);
		if ( ! thread ) {
			return;
		}

		const target =
			findIssue( thread, String( $button.attr( 'data-title' ) ) ) ||
			thread;
		target.scrollIntoView( { behavior: 'smooth', block: 'center' } );

		$( target ).addClass( 'wporg-review-highlight' );
		setTimeout(
			() => $( target ).removeClass( 'wporg-review-highlight' ),
			COPIED_FOR
		);
	}

	/**
	 * Normalizes a name for comparing, without case, quotes, or extra spaces.
	 *
	 * @param {string} name Name.
	 * @return {string} Normalized name.
	 */
	function normalizeName( name ) {
		return String( name || '' )
			.toLowerCase()
			.replace( /[\u2018\u2019\u201C\u201D"']/g, '' )
			.replace( /\s+/g, ' ' )
			.trim();
	}

	/**
	 * Builds a copy button.
	 *
	 * @param {string} text  What it copies.
	 * @param {string} label Its label.
	 * @return {jQuery} Button.
	 */
	function copyButton( text, label ) {
		return $( '<button type="button" class="wporg-review-copy">' )
			.attr( { 'data-copy': text, title: label, 'aria-label': label } )
			.append( '<i class="glyphicon glyphicon-copy"></i>' );
	}

	/**
	 * Adds a row to details.
	 *
	 * @param {jQuery}               $details Details.
	 * @param {string}               label    The row's label.
	 * @param {Array<string|jQuery>} content  What it shows: text, or elements.
	 */
	function addRow( $details, label, content ) {
		$details.append(
			$( '<dt>' ).text( label ),
			$( '<dd>' ).append(
				content.map( ( part ) =>
					'string' === typeof part
						? document.createTextNode( part )
						: part
				)
			)
		);
	}

	/**
	 * Parses a URL, if it's an absolute web address.
	 *
	 * @param {string} url URL.
	 * @return {string} The URL, or an empty string.
	 */
	function webUrl( url ) {
		try {
			const parsed = new URL( String( url ) );

			return [ 'http:', 'https:' ].includes( parsed.protocol )
				? parsed.href
				: '';
		} catch {
			return '';
		}
	}

	/**
	 * Shows the plugin as it is now: its status, ZIP, and page, its current name, and who submitted it.
	 *
	 * @param {jQuery} $panel  Panel.
	 * @param {Object} plugin  The plugin.
	 * @param {Object} strings Labels.
	 */
	function showPlugin( $panel, plugin, strings ) {
		const $details = $panel.find( '.wporg-review-plugin' );
		const actions = [];

		if ( plugin.download_url ) {
			actions.push(
				copyButton( String( plugin.download_url ), strings.download )
					.find( 'i' )
					.attr( 'class', 'glyphicon glyphicon-download-alt' )
					.end()
			);
		}
		if ( plugin.released && webUrl( plugin.page_url ) ) {
			actions.push(
				$(
					'<a class="wporg-review-action" target="_blank" rel="noopener noreferrer">'
				)
					.attr( {
						href: webUrl( plugin.page_url ),
						title: strings.page,
						'aria-label': strings.page,
					} )
					.append( '<i class="glyphicon glyphicon-link"></i>' )
			);
		}
		if ( webUrl( plugin.scans_url ) ) {
			actions.push(
				$(
					'<a class="wporg-review-action" target="_blank" rel="noopener noreferrer">'
				)
					.attr( {
						href: webUrl( plugin.scans_url ),
						title: strings.scans,
						'aria-label': strings.scans,
					} )
					.append( '<i class="glyphicon glyphicon-tower"></i>' )
			);
		}
		// A slug the Review ID had no plugin ID for links to the plugin, now it's found.
		const $name = $details.find( '.wporg-review-slug-name' );
		if ( $name.length && webUrl( plugin.edit_url ) ) {
			$name.replaceWith(
				$( '<a target="_blank" rel="noopener noreferrer">' )
					.attr( {
						href: webUrl( plugin.edit_url ),
						title: strings.edit,
					} )
					.text( $name.text() )
			);
		}

		// Next to the slug, which the Review ID may leave out.
		const $slug = $details.find( '.wporg-review-slug' );
		if ( $slug.length ) {
			$slug.append( actions );
		} else if ( actions.length ) {
			addRow( $details, strings.slug, [
				String( plugin.slug ),
				...actions,
			] );
		}

		addRow( $details, strings.status, [
			$( '<span class="wporg-review-status">' )
				.attr( 'data-status', String( plugin.status ) )
				.text( String( plugin.status_label ) ),
		] );
		if ( plugin.reviewer ) {
			addRow( $details, strings.assigned, [ String( plugin.reviewer ) ] );
		}

		const $names = $panel.find( '.wporg-review-names' );
		if ( $names.length && plugin.name ) {
			const current = normalizeName( plugin.name );
			// The suggested name wins, should it be the original too.
			const suggested =
				current === normalizeName( $names.attr( 'data-suggested' ) );
			$names
				.toggleClass( 'is-suggested', suggested )
				.toggleClass(
					'is-original',
					! suggested &&
						current ===
							normalizeName( $names.attr( 'data-original' ) )
				);
			addRow( $names, strings.current, [
				String( plugin.name ),
				$( '<br>' ),
				$( '<code>' ).text( String( plugin.slug ) ),
				copyButton( String( plugin.slug ), strings.copy_slug ),
			] );
		}
	}

	/**
	 * Shows whether the plugin's domains carry the author's record, and whether the submitter's address is at one.
	 *
	 * @param {jQuery}      $owner  The owner details.
	 * @param {Object}      owner   The check.
	 * @param {Object|null} plugin  The plugin, if known.
	 * @param {Object}      strings Labels.
	 */
	function showOwner( $owner, owner, plugin, strings ) {
		[ 'author', 'plugin' ].forEach( ( key ) => {
			if ( true === owner[ key ] ) {
				$owner
					.find( '[data-host="' + key + '"]' )
					.append(
						$( '<span class="wporg-review-badge">' ).text(
							strings.verified
						)
					);
			}
		} );

		if ( plugin && plugin.submitter ) {
			addRow( $owner, strings.submitter, [
				String( plugin.submitter.username ),
				$( '<br>' ),
				$( '<span class="wporg-review-email">' )
					.toggleClass( 'is-match', true === owner.email )
					.attr(
						'title',
						true === owner.email
							? strings.email_match
							: strings.email_other
					)
					.text( String( plugin.submitter.email ) ),
			] );
		}
	}

	/**
	 * Asks for what isn't in the review emails, and shows it.
	 *
	 * @param {jQuery}                 $panel   Panel.
	 * @param {Object}                 strings  Labels.
	 * @param {function(Object): void} onPlugin Called with the plugin, once it's known.
	 */
	function loadPlugin( $panel, strings, onPlugin ) {
		$.getJSON( String( $panel.data( 'url' ) ) )
			.done( ( result ) => {
				const plugin = result && result.plugin ? result.plugin : null;

				if ( plugin ) {
					showPlugin( $panel, plugin, strings );
					onPlugin( plugin );
				}
				if ( result && result.owner ) {
					showOwner(
						$panel.find( '.wporg-review-owner' ),
						result.owner,
						plugin,
						strings
					);
				}
			} )
			.fail( ( xhr ) => {
				addRow( $panel.find( '.wporg-review-plugin' ), strings.status, [
					$( '<span class="wporg-review-error">' ).text(
						429 === xhr.status ? strings.throttled : strings.failed
					),
				] );
			} );
	}

	/**
	 * Reads the agent's preference for the tab's title.
	 *
	 * @return {boolean} Whether they want the full title.
	 */
	function prefersFullTitle() {
		try {
			return '1' === window.localStorage.getItem( FULL_TITLE_KEY );
		} catch {
			return false;
		}
	}

	/**
	 * Keeps the agent's preference for the tab's title.
	 *
	 * @param {boolean} full Whether they want the full title.
	 */
	function preferFullTitle( full ) {
		try {
			if ( full ) {
				window.localStorage.setItem( FULL_TITLE_KEY, '1' );
			} else {
				window.localStorage.removeItem( FULL_TITLE_KEY );
			}
		} catch {
			// Then it's only for this page.
		}
	}

	/**
	 * Puts the marks of the review's flags before the tab's title, shortens the subject in it, and keeps it that way as
	 * FreeScout changes it, like when it counts new replies.
	 *
	 * @param {jQuery}   $panel  Panel.
	 * @param {string[]} flags   Flags.
	 * @param {Object}   strings Labels.
	 */
	function manageTitle( $panel, flags, strings ) {
		const marks = Object.keys( TITLE_MARKS )
			.filter( ( flag ) => flags.includes( flag ) )
			.map( ( flag ) => TITLE_MARKS[ flag ] )
			.join( '' );
		const subject = String( $panel.attr( 'data-subject' ) || '' );
		let shortSubject = String( $panel.attr( 'data-short-subject' ) || '' );

		// Shortening only goes one way: a short subject that holds the full one could never be told from it.
		if ( ! subject || shortSubject.includes( subject ) ) {
			shortSubject = '';
		}

		if ( ! marks && ! shortSubject ) {
			return;
		}

		const $toggle = $panel.find( '.wporg-review-title-toggle' );
		let full = prefersFullTitle();

		// FreeScout's count of new replies goes first, then the marks.
		const prefix = new RegExp(
			'^(\\(\\d+\\) )?(?:[' +
				Object.values( TITLE_MARKS ).join( '' ) +
				']+ )?',
			'u'
		);
		const update = () => {
			const match = document.title.match( prefix );
			let rest = document.title.slice( match[ 0 ].length );

			/*
			 * Each way only replaces what the other way leaves, so another pass changes nothing: the short subject may be
			 * part of the full one, but not the other way around.
			 */
			if ( shortSubject && full && ! rest.includes( subject ) ) {
				rest = rest.replace( shortSubject, subject );
			} else if ( shortSubject && ! full ) {
				rest = rest.replace( subject, shortSubject );
			}

			const title =
				( match[ 1 ] || '' ) + ( marks ? marks + ' ' : '' ) + rest;
			if ( document.title !== title ) {
				document.title = title;
			}
		};
		const label = () =>
			$toggle.text( full ? strings.short_title : strings.full_title );

		update();
		label();

		$toggle.on( 'click', () => {
			full = ! full;
			preferFullTitle( full );
			update();
			label();
		} );

		const element = document.querySelector( 'title' );
		if ( element ) {
			new window.MutationObserver( update ).observe( element, {
				childList: true,
				characterData: true,
				subtree: true,
			} );
		}
	}

	/**
	 * The username in the last Review ID line of a text, read the way the module's ReviewId class reads it.
	 *
	 * @param {string} text Text.
	 * @return {string} Username; empty if there's no Review ID, or it names no one.
	 */
	function reviewIdUsername( text ) {
		const line = text
			.split( '\n' )
			.map( ( piece ) => piece.replace( /[\s\u00A0]+/g, ' ' ).trim() )
			.filter( ( piece ) => /^Review ID:/i.test( piece ) )
			.pop();
		if ( ! line ) {
			return '';
		}

		let tokens = line
			.replace( /^Review ID:/i, '' )
			.replace( /\uFE0F/g, '' )
			.split( ' ' )
			.filter( Boolean );

		// The type, then the flags, after their marks, glued to them or not.
		tokens.shift();
		while ( tokens.length && tokens[ 0 ].startsWith( FLAG_MARK ) ) {
			const glued = tokens.shift().slice( FLAG_MARK.length ).trim();
			if (
				! glued &&
				tokens.length &&
				/^[A-Za-z0-9-]+$/.test( tokens[ 0 ] )
			) {
				tokens.shift();
			}
		}
		tokens = tokens.filter( ( token ) => ! PLUGIN_ID.test( token ) );

		/*
		 * The review's date and version come last; without a date, the last piece is still them, unless it's the only
		 * one. Only what comes before them is the slug and the username.
		 */
		let tail = -1;
		for ( let i = tokens.length - 1; i >= 0; i-- ) {
			if ( DATE.test( tokens[ i ].split( '/' )[ 0 ] ) ) {
				tail = i;
				break;
			}
		}
		if ( -1 === tail && tokens.length > 1 ) {
			tail = tokens.length - 1;
		}

		const segments = tokens
			.slice( 0, -1 === tail ? tokens.length : tail )
			.join( '/' )
			.split( '/' )
			.map( ( segment ) => segment.trim() )
			.filter( Boolean )
			.map( ( segment ) => ( UNKNOWN === segment ? '' : segment ) );

		// The number of reviews goes last, the first review's date before it.
		if (
			segments.length &&
			TIMES.test( segments[ segments.length - 1 ] )
		) {
			segments.pop();
		}
		if ( segments.length && DATE.test( segments[ segments.length - 1 ] ) ) {
			segments.pop();
		}

		return segments[ 1 ] || '';
	}

	/**
	 * Warns when the reply being written has a Review ID for someone other than the plugin's author, which would send
	 * one author's review to another; until the agent says it's the right one.
	 *
	 * @param {Set<string>} authors Usernames the plugin's author goes by, lowercase: the latest review's, and the
	 *                              submitter's once it's known.
	 * @param {Object}      strings Labels.
	 */
	function watchReply( authors, strings ) {
		const acknowledged = new Set();
		let $warning = $();

		const check = () => {
			const editable = document.querySelector(
				'.conv-reply-body .note-editable'
			);
			const username = editable
				? reviewIdUsername( editable.innerText || '' )
				: '';
			const key = username.toLowerCase();
			const mismatch =
				'' !== key &&
				authors.size > 0 &&
				! authors.has( key ) &&
				! acknowledged.has( key );

			if ( ! mismatch ) {
				$warning.remove();
				$warning = $();

				return;
			}
			if ( $warning.length && key === $warning.attr( 'data-username' ) ) {
				return;
			}

			$warning.remove();
			$warning = $(
				'<div class="alert alert-danger wporg-review-mismatch" role="alert">'
			)
				.attr( 'data-username', key )
				.append(
					$( '<span>' ).text(
						String( strings.mismatch ).replace(
							':username',
							username
						)
					),
					' ',
					$( '<button type="button" class="btn btn-default btn-xs">' )
						.text( strings.acknowledge )
						.on( 'click', () => {
							acknowledged.add( key );
							check();
						} )
				)
				.insertBefore( '.conv-reply-body' );
		};

		// Pasted text is only in the editor once the paste is done.
		$( document ).on( 'summernote.change summernote.init', '#body', check );
		$( document ).on(
			'input paste',
			'.conv-reply-body .note-editable',
			() => setTimeout( check )
		);

		return check;
	}

	$( function () {
		const $panel = $( '.wporg-review' );
		if ( ! $panel.length ) {
			return;
		}

		const strings = $panel.data( 'strings' ) || {};

		$panel.on( 'click', '.wporg-review-copy', function () {
			const $button = $( this );
			// Attributes, not data(), which would parse text that looks like JSON or numbers.
			const reply = $button.attr( 'data-reply' );
			const copying =
				undefined !== reply
					? copy( replyText( reply ), reply )
					: copy( String( $button.attr( 'data-copy' ) ) );

			copying
				.then( () => showCopied( $button, strings.copied ) )
				.catch( () => {} );
		} );

		$panel.on( 'click', '.wporg-review-issue', function () {
			showIssue( $( this ) );
		} );

		const authors = new Set(
			[ $panel.attr( 'data-username' ) ]
				.filter( Boolean )
				.map( ( username ) => String( username ).toLowerCase() )
		);
		const checkReply = watchReply( authors, strings );
		checkReply();

		loadPlugin( $panel, strings, ( plugin ) => {
			if ( plugin.submitter && plugin.submitter.username ) {
				authors.add(
					String( plugin.submitter.username ).toLowerCase()
				);
				checkReply();
			}
		} );

		manageTitle(
			$panel,
			String( $panel.attr( 'data-flags' ) || '' )
				.split( ' ' )
				.filter( Boolean ),
			strings
		);
	} );
} )( jQuery );
