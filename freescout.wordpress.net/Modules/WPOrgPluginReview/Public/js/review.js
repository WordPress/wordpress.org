/**
 * Makes the Plugin Review panel work: copying, jumping to issues, loading the plugin as it is now, flagging and
 * shortening the tab's title, and warning about a reply with another author's Review ID. Marks the plugin directory's
 * subjects, and copies the subjects themselves. Also shows the member and tag chosen in the My Reviews and My Cases
 * folders, and opens their conversations in new tabs.
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
	 * How many times the plugin is asked for again when the agent asked for too many in a minute, as when many tabs
	 * open at once, and how long to wait before each, in milliseconds: longer every time, and a little more at random,
	 * so the tabs don't all ask again at once.
	 *
	 * @type {Object<string, number>}
	 */
	const RETRY = {
		times: 3,
		after: 20000,
		jitter: 10000,
	};

	/**
	 * The parameters the worklists' Open buttons add to the conversations they open: when they were opened, and in
	 * which position, so their plugins' download URLs keep that order.
	 *
	 * @type {Object<string, string>}
	 */
	const OPEN_PARAMS = {
		batch: 'wporg_open_batch',
		order: 'wporg_open_order',
	};

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
	 * Shows the plugin as it is now: its status, ZIP, and page, and its current name, colored by whether it's the one the
	 * review suggested.
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
			const suggested = normalizeName( $names.attr( 'data-suggested' ) );
			const slug = String( $names.attr( 'data-suggested-slug' ) || '' );

			// The suggested name, or slug when there's no name, wins should it be the original too.
			const followed = suggested
				? current === suggested
				: '' !== slug && slug === String( plugin.slug );
			let state = 'to-review';
			if ( followed ) {
				state = 'followed';
			} else if (
				current === normalizeName( $names.attr( 'data-original' ) )
			) {
				state = 'ignored';
			}
			markCheck( $names, state, strings[ 'names_' + state ] );

			addRow( $names.find( '.wporg-review-details' ), strings.current, [
				String( plugin.name ),
				$( '<br>' ),
				$( '<code>' ).text( String( plugin.slug ) ),
				copyButton( String( plugin.slug ), strings.copy_slug ),
			] );
		}
	}

	/**
	 * Colors a check in the panel by whether the author followed the review.
	 *
	 * @param {jQuery} $check Check.
	 * @param {string} state  followed, ignored, or to-review.
	 * @param {string} label  What it means.
	 */
	function markCheck( $check, state, label ) {
		$check.addClass( 'is-' + state ).attr( 'title', label );
	}

	/**
	 * Shows whether the plugin's domains carry the author's record, and whether the submitter's address is at one, and
	 * marks the check when neither proves they own it.
	 *
	 * @param {jQuery}      $owner  The owner check.
	 * @param {Object}      owner   The check.
	 * @param {Object|null} plugin  The plugin, if known.
	 * @param {Object}      strings Labels.
	 */
	function showOwner( $owner, owner, plugin, strings ) {
		// Either is how the review asks them to prove it.
		if (
			! [ 'author', 'plugin', 'email' ].some(
				( key ) => true === owner[ key ]
			)
		) {
			markCheck( $owner, 'ignored', strings.owner_ignored );
		}

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
			addRow( $owner.find( '.wporg-review-details' ), strings.submitter, [
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
	 * @param {number}                 attempt  How many times it was asked for already.
	 */
	function loadPlugin( $panel, strings, onPlugin, attempt = 0 ) {
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
				if ( 429 === xhr.status && attempt < RETRY.times ) {
					const next = attempt + 1;
					const wait =
						next * RETRY.after + Math.random() * RETRY.jitter;

					setTimeout( () => {
						loadPlugin( $panel, strings, onPlugin, next );
					}, wait );
					return;
				}

				addRow( $panel.find( '.wporg-review-plugin' ), strings.status, [
					$( '<span class="wporg-review-error">' ).text(
						429 === xhr.status ? strings.throttled : strings.failed
					),
				] );
			} );
	}

	/**
	 * Puts the marks of the review's flags before the tab's title, its subject's short version in it without the
	 * conversation's number, and keeps it that way as FreeScout changes it, like when it counts new replies.
	 *
	 * @param {jQuery}   $data What the page says about the conversation's subject.
	 * @param {string[]} flags Flags.
	 */
	function manageTitle( $data, flags ) {
		const subject = String( $data.attr( 'data-subject' ) || '' );
		const number = String( $data.attr( 'data-number' ) || '' );
		if ( ! subject || ! number ) {
			return;
		}

		const marks = Object.keys( TITLE_MARKS )
			.filter( ( flag ) => flags.includes( flag ) )
			.map( ( flag ) => TITLE_MARKS[ flag ] )
			.join( '' );

		// FreeScout's title starts with the number and the subject; the short one, with the short subject alone.
		const long = '#' + number + ' ' + subject;
		const short =
			String( $data.attr( 'data-short-subject' ) || '' ) || subject;

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

			if ( rest.startsWith( long ) ) {
				rest = short + rest.slice( long.length );
			}

			const title =
				( match[ 1 ] || '' ) + ( marks ? marks + ' ' : '' ) + rest;
			if ( document.title !== title ) {
				document.title = title;
			}
		};

		update();

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
	 * Shows a marked subject in an element, which keeps the subject itself for hovering and copying.
	 *
	 * @param {jQuery} $element Element.
	 * @param {Object} subjects Subject, and marked subject.
	 */
	function markSubject( $element, subjects ) {
		$element
			.addClass( 'wporg-subject' )
			.attr( {
				title: subjects.subject,
				'data-wporg-subject': subjects.subject,
			} )
			.text( subjects.marked );
	}

	/**
	 * Marks the subjects of the conversation and of its sender's previous conversations, as the page says; lists of
	 * conversations come marked already.
	 *
	 * @param {jQuery} $data What the page says about the conversation's subject.
	 */
	function markSubjects( $data ) {
		let subjects;
		try {
			subjects = JSON.parse( String( $data.attr( 'data-subjects' ) ) );
		} catch {
			return;
		}

		$( '.sidebar-block-list a.help-link' ).each( function () {
			const id = String( $( this ).attr( 'href' ) || '' ).match(
				/\/conversation\/(\d+)/
			);
			const item = id && subjects[ id[ 1 ] ];
			const node = item && this.lastChild;
			if (
				node &&
				window.Node.TEXT_NODE === node.nodeType &&
				node.nodeValue.trim() === item.subject
			) {
				const $subject = $( '<span>' );
				markSubject( $subject, item );
				$( node ).replaceWith( $subject );
			}
		} );

		const current =
			subjects[ String( $data.attr( 'data-conversation-id' ) ) ];
		const $heading = $( '.conv-subjtext > span' ).first();
		if ( ! current || ! $heading.length ) {
			return;
		}

		/*
		 * FreeScout's editor keeps the subject itself, and puts what the agent saves in the heading, which shows as it is
		 * until the page loads again.
		 */
		const update = () => {
			const text = $heading.text();
			if ( text === current.subject ) {
				markSubject( $heading, current );
			} else if ( text !== current.marked ) {
				$heading.removeAttr( 'title data-wporg-subject' );
			}
		};

		update();
		new window.MutationObserver( update ).observe( $heading[ 0 ], {
			childList: true,
			characterData: true,
			subtree: true,
		} );
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
			// FreeScout's minifier misreads a regex literal right after `=>`.
			.filter( ( piece ) => piece.match( /^Review ID:/i ) )
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

	/**
	 * The rows of the conversations a worklist's Open button opens, in the order it opens them.
	 *
	 * @param {jQuery} $button The button.
	 * @return {jQuery[]} Rows.
	 */
	function rowsToOpen( $button ) {
		return String( $button.attr( 'data-conversations' ) || '' )
			.split( ',' )
			.filter( Boolean )
			.map( ( id ) =>
				$button
					.closest( '.wporg-worklist-section' )
					.find( 'tr[data-conversation_id="' + id + '"]' )
			)
			.filter( ( $row ) => $row.length );
	}

	/**
	 * Unmarks the conversations an Open button would open.
	 */
	function unmarkRowsToOpen() {
		$( '.wporg-worklist tr.is-to-open' ).removeClass( 'is-to-open' );
		$( '.wporg-worklist-open-order' ).remove();
	}

	// The My Reviews and My Cases folders show a member's conversations, or a tag's, as soon as they're chosen.
	$( document ).on( 'change', '.wporg-worklist-filter', function () {
		this.form.submit();
	} );

	// Their Open buttons mark which conversations they'd open, and in what order.
	$( document ).on(
		'mouseenter focus',
		'.wporg-worklist-open-button',
		function () {
			unmarkRowsToOpen();
			rowsToOpen( $( this ) ).forEach( ( $row, index ) => {
				$row.addClass( 'is-to-open' )
					.find( '.wporg-worklist-name' )
					.prepend(
						$( '<span class="wporg-worklist-open-order">' ).text(
							index + 1
						)
					);
			} );
		}
	);
	$( document ).on(
		'mouseleave blur',
		'.wporg-worklist-open-button',
		unmarkRowsToOpen
	);

	/*
	 * And open them in new tabs, in that order, telling each when and in which position it was opened; browsers block
	 * all but the first, unless the site may open pop-ups.
	 */
	$( document ).on( 'click', '.wporg-worklist-open-button', function () {
		const $button = $( this );
		const batch = String( Date.now() );
		let blocked = 0;

		rowsToOpen( $button ).forEach( ( $row, index ) => {
			const href = $row.find( 'a[href]' ).first().attr( 'href' );
			if ( ! href ) {
				return;
			}

			const url = new URL( href, window.location.href );
			url.searchParams.set( OPEN_PARAMS.batch, batch );
			url.searchParams.set( OPEN_PARAMS.order, String( index + 1 ) );
			if ( ! window.open( url.href, '_blank' ) ) {
				blocked++;
			}
		} );

		if ( blocked ) {
			const strings =
				$button.closest( '.wporg-worklist-open' ).data( 'strings' ) ||
				{};
			const message = String( strings.blocked || '' ).replace(
				':count',
				blocked
			);

			if ( 'function' === typeof window.showFloatingAlert ) {
				window.showFloatingAlert( 'error', message );
			} else {
				window.alert( message ); // eslint-disable-line no-alert
			}
		}
	} );

	// Their conversations someone is participating in get FreeScout's own bulk actions, which modules like Tags add to.
	$( function () {
		if (
			$( '.wporg-worklist .conv-checkbox' ).length &&
			'function' === typeof window.converstationBulkActionsInit
		) {
			window.converstationBulkActionsInit();
		}
	} );

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
			// For the header's Copy button, which collects the download URLs of the plugins open.
			$( document ).trigger( 'wporgpluginreview:plugin', [ plugin ] );

			if ( plugin.submitter && plugin.submitter.username ) {
				authors.add(
					String( plugin.submitter.username ).toLowerCase()
				);
				checkReply();
			}
		} );
	} );

	// The plugin directory's subjects, for the plugins team, with or without a review to show.
	$( function () {
		const $data = $( '.wporg-subjects' ).first();
		if ( ! $data.length ) {
			return;
		}

		markSubjects( $data );
		manageTitle(
			$data,
			String( $( '.wporg-review' ).attr( 'data-flags' ) || '' )
				.split( ' ' )
				.filter( Boolean )
		);
	} );

	// Copying a marked subject copies the subject itself.
	$( document ).on( 'copy', ( event ) => {
		const view = ( event.target.ownerDocument || document ).defaultView;
		const selection = view && view.getSelection();
		const clipboard =
			event.originalEvent && event.originalEvent.clipboardData;
		if ( ! selection || selection.isCollapsed || ! clipboard ) {
			return;
		}

		const copied = selection.toString();
		let text = copied;
		$( '[data-wporg-subject]' ).each( function () {
			if ( selection.containsNode( this, true ) ) {
				text = text.replace(
					$( this ).text(),
					String( $( this ).attr( 'data-wporg-subject' ) )
				);
			}
		} );

		if ( text !== copied ) {
			clipboard.setData( 'text/plain', text );
			event.preventDefault();
		}
	} );
} )( jQuery );
