/* globals wpTracAutoCompleteUsers, wpTracContributorLabels, wpTracCurrentUser */
let wpTrac,
	coreKeywordList,
	gardenerKeywordList,
	hideFromNewTickets,
	reservedTerms,
	coreFocusesList,
	bugTrackerLocations,
	$body;

( function ( $ ) {
	coreKeywordList = {
		'has-patch': 'Proposed solution attached and ready for review.',
		'needs-patch': 'Ticket needs a new patch.',
		'needs-refresh': 'Patch no longer applies cleanly and needs to be updated.',
		'changes-requested': 'Feedback has been provided and the patch needs to be updated.',
		'reporter-feedback': 'Feedback is needed from the reporter.',
		'dev-feedback': 'Feedback is needed from a core developer.',
		'dev-reviewed':
			'Indicates that a ticket has been reviewed by two committers and can be backported when used in combination with the commit keyword.',
		'2nd-opinion': 'A second opinion is desired for the problem or solution.',
		close: 'The ticket is a candidate for closure.',
		'needs-testing': 'Patch has a particular need for testing.',
		'has-test-info': 'Steps have been provided to reproduce the issue or test a patch.',
		'needs-test-info':
			'A more detailed testing procedure is needed to reproduce the issue, or to validate a patch works as expected.',
		'needs-design':
			'A designer should create a prototype of how the suggested changes should look/behave before writing code.',
		'needs-design-feedback': 'A designer should review and give feedback on the proposed changes.',
		'has-unit-tests': 'Proposed solution has unit test coverage.',
		'needs-unit-tests': 'Ticket has a particular need for unit tests.',
		'has-dev-note': 'Ticket with a published post on the development blog.',
		'needs-dev-note': 'Ticket needs a post on the development blog.',
		'add-to-field-guide': 'Ticket dev-note should be included in the releasese field guide.',
		'has-privacy-review':
			'Input has been given from the core privacy team reviewing the privacy implications of the suggested changes.',
		'needs-privacy-review':
			'Input is needed from the core privacy team with regards to the privacy implications of the suggested changes.',
		'has-copy-review': 'Input has been given from a copywriter reviewing the suggested verbiage changes.',
		'needs-copy-review': 'Input is needed from a copywriter with regards to the suggested verbiage changes.',
		'needs-docs': 'Inline documentation is needed.',
		'needs-user-docs': 'The User Documentation needs to be updated or expanded.',
		'has-screenshots': 'Visual changes are documented with screenshots.',
		'needs-screenshots': 'Screenshots are needed as a visual change log.',
		commit: 'Patch is a suggested commit candidate.',
		early: 'Ticket should be addressed early in the next dev cycle.',
		'i18n-change': 'A string change, used only after string freeze.',
		'good-first-bug':
			'This ticket is great for a new contributor to work on, generally because it is easy or well-contained.',
		'fixed-major': 'The commits of this ticket need to be backported.',
		'gutenberg-merge': 'This ticket is a backport request from Gutenberg.',
	};

	coreFocusesList = {
		ui: 'Ticket is focused on user interface changes.',
		accessibility: 'Accessibility focus.',
		javascript: 'Heavy JavaScript focus.',
		css: 'CSS focus.',
		tests: 'Ticket solely aimed at adding tests, but assigned a more specific component.',
		docs: 'Inline documentation focus.',
		rtl: 'Right-to-left languages.',
		administration: 'Administration related, but assigned a more specific component.',
		template: 'Relating to theme templating, but assigned a more specific component.',
		multisite: 'Relating to multisite, but assigned a more specific component.',
		'rest-api': 'Relating to the REST API, but assigned a more specific component.',
		performance: 'Performance or caching (but not the Cache API component).',
		privacy: 'Privacy focus.',
		sustainability: 'Relating to improving the sustainability of WordPress.',
		'ui-copy': 'Copy focus for the user interface.',
		'coding-standards': 'Coding Standards focus.',
		'php-compatibility':
			'Relating to PHP forward and backward compatibility. A phpNN keyword identifies the PHP version that introduced the incompatibility.',
	};

	// Other Bug Trackers which the WordPress project uses for various things.
	bugTrackerLocations = {
		/*
		 * Fields & options are...
		 * tracker: The URL to redirect the reporter to
		 * tracker_text: The Text to display
		 * Optional:
		 * prevent_changing_to: Set to true to prevent an existing ticket being changed to it.
		 * enable_copy: Enable copy-to for the report, GitHub /choose cannot use this.
		 * allow_bypass: Set to true to allow ignoring the notice.
		 */
		'WordPress.org Site': {
			tracker: 'https://meta.trac.wordpress.org/newticket',
			tracker_text: 'WordPress.org Meta Trac',
			prevent_changing_to: true,
			enable_copy: true,
			allow_bypass: true,
		},
		Editor: {
			tracker: 'https://github.com/WordPress/gutenberg/issues/new/choose',
			tracker_text: 'Gutenberg GitHub Repository',
			bug_text: 'the Gutenberg Editor',
			allow_bypass: true,
		},
		'WordCamp Site & Plugins': {
			tracker: 'https://github.com/WordPress/wordcamp.org/issues/new/choose',
			tracker_text: 'WordCamp.org GitHub Repository',
		},
		'Five For The Future': {
			tracker: 'https://github.com/WordPress/five-for-the-future/issues/new',
			tracker_text: 'Five for the Future GitHub Repository',
			enable_copy: true,
		},
		'Learn (learn.wordpress.org)': {
			tracker: 'https://github.com/WordPress/Learn/issues/new/choose',
			tracker_text: 'WordPress.org Learn GitHub Repository',
		},
		'Pattern Directory': {
			tracker: 'https://github.com/WordPress/pattern-directory/issues/new/choose',
			tracker_text: 'WordPress.org Pattern Directory GitHub Repository',
		},
		Openverse: {
			tracker: 'https://github.com/WordPress/openverse/issues/new/choose',
			tracker_text: 'Openverse GitHub Repository',
		},
		'Global Header/Footer': {
			tracker: 'https://github.com/WordPress/wporg-mu-plugins/issues/new?labels=Header+%26+Footer',
			tracker_text: 'WordPress.org mu-plugins GitHub Repository',
			enable_copy: true,
		},
		'News (wordpress.org/news)': {
			tracker: 'https://github.com/WordPress/wporg-news-2021/issues/new',
			tracker_text: 'WordPress.org News GitHub Repository',
			enable_copy: true,
		},
		Playground: {
			tracker: 'https://github.com/WordPress/wordpress-playground/issues/new',
			tracker_text: 'WordPress Playground GitHub Repository',
			enable_copy: true,
		},
		Showcase: {
			tracker: 'https://github.com/WordPress/wporg-showcase-2022/issues/new',
			tracker_text: 'WordPress.org Showcase GitHub Repository',
			enable_copy: true,
			allow_bypass: true,
		},
		'bbpress.org': {
			tracker: 'https://bbpress.trac.wordpress.org/newticket?component=Site+-+bbPress.org',
			tracker_text: 'bbPress Trac instance',
			enable_copy: true,
		},
		'buddypress.org': {
			tracker: 'https://buddypress.trac.wordpress.org/newticket?component=BuddyPress.org+Sites',
			tracker_text: 'BuddyPress Trac instance',
			enable_copy: true,
		},
	};

	gardenerKeywordList = [
		'commit',
		'early',
		'i18n-change',
		'good-first-bug',
		'fixed-major',
		'dev-reviewed',
		'gutenberg-merge',
	];
	hideFromNewTickets = [
		'needs-refresh',
		'changes-requested',
		'reporter-feedback',
		'dev-feedback',
		'close',
		'has-dev-note',
		'needs-dev-note',
		'add-to-field-guide',
		'has-privacy-review',
		'has-copy-review',
		'commit',
		'early',
		'i18n-change',
		'fixed-major',
		'dev-reviewed',
	];

	// phpDocumentor tags, but also a few common @-terms.
	reservedTerms = [
		'access',
		'author',
		'category',
		'copyright',
		'covers',
		'coversNothing',
		'deprecated',
		'example',
		'expectedDeprecated',
		'final',
		'filesource',
		'global',
		'group',
		'home',
		'ignore',
		'import',
		'inheritdoc',
		'internal',
		'license',
		'link',
		'media',
		'mention',
		'mentions',
		'method',
		'name',
		'notification',
		'notifications',
		'package',
		'param',
		'private',
		'property',
		'property-read',
		'requires',
		'return',
		'returns',
		'see',
		'since',
		'static',
		'staticvar',
		'subpackage',
		'term',
		'terms',
		'throws',
		'ticket',
		'toc',
		'todo',
		'tutorial',
		'type',
		'user',
		'username',
		'uses',
		'var',
		'version',
		'wordpress',
		'wp',
	];

	$body = $( document.body );

	/**
	 * Escapes a value for insertion into an HTML string.
	 *
	 * Safe for element content only: quotes are left alone, so attribute values
	 * must be set through jQuery rather than built by concatenation.
	 *
	 * @param {string} value Value to escape.
	 * @return {string} The escaped value.
	 */
	function escapeHtml( value ) {
		return $( '<span />' ).text( value ).html();
	}

	/**
	 * Rewrites regex matches found in an element's text nodes.
	 *
	 * Rebuilt as Text/Element nodes rather than an HTML string, so nothing
	 * decoded out of the text (e.g. `&lt;` read back as `<`) is ever
	 * reparsed as markup. Uses Node.append() instead of jQuery's
	 * .append()/.html(), which parse string arguments as HTML. Attribute
	 * values are never visited, and matches already inside a link are
	 * skipped.
	 *
	 * @param {Element}  root     Element whose text nodes are walked.
	 * @param {RegExp}   regex    Global regex tested against each text node's value.
	 * @param {Function} replacer Receives the same arguments as a String.prototype.replace()
	 *                            callback (match, ...groups, offset, string) and returns a
	 *                            string, a DOM Node, or an array of either to insert as-is.
	 */
	function linkTextNodes( root, regex, replacer ) {
		const walker = document.createTreeWalker( root, window.NodeFilter.SHOW_TEXT ),
			textNodes = [];
		let node;

		while ( ( node = walker.nextNode() ) ) {
			if ( ! $( node.parentNode ).closest( 'a' ).length ) {
				textNodes.push( node );
			}
		}

		textNodes.forEach( function ( textNode ) {
			const value = textNode.nodeValue,
				matches = [ ...value.matchAll( regex ) ];

			if ( ! matches.length ) {
				return;
			}

			const fragment = document.createDocumentFragment();
			let lastIndex = 0;

			matches.forEach( function ( match ) {
				fragment.append( value.slice( lastIndex, match.index ) );

				const replacement = replacer.apply( null, [ ...match, match.index, value ] );
				fragment.append( ...( Array.isArray( replacement ) ? replacement : [ replacement ] ) );

				lastIndex = match.index + match[ 0 ].length;
			} );
			fragment.append( value.slice( lastIndex ) );

			textNode.replaceWith( fragment );
		} );
	}

	// Project slug: from the body class site_header.html sets (make-<slug>), else the host.
	const projectSlug =
		( document.body.className.match( /\bmake-([a-z0-9-]+)/ ) || [] )[ 1 ] ||
		window.location.hostname.split( '.' )[ 0 ];

	// Project display name from the page title, e.g. "WordPress" or "bbPress". HTML-escaped.
	const projectName = escapeHtml( document.title.split( '–' ).pop().replace( 'Trac', '' ).trim() || projectSlug );

	const supportLink =
		{
			bbpress: 'https://bbpress.org/forums/',
			buddypress: 'https://buddypress.org/support/topics/',
		}[ projectSlug ] || 'https://wordpress.org/support/';

	wpTrac = {
		gardener: 'undefined' !== typeof wpBugGardener,
		currentUser: 'undefined' !== typeof wpTracCurrentUser ? wpTracCurrentUser : '',

		init() {
			// Gardener status as a body class, for rules that cannot see the flag.
			$body.toggleClass( 'wp-bug-gardener', wpTrac.gardener );

			// Markup rewrites first, so everything below sees the rewritten DOM (e.g. data-nicename on gravatar links).
			wpTrac.updateAuthLinks();
			wpTrac.addContributorAvatars();
			wpTrac.requestReporterFeedback();
			wpTrac.addNewTicketGuidance();
			wpTrac.requireContributionLicense();
			wpTrac.markUgcLinks();

			wpTrac.hacks();
			if ( ! wpTrac.gardener ) {
				wpTrac.nonGardeners();
			}

			if ( 'undefined' !== typeof wpTracContributorLabels ) {
				wpTrac.showContributorLabels( wpTracContributorLabels );
			}

			wpTrac.autocomplete.init();
			wpTrac.linkMentions();
			wpTrac.linkGutenbergIssues();
			wpTrac.githubPRs.init();
			wpTrac.suggestNotGeneral.init();

			if ( ! $body.hasClass( 'plugins' ) ) {
				wpTrac.workflow.init();
				if ( $body.hasClass( 'core' ) ) {
					wpTrac.reports();
					wpTrac.focuses.init();
				}
			}
		},

		isNewTicket() {
			return '/newticket' === window.location.pathname;
		},

		showContributorLabels( labels ) {
			$( 'h3.change .username' ).each( function () {
				let html;
				const $el = $( this ),
					username = $el.data( 'username' );

				if ( username in labels ) {
					if ( typeof labels[ username ] === 'object' ) {
						html = $( '<span />', {
							class: 'contributor-label',
							title: labels[ username ].title,
						} ).text( labels[ username ].text );
					} else {
						html = $( '<span />', {
							class: 'contributor-label',
						} ).text( labels[ username ] );
					}
					$el.closest( '.username-line' ).append( '&ensp;' + html.prop( 'outerHTML' ) );
				}
			} );
		},

		linkMentions( selector ) {
			// See https://github.com/regexps/mentions-regex/blob/master/index.js#L21
			const mentionsRegEx =
				/(^|[^a-zA-Z0-9_＠!@#$%&*])(?:(?:@|＠)(?!\/))([a-zA-Z0-9_\-.]{1,20})(?:\b(?!@|＠)|$)/g;

			$( selector || 'div.change .comment, #ticket .description' ).each( function () {
				linkTextNodes( this, mentionsRegEx, function ( match, pre, username ) {
					if ( -1 !== $.inArray( username, reservedTerms ) ) {
						return match;
					}

					const meClass = username === wpTrac.currentUser ? ' me' : '';
					return [
						pre,
						$( '<a />', {
							class: `mention${ meClass }`,
							href: `https://profiles.wordpress.org/${ encodeURIComponent( username ) }/`,
							text: `@${ username }`,
						} )[ 0 ],
					];
				} );
			} );
		},

		linkGutenbergIssues( selector ) {
			const gutenbergIssueRegEx = /\bGB[-]?(\d+)\b/gi;

			$( selector || 'div.change .comment, #ticket .description' ).each( function () {
				linkTextNodes( this, gutenbergIssueRegEx, function ( match, issueNumber ) {
					return $( '<a />', {
						class: 'gutenberg-issue github ext-link',
						href: `https://github.com/WordPress/Gutenberg/issues/${ issueNumber }`,
					} )
						.append( $( '<span class="icon">&#8203;</span>' ) )
						.append( document.createTextNode( match ) )[ 0 ];
				} );
			} );
		},

		// These ticket hacks need to be re-run after ticket previews.
		postPreviewHacks() {
			// Automatically preview images.
			$( 'li.trac-field-attachment' ).each( function () {
				const li = $( this );
				if ( li.parent().parent().find( '.trac-image-preview' ).length ) {
					return;
				}
				const el = li.find( '.trac-rawlink' ),
					href = el.attr( 'href' ),
					appendTo = li.parent().parent(); // div.change
				if ( href.match( /\.(jpg|jpeg|png|gif|svg|webp)$/i ) ) {
					const image = new window.Image();
					image.src = href;
					let alt = appendTo.find( '.comment' );
					if ( alt.length > 0 ) {
						alt = alt.text();
					} else {
						// Use attachment filename if it has no description.
						alt = el.find( '.trac-attachment-name' ).text();
					}
					image.onload = function () {
						$( '<img />' )
							.attr( {
								src: href,
								alt: alt.trim(),
								width: image.width,
								height: image.height,
								class: 'trac-image-preview',
							} )
							.appendTo( appendTo )
							.wrap( $( '<a />' ).attr( 'href', href.replace( '/raw-attachment/', '/attachment/' ) ) );
					};
				} else if ( href.match( /\.(mp4|mov|webm)$/i ) ) {
					$( '<video />' )
						.attr( {
							src: href,
							class: 'trac-image-preview',
							controls: true,
							preload: 'metadata',
						} )
						.appendTo( appendTo );
				}
			} );

			wpTrac.linkGutenbergIssues( '.ticketdraft .comment' );
			wpTrac.linkMentions( '.ticketdraft .comment' );
		},

		hacks() {
			const content = $( '#content' );

			// Add deprecated notice for core's test repository.
			if ( $body.hasClass( 'core' ) && content.hasClass( 'browser' ) ) {
				$( '#repoindex tbody .odd .name a[href="/browser/tests"]' )
					.parent()
					.append(
						'<p style="display:inline">Deprecated. <a href="/browser/trunk/tests">Please see default repository</a>.'
					);

				if ( window.location.pathname.substring( 0, 14 ) === '/browser/tests' ) {
					content.before(
						$( '<div />', {
							class: 'system-message warning',
							html: 'You are currently viewing the <strong>deprecated</strong> test repository. You may want to <a href="/browser/trunk/tests">view the tests in the default repository</a>.',
						} )
					);
				}
			}

			if ( $body.hasClass( 'themes' ) ) {
				$( '#h_reporter' ).text( 'Developer:' );
				$( '#h_owner' ).text( 'Reviewer:' );

				// Prevent uploading of ZIP files to Trac.
				// See https://meta.trac.wordpress.org/ticket/3904
				$( '#attachment input[type="file"]' ).on( 'change', function () {
					const ext = this.value.split( '.' ).pop();
					$( '#wp-block-zip-upload' ).remove(); // Hide the notice if it's already in the DOM
					if ( 'zip' === ext ) {
						this.value = '';

						$( this )
							.parents( 'div.field' )
							.after(
								'<div class="wp-notice" id="wp-block-zip-upload"><p><strong>Please do not upload ZIPs to Trac.</strong><br>All Theme ZIPs (including updates) should be submitted via <a href="https://wordpress.org/themes/upload/">https://wordpress.org/themes/upload/</a>.</p></div>'
							);
					}
				} );
			}

			// Change 'Comments' and 'Stars' columns to dashicons glyphs to save space.
			$( 'th a[href*="sort=Comments"]' ).html( '<div class="dashicons dashicons-admin-comments"></div>' );
			$( 'th a[href*="sort=Stars"]' ).html( '<div class="dashicons dashicons-star-empty"></div>' );

			// Link username in header.
			wpTrac.linkHeaderUsername();

			// Ticket-only tweaks.
			if ( content.hasClass( 'ticket' ) ) {
				wpTrac.redirectTicketsToProperTracker.init();

				// A collection of ticket hacks that must be run again after previews.
				wpTrac.postPreviewHacks();
				content.on( 'wpTracPostPreview', wpTrac.postPreviewHacks );

				// Allow 'Modify Ticket' to be shown even after a Trac preview tries to close it,
				// but only if it was already open.
				wpTrac.keepModifyTicketOpen();

				// Open WikiFormatting links in a new window.
				$( '#content.ticket' ).on( 'click', 'a[href$="wiki/WikiFormatting"]', function () {
					window.open( $( this ).attr( 'href' ) );
					return false;
				} );

				// Submit comment form on Cmd/Ctrl + Enter.
				$( '#comment' ).on( 'keydown', function ( event ) {
					if ( event.ctrlKey && ( event.keyCode === 10 || event.keyCode === 13 ) ) {
						$( 'input[name="submit"]' ).trigger( 'click' );
					}
				} );

				// Move all of the ticket actions text into the label.
				// Trac markup is like this: `<label>close</label> as fixed`
				window.jQuery( '#action div label' ).each( function () {
					if ( this.nextSibling && window.Node.TEXT_NODE === this.nextSibling.nodeType ) {
						this.textContent += this.nextSibling.nodeValue;
						this.nextSibling.nodeValue = '';
					}
				} );

				// Point users to open new tickets when they comment on old tickets.
				if ( $( '#ticket' ).find( '.milestone' ).hasClass( 'closed' ) ) {
					const component = $( '#field-component' ).val(),
						ticketId = $( '.trac-id' ).text(),
						newticket = `/newticket?component=${ encodeURIComponent(
							component
						) }&description=${ encodeURIComponent( `This is a follow-up to ${ ticketId }.` ) }`;
					$( '#trac-add-comment fieldset' ).prepend(
						`<p class="ticket-reopen-notice"><span class="dashicons dashicons-info"></span>
						<strong>This ticket was closed on a completed milestone.</strong><br />
						If you have a bug or enhancement to report, please <a href="${ newticket }">open a new ticket</a>.
						Be sure to mention this ticket, ${ escapeHtml( ticketId ) }.</p>`
					);
					if ( ! wpTrac.gardener ) {
						$( '#action_reopen' ).parent().remove();
					}
				}

				// Rudimentary save alerts for new tickets (summary/description) and comments.
				window.onbeforeunload = function () {
					if ( wpTrac.isNewTicket() ) {
						if ( ! $( '#field-description' ).val() && ! $( '#field-summary' ).val() ) {
							return;
						}
					} else if ( ! $( '#comment' ).val() ) {
						return;
					}
					return 'The changes you made will be lost if you navigate away from this page.';
				};
				$( '.buttons' ).on( 'click', 'input', function () {
					window.onbeforeunload = null;
				} );
			}

			// Add custom buttons to the formatting toolbar.
			wpTrac.wikiToolbar();

			// Force 'Attachments' and 'Modify Ticket' to be shown.
			$( '#attachments' ).removeClass( 'collapsed' );
			$( '#modify' ).parent().removeClass( 'collapsed' );

			// Move the Add-Comment before Ticket Modify dialogue.
			$( '#trac-add-comment' ).insertBefore( $( '#modify' ).parent() );

			// Push live comment previews above 'Modify Ticket'.
			$( '#ticketchange' ).insertAfter( '#trac-add-comment' );

			// Toggle the security notice on component change, if rendered.
			if ( $( '#wp-security-notice' ).length ) {
				$( '#field-component' ).on( 'change', function () {
					$( '#wp-security-notice' ).toggle( 'Security' === $( this ).val() );
				} );
			}

			// Prevent links inside a ticket or comment preview from opening in the same window.
			$( '.ticketdraft' ).on( 'click', 'a', function () {
				window.open( $( this ).attr( 'href' ) );
				return false;
			} );

			// Allow action text inputs and select fields to be clicked directly.
			$( '#action' )
				.find( 'input[type=text], select' )
				.enable()
				.on( 'focus', function () {
					$( this ).siblings( 'input[type=radio]' ).trigger( 'click' );
				} )
				.end()
				.find( 'input[name=action]' )
				.off( 'click' )
				.end()
				.find( 'div' )
				.has( 'select' )
				.find( 'input[type=radio]' )
				.on( 'change', function () {
					$( this ).siblings( 'select' ).enable();
				} );

			// Hide action text inputs and select fields from keyboard, unless the corresponding action is focused.
			$( '#action' )
				.find( 'input[type=text], select' )
				.each( function () {
					$( this ).attr( 'tabindex', '-1' );
				} )
				.end()
				.find( 'input' )
				.on( 'blur', function () {
					$( this ).parent().find( 'input[type=text], select' ).attr( 'tabindex', '-1' );
				} )
				.on( 'focus', function () {
					$( this ).parent().find( 'input[type=text], select' ).removeAttr( 'tabindex' );
				} );

			// Clear the milestone on wontfix, duplicate, worksforme, invalid.
			const milestone = $( '#field-milestone' );
			if ( ! milestone.prop( 'disabled' ) ) {
				$( '#propertyform' ).on( 'submit', function () {
					const action = $( 'input[name=action]:checked' ).val();
					if (
						'duplicate' === action ||
						( 'resolve' === action && 'fixed' !== $( '#action_resolve_resolve_resolution' ).val() )
					) {
						milestone.val( '' );
					}
				} );
			}

			// Prevent marking a ticket as a duplicate of itself.
			$( '#propertyform' ).on( 'submit', function () {
				const action = $( 'input[name="action"]:checked' ).val(),
					currentTicket = parseInt( $( '.trac-id' ).text().replace( '#', '' ) ),
					duplicateTicket = parseInt( $( '#action_dupe' ).val() );

				if ( 'duplicate' === action && ( ! duplicateTicket || currentTicket === duplicateTicket ) ) {
					$( '#action_dupe' ).val( '' );
					return false;
				}
			} );

			// capital_P_dangit()
			$( '#propertyform' ).on( 'submit', function () {
				const $summary = $( '#field-summary' ),
					$description = $( '#field-description' ),
					$comment = $( '#comment' ),
					isNewTicket = wpTrac.isNewTicket();

				// Simple replacement for ticket summary.
				if ( isNewTicket ) {
					$summary.val( $summary.val().replaceAll( 'Wordpress', 'WordPress' ) );
				}

				// Use the more judicious replacement for ticket description and comments.
				$.each(
					[ ' Wordpress', '&#8216;Wordpress', '&#8220;Wordpress', '>Wordpress', '(Wordpress' ],
					function ( index, value ) {
						const replacement = value.replaceAll( 'Wordpress', 'WordPress' );

						if ( $description.length && isNewTicket ) {
							$description.val( $description.val().replaceAll( value, replacement ) );
						}
						if ( $comment.length ) {
							$comment.val( $comment.val().replaceAll( value, replacement ) );
						}
					}
				);
			} );

			// Add a 'Show only commits/attachments' view option to tickets.
			$( 'label[for="trac-comments-only-toggle"]' ).text( 'Show only comment text' );
			$( 'form#prefs' )
				.has( '#trac-comments-order' )
				.append(
					'<div><input type="checkbox" id="wp-trac-commits-only" /> <label for="wp-trac-commits-only">Show only commits/attachments</label></div>'
				);
			$( '#wp-trac-commits-only' ).on( 'change', function () {
				if ( ! this.checked ) {
					$( 'div.change' ).show();
					return;
				}
				$( 'div.change' )
					.hide()
					// Best we can do to target a.
					.has( '.comment > p > a.changeset' )
					.has( '.comment div.message p a.ticket' )
					.show()
					.end()
					.end()
					.has( 'li.trac-field-attachment' )
					.show();
			} );

			// List commits between #ticket and #attachments.
			if ( $( '#content.ticket' ).length && ! $( '#ticket.ticketdraft' ).length ) {
				const $commitChanges = $( 'div.change' )
						.has( '.comment > p > a.changeset' )
						.has( '.comment div.message p a.ticket' ),
					$commits = $( '<ul/>' );
				let commitCount = 0;

				$commitChanges.each( function ( i, el ) {
					const $el = $( el ),
						$comment = $el.find( '.comment' ),
						$commit = $( '<li>' );

					const commitNumber = $comment
						.find( '> p ' )
						.html()
						.trim()
						.replace( /^In /, '' )
						.replace( /:<br>$/, '' );
					$commit.append( '[' + commitNumber + '] ' );

					const firstLine = $comment.find( '.message > p' ).html().trim().replace( /<br>$/, '' );
					$commit.append( firstLine + '&hellip;' );

					const author = $el.find( '.username' ).data( 'username' );
					$commit.append(
						' by&nbsp;<a href="https://profiles.wordpress.org/' + author + '/">@' + author + '</a>'
					);

					const date = $el.find( '.time-ago' ).html();
					$commit.append( ' ' + date );

					$commits.append( $commit );
					commitCount += 1;
				} );

				$( '#ticket' ).after(
					$( '<div/>', {
						id: 'commits',
						class: 'collapsed',
					} )
						.append(
							$( '<h3/>', {
								class: 'foldable',
							} ).html(
								`<a href="#no0" id="no0">Commits <span class="trac-count">(${ commitCount })</span></a>`
							)
						)
						.append(
							$( '<div/>', {
								class: 'commits',
							} ).append( $commits )
						)
				);
			}

			// See $.fn.enableFolding().
			$( '#no0' ).on( 'click', function () {
				const $div = $( this.parentNode.parentNode ).toggleClass( 'collapsed' );
				return ! $div.hasClass( 'collapsed' );
			} );

			// 'User Interface' preferences tab => 'Help Links' (and removes icons-only setting).
			const uitab = $( '#tab_userinterface' );
			if ( uitab.length ) {
				if ( uitab.hasClass( 'active' ) ) {
					uitab.text( 'Help Links' );
					$( 'input[name="ui.use_symbols"]' ).closest( 'div.field' ).remove();
				} else {
					uitab.find( 'a' ).text( 'Help Links' );
				}
			}

			if ( content.hasClass( 'search' ) ) {
				// Remove 'Wiki' and 'Milestone' from search.
				$( '#fullsearch #milestone' ).next().remove().end().remove();
				$( '#fullsearch #wiki' ).next().remove().end().remove();

				// Offer to create a new ticket.
				content
					.find( 'h1' )
					.append(
						`<span class="create-new-ticket button button-large button-primary"><a href="https://login.wordpress.org/?redirect_to=https://${ window.location.host }/newticket" rel="nofollow">Create a new ticket</a></span>`
					);
			}

			// Batch Modify should require a comment.
			$( '#batchmod_value_comment' ).prop( 'required', true );

			// Show the number of query results even on a single page; Trac only renders a heading when they paginate.
			if ( content.hasClass( 'query' ) && ! content.find( 'h2.report-result' ).length ) {
				const numResults = content.find( 'table.listing tbody tr' ).length;
				if ( numResults ) {
					$( 'form#query' ).after(
						'<h2 class="report-result">Results <span class="numresults">(' + numResults + ')</span></h2>'
					);
				}
			}

			// Hide the "arguments are missing" warning on report views for users who cannot edit the report to fix it.
			if (
				/^\/report\/\d/.test( window.location.pathname ) &&
				! content.find( '.buttons input[value="Edit report"]' ).length
			) {
				content
					.find( '#warning.system-message' )
					.filter( function () {
						return /arguments are missing/i.test( $( this ).text() );
					} )
					.hide();
			}

			// Demote the nav "Preferences" link to the footer.
			$( '#altlinks' ).prepend(
				'<a class="preferences-link" href="' + ( window.tracBaseUrl || '' ) + '/prefs">Trac UI Preferences</a> '
			);

			// Prevent emoji in ticket text from being replaced with <img> tags.
			$( '#field-description, #comment, textarea[name="edited_comment"]' ).addClass( 'wp-exclude-emoji' );

			// Remove wiki cruft from the home page.
			if ( '/' === window.location.pathname ) {
				$( '#pagepath, #ctxtnav, .trac-modifiedby, #altlinks h3, #altlinks ul' ).hide();
			}

			// bbPress/BuddyPress: move #metanav into a #subnav banner, like their sites' themes.
			if ( 'bbpress' === projectSlug || 'buddypress' === projectSlug ) {
				const $items = $( '#metanav ul' ).children( 'li' );
				if ( $items.length ) {
					const $subnav = $(
						'<div id="subnav"><div id="subnav-inner"><ul id="nav-user" class="menu"></ul></div></div>'
					);
					$subnav.find( 'ul' ).append( $items );
					$( '#banner' ).before( $subnav );
				}
			}
		},

		/**
		 * Points the login and logout links at WordPress.org's login.
		 */
		updateAuthLinks() {
			const currentUrl = encodeURIComponent( window.location.href );

			$( '#metanav a[href="/login"]' )
				.attr( 'href', 'https://login.wordpress.org/?redirect_to=' + currentUrl )
				.addClass( 'login' );
			$( '#metanav form#logout' ).replaceWith(
				`<a href="https://login.wordpress.org/logout?redirect_to=${ currentUrl }">Logout</a>`
			);
		},

		/**
		 * Links the ticket reporter and owner to their WordPress.org profiles, with avatars.
		 *
		 * Comment-author avatars are added server-side in ticket_change.html.
		 */
		addContributorAvatars() {
			/**
			 * Approximates WordPress.org's user-nicename sanitization, for profile and
			 * Gravatar URLs.
			 *
			 * @param {string} name Username as displayed by Trac.
			 * @return {string} The corresponding WordPress.org user nicename.
			 */
			function sanitizeNicename( name ) {
				return String( name )
					.toLowerCase()
					.replace( /[^%a-z0-9\u0080-\u00ff _-]/g, '' )
					.trim()
					.replace( /[\s-]+/g, '-' )
					.replace( /_+/g, '_' );
			}

			// The cells hold the username as plain text or inside Trac's a.trac-author link; text() covers both.
			$( 'td[headers="h_reporter"], td[headers="h_owner"]' ).each( function () {
				const $td = $( this );
				const user = $td.text().trim();
				if ( ! user ) {
					return; // e.g. an unassigned owner
				}
				const nice = sanitizeNicename( user );
				$td.prepend(
					`<a href="https://profiles.wordpress.org/${ nice }/" data-nicename="${ nice }"><img class="avatar" src="https://wordpress.org/grav-redirect.php?user=${ nice }&s=48" srcset="https://wordpress.org/grav-redirect.php?user=${ nice }&s=96 2x" height="48" width="48" alt="${ nice }'s profile" /></a> `
				);
			} );
		},

		/**
		 * Asks the ticket reporter for feedback when the reporter-feedback keyword is set.
		 *
		 * Deliberately shows for closed tickets with a resolution other than 'fixed'.
		 */
		requestReporterFeedback() {
			const reporter = $( 'td[headers="h_reporter"]' ).text().trim();
			if (
				! wpTrac.currentUser ||
				reporter !== wpTrac.currentUser ||
				! /\breporter-feedback\b/.test( $( 'td[headers="h_keywords"]' ).text() ) ||
				'fixed' === $( 'td[headers="h_resolution"]' ).text().trim()
			) {
				return;
			}

			$( '#ticketchange' )
				.first()
				.after(
					`<div class="wp-notice" id="wp-reporter-feedback-notice">
					<p><strong>Howdy!</strong></p>
					<p>A contributor marked this ticket with the reporter-feedback keyword.
					<strong>That means we need feedback from you.</strong></p>
					<p>Please answer their questions and address their concerns, then remove the keyword, below.</p>
					<p>If this is a support question, you’re better off in the
					<a href="${ supportLink }" class="ext-link"><span class="icon"> </span>support forums</a>.</p>
					</div>`
				);
		},

		/**
		 * Adds support and security notices and bug-reporting instructions to the
		 * new-ticket form.
		 */
		addNewTicketGuidance() {
			if ( '/newticket' !== window.location.pathname ) {
				return;
			}

			const $form = $( '#content.ticket form' ).first();

			// "Are you in the right place?" notice.
			const securityHtml =
				'plugins' === projectSlug
					? `<strong>Do not report potential security vulnerabilities here.</strong><br />Please email
					<a class="mail-link" href="mailto:plugins@wordpress.org">plugins@wordpress.org</a>.`
					: `<strong>Do not report potential security vulnerabilities here.</strong><br />See the
					<a href="https://make.wordpress.org/core/handbook/reporting-security-vulnerabilities/">Security FAQ</a>
					and visit the <a href="https://hackerone.com/wordpress">WordPress HackerOne program</a>.`;

			$form.before(
				`<div class="wp-notice newticket-not-here">
				<p><strong>ARE YOU IN THE RIGHT PLACE?</strong></p>
				<p class="support"><span class="dashicons dashicons-editor-help"></span>
				<strong>This is not for support.</strong><br />Please try the <a href="${ supportLink }">support forums</a>.</p>
				<p class="security"><span class="dashicons dashicons-lock"></span> ${ securityHtml }</p>
				</div>`
			);

			// Bug-report instructions (core/bbPress/BuddyPress only).
			if ( [ 'core', 'bbpress', 'buddypress' ].indexOf( projectSlug ) !== -1 ) {
				const gutenberg =
					'core' === projectSlug
						? '<li>Please create Gutenberg issues on the project’s GitHub <a href="https://github.com/WordPress/gutenberg/issues">issue tracker</a>.</li>'
						: '';
				$form.before(
					`<div class="newticket-instructions">
					<p><strong>This form is for suggesting enhancements and reporting bugs in ${ projectName }.</strong>
					Here are some questions and tips to help you write a great bug report:</p>
					<ul>
					<li>Are you using either the latest version of ${ projectName },
					or the latest development version? If not, please update first.</li>
					<li>What steps should be taken to consistently reproduce the problem?</li>
					<li>Does the problem occur even when you deactivate all plugins and use the default theme?</li>
					<li>In case it’s relevant to the ticket, what is the expected output or result? What did you see instead?</li>
					<li>Please provide any additional information that you think we’d find useful.
					(OS and browser for UI defects, server environment for crashes, etc.)</li>
					${ gutenberg }
					</ul>
					<p>You can <a href="/search">search for existing tickets here</a>. For more help, please see the
					<a href="https://make.wordpress.org/core/handbook/reporting-bugs/">contributor handbook</a>.</p>
					</div>`
				);
			}

			// Security-component alert on preview.
			if ( $( '#ticketbox.ticketdraft' ).length && 'Security' === $( '#field-component' ).val() ) {
				$( '#propertyform div.buttons' ).before(
					`<div class="wp-notice" id="wp-security-notice">
					<p><strong>Caution!</strong> This ticket was assigned to the Security component.</p>
					<p><strong>If this is a potential security vulnerability, DO NOT REPORT IT HERE.</strong></p>
					<p>Instead, read the <a class="ext-link" href="https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/">
					<span class="icon"> </span>Security FAQ</a> and visit the
					<a href="https://hackerone.com/wordpress">WordPress HackerOne program</a>.</p>
					</div>`
				);
			}
		},

		/**
		 * Requires attachments to be contributed under the GPL.
		 */
		requireContributionLicense() {
			$( '#attachment div.buttons' ).before(
				`<div class="wp-caution" id="wp-contributions-caution"><p>By contributing code to ${ projectName },
				you grant its use under the GNU General Public License v2 (or later).</p></div>`
			);
			$( '#attachment input[type="submit"][value="Add attachment"]' ).val( 'Agree and Upload' );
		},

		/**
		 * Marks links in user-generated content as such, with nofollow for
		 * destinations outside the WordPress.org network.
		 */
		markUgcLinks() {
			/**
			 * Whether a URL points at a WordPress.org-family domain.
			 *
			 * @param {string} url URL to test.
			 * @return {boolean} True for wordpress.org/.net, bbpress.org, buddypress.org, and wordcamp.org URLs.
			 */
			function isWporgUrl( url ) {
				return /^([^>]*href=")?https?:\/\/([a-z0-9.-]+\.)?(wordpress\.(org|net)|buddypress\.org|bbpress\.org|wordcamp\.org)\//i.test(
					url
				);
			}

			$( '.description, .comment, .attachments, td.message.searchable' )
				.find( 'a.ext-link' )
				.each( function () {
					const $a = $( this );
					if ( isWporgUrl( $a.attr( 'href' ) || '' ) ) {
						$a.removeClass( 'ext-link' ).attr( 'rel', 'ugc' );
					} else {
						$a.attr( 'rel', 'ugc nofollow' );
					}
				} );
		},

		// Link the current user's name in the header to their profile.
		linkHeaderUsername() {
			const el = $( '#metanav' ).find( '.first' );
			let username = el.text();

			if ( 0 === username.indexOf( 'logged in as' ) ) {
				username = username.replace( 'logged in as ', '' );
				el.html(
					$( '<a />', {
						href: 'https://profiles.wordpress.org/' + username + '/',
					} ).text( username )
				).prepend( 'logged in as ' );
			}
		},

		// Allow 'Modify Ticket' to be shown even after a Trac preview tries to close it,
		// but only if it was already open.
		keepModifyTicketOpen() {
			let hadClass;
			const form = $( '#propertyform' );

			if ( ! form.length ) {
				return;
			}

			const content = $( '#content' ),
				modify = $( '#modify' ).parent(),
				action = form.attr( 'action' );
			$( document ).ajaxSend( function ( event, XMLHttpRequest, ajaxOptions ) {
				if ( 0 !== action.indexOf( ajaxOptions.url ) ) {
					return;
				}
				hadClass = modify.hasClass( 'collapsed' );
				// Prevent re-rendering of image previews and other changes from causing "jumps" while writing a comment.
				const changelogHeight = $( '#changelog' ).height();
				$( document.head ).append(
					`<style id="changelog-height"> #changelog { height: ${ changelogHeight }px !important; } </style>`
				);
			} );
			$( document ).ajaxComplete( function ( event, XMLHttpRequest, ajaxOptions ) {
				if ( 0 !== action.indexOf( ajaxOptions.url ) ) {
					return;
				}
				if ( ! hadClass ) {
					modify.removeClass( 'collapsed' );
				}
				content.triggerHandler( 'wpTracPostPreview' );
				window.setTimeout( function () {
					$( '#changelog-height' ).remove();
				}, 200 );
			} );
		},

		// Add custom buttons to the formatting toolbar.
		// http://trac.edgewall.org/browser/tags/trac-1.0.9/trac/htdocs/js/wikitoolbar.js
		wikiToolbar() {
			// after = ID of an existing button.
			function addButton( $wikitoolbar, $textarea, id, title, after, fn ) {
				const $button = $( '<a />', {
					href: '#',
					id,
					title,
					tabIndex: 400,
				} );
				$button.on( 'click', function () {
					if ( false === $textarea.prop( 'disabled' ) && false === $textarea.prop( 'readonly' ) ) {
						try {
							fn();
						} catch {}
					}
					return false;
				} );
				$wikitoolbar.find( after ).after( $button );
			}

			function encloseSelection( textarea, prefix, suffix ) {
				let start, end, sel, scrollPos;
				// A DOM element, not a jQuery object: see the caller.
				textarea.focus();
				if ( 'undefined' !== typeof document.selection ) {
					sel = document.selection.createRange().text;
				} else if ( 'undefined' !== typeof textarea.setSelectionRange ) {
					start = textarea.selectionStart;
					end = textarea.selectionEnd;
					scrollPos = textarea.scrollTop;
					sel = textarea.value.substring( start, end );
				}
				if ( sel.match( / $/ ) ) {
					// exclude ending space char, if any
					sel = sel.substring( 0, sel.length - 1 );
					suffix = suffix + ' ';
				}
				const subst = prefix + sel + suffix;
				if ( 'undefined' !== typeof document.selection ) {
					document.selection.createRange().text = subst;
					textarea.caretPos -= suffix.length;
				} else if ( 'undefined' !== typeof textarea.setSelectionRange ) {
					textarea.value = textarea.value.substring( 0, start ) + subst + textarea.value.substring( end );
					if ( sel ) {
						textarea.setSelectionRange( start + subst.length, start + subst.length );
					} else {
						textarea.setSelectionRange( start + prefix.length, start + prefix.length );
					}
					textarea.scrollTop = scrollPos;
				}
			}

			$( 'textarea.wikitext' ).each( function () {
				const $textarea = $( this ),
					textarea = $textarea[ 0 ];
				if ( 'undefined' === typeof document.selection && 'undefined' === typeof textarea.setSelectionRange ) {
					return;
				}

				const $wikitoolbar = $textarea.parents( 'div.trac-resizable' ).siblings( 'div.wikitoolbar' );

				addButton(
					$wikitoolbar,
					$textarea,
					'code-php',
					'PHP Code block: {{{#!php example }}}',
					// Trac 1.6 gives toolbar buttons a class, not an id.
					'.trac-wikitoolbar-code',
					function () {
						encloseSelection( textarea, '{{{#!php\n<?php\n', '\n}}}\n' ); // jshint ignore:line
					}
				);
			} );
		},

		// If we're not dealing with a trusted bug gardener:
		nonGardeners() {
			const elements = {};

			// Hide disabled fields (new ticket & ticket modify)
			$( '.trac-properties select[disabled]' ).parents( 'td' ).hide().prev().hide();

			elements.type = $( '#field-type' );
			elements.version = $( '#field-version' );
			const version = parseFloat( elements.version.val() );

			// Remove task, or make a task ticket read only. This supports the ticket type being 'task' or 'task (blessed)'
			if ( elements.type.length ) {
				const typeValue = elements.type.val();
				if ( -1 !== typeValue.indexOf( 'task' ) ) {
					// Built as nodes: the value is a field value, not markup.
					elements.type
						.after(
							$( '<input type="hidden" name="field_type" />' ).val( typeValue ),
							document.createTextNode( ` ${ typeValue }` )
						)
						.parent()
						.css( 'vertical-align', 'middle' )
						.end()
						.remove();
				} else {
					elements.type.find( 'option[value*="task"]' ).remove();
				}
			}

			// Once a Version is set, remove newer versions.
			if ( version ) {
				elements.version.find( 'option' ).each( function () {
					const value = parseFloat( $( this ).val() );

					if ( ! value || value > version ) {
						$( this ).remove();
					}
				} );
			}

			// Rename the "Submit changes" buttons
			$( 'input[type="submit"][value="Submit changes"]' ).prop( 'value', 'Add Comment' );

			// Require a preview before a new ticket can be submitted.
			if ( '/newticket' === window.location.pathname && ! $( '#ticketbox.ticketdraft' ).length ) {
				$( '#propertyform div.buttons' ).html(
					'<input type="submit" name="preview" value="Continue to Preview" />'
				);
			}

			// Only gardeners may replace an existing attachment of the same name.
			$( '#attachment .options' ).remove();
		},

		reports() {
			const popup = $( '#report-popup' ),
				$headline = $( '#headline' );
			let failed = false;

			popup.on( 'change', '.tickets-by-topic', function () {
				const topic = $( this ).val();
				if ( ! topic ) {
					return;
				}
				window.location.href = $( this ).data( 'location' ) + topic;
				return false;
			} );

			popup.appendTo( '#main' );

			$( '.open-ticket-report' ).on( 'click', function ( event ) {
				// Allow opening the report on make.
				if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
					return;
				}

				// Calculate the correct position, even if the header size/etc changes.
				popup.css( 'top', $headline.offset().top + $headline.outerHeight() + 'px' );

				if ( popup.children().length === 0 ) {
					$.ajax( {
						url: 'https://make.wordpress.org/core/reports/?from-trac',
						xhrFields: { withCredentials: true },
					} )
						.done( function ( data ) {
							$( data ).find( '.ticket-reports' ).appendTo( popup );
							$body.addClass( 'ticket-reports-open' );
						} )
						.fail( function () {
							failed = true;
						} );
				} else {
					$body.toggleClass( 'ticket-reports-open' );
					event.preventDefault();
				}
				if ( ! failed ) {
					event.preventDefault();
				}
			} );
			popup.on( 'click', '.close', function () {
				$body.removeClass( 'ticket-reports-open' );
				return false;
			} );
		},

		redirectTicketsToProperTracker: ( function () {
			const component = $( '#field-component' );

			return {
				init() {
					// Special hack to not show the warning on Meta Trac for the WordPress.org site.
					if ( window.location.host === 'meta.trac.wordpress.org' ) {
						delete bugTrackerLocations[ 'WordPress.org Site' ];
					}

					// Prevent changing to the component if need be.
					if ( ! wpTrac.isNewTicket() ) {
						for ( const c in bugTrackerLocations ) {
							if ( ! bugTrackerLocations[ c ].prevent_changing_to ) {
								continue;
							}
							if ( component.val() !== c ) {
								component.children( 'option[value="' + c + '"]' ).remove();
							}
						}
					}

					// Show a notice when the component is selected.
					component.on( 'change', wpTrac.redirectTicketsToProperTracker.maybeShowNotice );

					// Trigger a warning on load, when the ticket is not closed.
					if ( ! wpTrac.isNewTicket() && ! $( '#action_reopen' ).length ) {
						wpTrac.redirectTicketsToProperTracker.maybeShowNotice();
					}

					$( '#propertyform' ).on( 'click', '#new-tracker-ticket', function () {
						const urlParams = {},
							href = $( this ).attr( 'href' );
						let url,
							summaryField = 'summary',
							descriptionField = 'description';

						// Trac (default) and GitHub are supported.
						if ( href.match( /github.com/ ) ) {
							summaryField = 'title';
							descriptionField = 'body';
						}

						urlParams[ summaryField ] = $( '#field-summary' ).val();
						urlParams[ descriptionField ] = $( '#field-description' ).val();

						url = href + ( href.indexOf( '?' ) !== -1 ? '&' : '?' ) + $.param( urlParams );
						if ( url.length > 1500 ) {
							urlParams[ descriptionField ] =
								"(Couldn't copy over your description as it was too long. Please paste it here. Your old window was not closed.)";
							url = href + ( href.indexOf( '?' ) !== -1 ? '&' : '?' ) + $.param( urlParams );
							window.open( url );
						} else {
							window.location.href = url;
						}
						return false;
					} );
				},

				maybeShowNotice() {
					const toggle = $( 'input[name="attachment"]' )
						.parent()
						.add( '.ticketdraft' )
						.add( '.wp-notice' )
						.add( 'div.buttons' );

					// Reset.
					$( '.wp-notice.component' ).remove();
					toggle.hide();

					const selectedComponent = component.val();
					if ( ! ( selectedComponent in bugTrackerLocations ) ) {
						toggle.show();
						return;
					}

					const tracker = bugTrackerLocations[ selectedComponent ];

					// If the component (ie. Editor) allows bypassing the warning show the create buttons.
					if ( ! wpTrac.isNewTicket() || tracker.allow_bypass ) {
						toggle.show();
					}

					$( 'div.buttons' ).before(
						`<div class="wp-notice component">
						<p><strong>Tickets related to ${ tracker.bug_text || selectedComponent }</strong> should be filed on the
						<a href="${ tracker.tracker }">${ tracker.tracker_text || tracker.tracker }</a></p>
						<p>Would you mind creating this ticket over there instead if appropriate?
						${
							tracker.enable_copy
								? `<a href="${ tracker.tracker }" id="new-tracker-ticket">Click here to copy your summary and description over</a>.`
								: ''
						}</p>
						${
							wpTrac.isNewTicket() && tracker.allow_bypass
								? `<p>If this isn't related to ${
										tracker.bug_text || selectedComponent
								  }, please continue to open this ticket here.</p>`
								: ''
						}
						</div>`
					);
				},
			};
		} )(),

		autocomplete: ( function () {
			let ticketParticipants = [],
				nonTicketParticipants = [],
				settings = {};

			return {
				init() {
					if ( ! $( '#comment' ).length ) {
						return;
					}

					if ( 'undefined' !== typeof wpTracAutoCompleteUsers ) {
						settings = wpTracAutoCompleteUsers;
					}

					this.initTicketParticipants();
					this.initNonTicketParticipants();

					// Adjusts the query so it doesn't search for 'achment' in case Ryan enters too many characters.
					const replacer = function ( query ) {
						return query.replace( /^(achment|achmen|achme|achm|ach|ac|a)/g, '' );
					};

					$( '#comment' )
						.atwho( {
							at: '@',
							callbacks: {
								filter: this.filterTicketParticipants,
								remoteFilter: this.filterNonTicketParticipants,
							},
						} )
						.atwho( {
							at: '[att',
							insertTpl: '${atwho-at}achment:"${name}"]',
							displayTpl: '<li>${display}</li>',
							data: this.getAttachments(),
							callbacks: {
								filter( query, data, searchKey ) {
									return this.callDefault( 'filter', replacer( query ), data, searchKey );
								},
								sorter( query, items, searchKey ) {
									return this.callDefault( 'sorter', replacer( query ), items, searchKey );
								},
								highlighter( li, query ) {
									return this.callDefault( 'highlighter', li, replacer( query ) );
								},
							},
						} );
				},

				filterNonTicketParticipants( query, callback ) {
					// Bail out if the query is empty.
					if ( '' === query ) {
						return callback();
					}

					const results = [],
						regex = new RegExp( '^' + query, 'ig' ); // start of string

					$.each( nonTicketParticipants, function ( key, value ) {
						if ( value.toLowerCase().match( regex ) ) {
							results.push( { name: value } );
						}
					} );

					callback( results );
				},

				filterTicketParticipants( query ) {
					// Bail out if the query is empty.
					if ( '' === query ) {
						return ticketParticipants;
					}

					const results = [],
						regex = new RegExp( '^' + query, 'ig' ); // start of string

					$.each( ticketParticipants, function ( key, value ) {
						if ( value.toLowerCase().match( regex ) ) {
							results.push( value );
						}
					} );

					return results;
				},

				initTicketParticipants() {
					let users = [],
						exclude = [];

					if ( 'undefined' !== typeof settings.exclude ) {
						exclude = settings.exclude;
					}

					// Most recent should show up first.
					$( $( '.change .username' ).get().reverse() ).each( function () {
						let username = $( this ).data( 'username' );

						// Override the username with the nicename if it differs by more than just case (ie. spaces, etc)
						if (
							$( this ).data( 'nicename' ) &&
							username.toLowerCase() !== $( this ).data( 'nicename' ).toLowerCase() &&
							wpTrac.currentUser !== username
						) {
							username = $( this ).data( 'nicename' );
						}

						if (
							typeof username !== 'undefined' &&
							-1 === $.inArray( username, users ) &&
							-1 === $.inArray( username, exclude )
						) {
							users.push( username );
						}
					} );

					// Add ticket reporter.
					let ticketReporter = $( '#ticket td[headers="h_reporter"]' ).text().trim();
					const ticketReporterNicename = $( '#ticket td[headers="h_reporter"] a' ).data( 'nicename' );
					// Override the username with the nicename if it differs by more than just case (ie. spaces, etc)
					if (
						ticketReporter &&
						ticketReporterNicename &&
						ticketReporter !== wpTrac.currentUser &&
						ticketReporter.toLowerCase() !== ticketReporterNicename.toLowerCase()
					) {
						ticketReporter = ticketReporterNicename;
					}

					if ( ticketReporter && -1 === $.inArray( ticketReporter, users ) ) {
						users.push( ticketReporter );
					}

					// Exclude current user.
					if ( wpTrac.currentUser ) {
						users = $.grep( users, function ( user ) {
							return user !== wpTrac.currentUser;
						} );
					}

					ticketParticipants = users;
				},

				getTicketParticipants() {
					return ticketParticipants;
				},

				addTicketParticipant( ticketParticipant ) {
					if ( -1 === $.inArray( ticketParticipant, ticketParticipants ) ) {
						$.merge( ticketParticipants, [ ticketParticipant ] );
					}
				},

				initNonTicketParticipants() {
					let users = [];

					if ( 'undefined' !== typeof settings.include ) {
						$.each( settings.include, function ( k, username ) {
							if (
								-1 === $.inArray( username, users ) &&
								-1 === $.inArray( username, ticketParticipants )
							) {
								users.push( username );
							}
						} );
					}

					// Exclude current user.
					if ( wpTrac.currentUser ) {
						users = $.grep( users, function ( user ) {
							return user !== wpTrac.currentUser;
						} );
					}

					nonTicketParticipants = users;
				},

				getNonTicketParticipants() {
					return nonTicketParticipants;
				},

				addNonTicketParticipant( nonTicketParticipant ) {
					if ( -1 === $.inArray( nonTicketParticipant, nonTicketParticipants ) ) {
						$.merge( nonTicketParticipants, [ nonTicketParticipant ] );
					}
				},

				getAttachments() {
					const attachments = [];

					// Most recent should show up first.
					$( $( 'dl.attachments dt' ).get().reverse() ).each( function () {
						attachments.push( {
							// Rendered by displayTpl as markup; matching uses `name`.
							display: escapeHtml( $( this ).text().replace( /\n/g, '' ) ),
							name: $( this ).find( 'a[title="View attachment"]' ).text().replace( /\n/g, '' ),
						} );
					} );

					return attachments;
				},
			};
		} )(),

		workflow: ( function () {
			let keywords = {},
				originalKeywords = {};
			const elements = {},
				// Keywords that cannot coexist. Adding one removes its counterpart.
				exclusiveKeywords = {
					'has-patch': 'needs-patch',
					'needs-patch': 'has-patch',
					'has-test-info': 'needs-test-info',
					'needs-test-info': 'has-test-info',
					'has-unit-tests': 'needs-unit-tests',
					'needs-unit-tests': 'has-unit-tests',
					'has-dev-note': 'needs-dev-note',
					'needs-dev-note': 'has-dev-note',
					'dev-reviewed': 'dev-feedback',
					'has-privacy-review': 'needs-privacy-review',
					'needs-privacy-review': 'has-privacy-review',
					'has-copy-review': 'needs-copy-review',
					'needs-copy-review': 'has-copy-review',
					'has-screenshots': 'needs-screenshots',
					'needs-screenshots': 'has-screenshots',
				};

			// Build a keyword bin <span> with its remove button.
			function keywordSpan( keyword ) {
				return $( '<span />' )
					.text( keyword )
					.attr( 'data-keyword', keyword )
					.prepend(
						$( '<button type="button" class="keyword-button-remove dashicons dashicons-dismiss" />' ).attr(
							'aria-label',
							'Remove ' + keyword + ' keyword'
						)
					);
			}

			return {
				init() {
					elements.hiddenEl = $( '#field-keywords' ).attr( 'aria-label', 'Manual keywords' );
					if ( ! elements.hiddenEl.length ) {
						return;
					}

					// Attach change event handler on the field-keywords input.
					elements.hiddenEl.on( 'change', wpTrac.workflow.populate );

					// Designed so the list could have come from another file.
					if ( typeof coreKeywordList === 'undefined' ) {
						return;
					}

					// Generate the workflow template.
					wpTrac.workflow.template();

					// Load up the initial keywords and the dropdown.
					wpTrac.workflow.populate();

					// Save these for later.
					originalKeywords = $.merge( [], keywords );

					// Catch the submit to see if keywords were simply reordered.
					elements.hiddenEl.parents( 'form' ).on( 'submit', wpTrac.workflow.submit );

					// Keyword removal.
					elements.bin.on( 'click', '.keyword-button-remove', function () {
						wpTrac.workflow.removeKeyword( $( this ).parent() );
						// Move focus to the Manual keyword button to avoid focus loss on keyword removal.
						$( '#edit-keywords' )
							.addClass( 'hide-programmatic-focus' )
							.trigger( 'focus' )
							.on( 'blur', function () {
								$( this ).removeClass( 'hide-programmatic-focus' );
							} );
					} );

					// Keyword adds.
					$( '#keyword-add' ).on( 'change keypress', function ( e ) {
						if ( e.type === 'keypress' ) {
							if ( e.which === 13 ) {
								e.stopPropagation();
								e.preventDefault();
							} else {
								return;
							}
						}
						wpTrac.workflow.addKeyword( $( this ).val() );
						$( this ).val( '' );
					} );

					// Manual keyword button.
					$( '#edit-keywords' ).on( 'click', function () {
						if ( elements.hiddenEl.is( ':visible' ) ) {
							elements.hiddenEl.hide();
							$( this ).attr( 'aria-expanded', 'false' );
							return;
						}

						$( this ).attr( 'aria-expanded', 'true' );
						elements.hiddenEl.show().trigger( 'focus' );
					} );

					// Handle keyboard interaction on the field-keywords field.
					$( '#field-keywords' ).on( 'keydown', function ( event ) {
						// When pressing Enter or Escape.
						if ( event.which === 13 || event.which === 27 ) {
							// Prevent form submission.
							event.preventDefault();
							// Hide the input field and populate the keywords.
							elements.hiddenEl.hide();
							/*
							 * Move focus back to the Manual keyword button.
							 * This blurs the field and triggers the `change`
							 * event thus the keywords are populated.
							 */
							$( '#edit-keywords' ).attr( 'aria-expanded', 'false' ).trigger( 'focus' );
						}
					} );
				},

				// Generates the workflow template.
				template() {
					const container = elements.hiddenEl.parent();
					let html;

					// Necessary to keep everything in line.
					const labelWidth = container.prev().width();

					// Rearrange the table to suit our needs.
					container
						.prev()
						.detach()
						.end()
						.attr( 'colspan', '2' )
						.addClass( 'has-js' )
						.parents( 'table' )
						.css( 'table-layout', 'fixed' );

					// If the owner field exists, then we're on /newticket. Remove it.
					$( '#field-owner' ).parents( 'tr' ).hide();

					html = `<div><label id="keyword-label" for="keyword-add" style="width:${ labelWidth }px">Workflow Keywords:</label>`;
					html += '<select id="keyword-add"><option value=""> - Add - </option></select>';
					html +=
						'<button type="button" id="edit-keywords" aria-label="Manual keyword" aria-expanded="false">Manual</button></div>';
					html += '<div id="keyword-bin"></div>';
					container.prepend( html );
					elements.bin = $( '#keyword-bin' );
				},

				// Populates the keywords and dropdown.
				populate() {
					// For repopulation. Starting over.
					if ( elements.bin.find( 'span' ).length ) {
						elements.bin.empty();
					}

					// Replace commas, collapse spaces, trim, then split by space.
					keywords = elements.hiddenEl.val().replace( /,/g, ' ' ).replace( / +/g, ' ' ).trim().split( ' ' );

					// Put our cleaned up version back into the hidden field.
					elements.hiddenEl.val( keywords.join( ' ' ) );

					// If we have a non-empty keyword, let's go through the process of adding the spans.
					if ( 1 !== keywords.length || keywords[ 0 ] !== '' ) {
						$.each( keywords, function ( k, v ) {
							const html = keywordSpan( v );
							if ( v in coreKeywordList ) {
								html.attr( 'title', coreKeywordList[ v ] );
							}
							html.appendTo( elements.bin );
						} );
					}

					// Populate the dropdown.
					if ( elements.add ) {
						elements.add.children().not( '[value=""]' ).remove();
					} else {
						elements.add = $( '#keyword-add' );
					}

					$.each( coreKeywordList, function ( k ) {
						// Don't show special (permission-based) ones.
						if ( ! wpTrac.gardener && -1 !== $.inArray( k, gardenerKeywordList ) ) {
							return;
						}
						// Don't show workflow keywords such as 'reporter-feedback' for new ticket.
						if ( wpTrac.isNewTicket() && -1 !== $.inArray( k, hideFromNewTickets ) ) {
							return;
						}
						elements.add.append(
							`<option value="${ k }${
								-1 !== $.inArray( k, keywords ) ? '" disabled="disabled">* ' : '">'
							}${ k }</option>`
						);
					} );
				},

				// Add a keyword. Takes a sanitized string.
				addKeyword( keyword ) {
					if ( ! keyword ) {
						return;
					}

					let title = '';

					// Don't add it again.
					if ( -1 !== $.inArray( keyword, keywords ) ) {
						return;
					}
					keywords.push( keyword );

					// Update the dropdown. Core keywords also get a title attribute with their description.
					if ( keyword in coreKeywordList ) {
						elements.add
							.find( 'option[value=' + keyword + ']' )
							.prop( 'disabled', true )
							.text( '* ' + keyword );
						title = coreKeywordList[ keyword ];
					}

					// Remove the mutually-exclusive counterpart, if any.
					if ( Object.prototype.hasOwnProperty.call( exclusiveKeywords, keyword ) ) {
						wpTrac.workflow.removeKeyword( exclusiveKeywords[ keyword ] );
					}

					// Add it to the bin, and refresh the hidden input.
					const html = keywordSpan( keyword );
					if ( title ) {
						html.attr( 'title', title );
					}
					html.appendTo( elements.bin );
					elements.hiddenEl.val( keywords.join( ' ' ) );
				},

				// Remove a keyword. Takes a jQuery object of a keyword in the bin, or a sanitized keyword as a string.
				removeKeyword( object ) {
					let keyword;
					if ( typeof object === 'string' ) {
						keyword = object;
						object = elements.bin.find( 'span[data-keyword="' + keyword + '"]' );

						if ( ! object.length ) {
							return;
						}
					} else {
						keyword = object.text();
					}

					keywords = $.grep( keywords, function ( v ) {
						return v !== keyword;
					} );

					// Update the core keyword dropdown.
					if ( keyword in coreKeywordList ) {
						elements.add
							.find( 'option[value=' + keyword + ']' )
							.prop( 'disabled', false )
							.text( keyword );
					}
					elements.hiddenEl.val( keywords.join( ' ' ) );
					object.remove();
				},

				// Check on submit that we're not just re-ordering keywords.
				// Otherwise, Trac flips out and adds a useless 'Keywords changed from X to X' marker.
				submit() {
					if ( keywords.length !== originalKeywords.length ) {
						return;
					}

					const testKeywords = $.grep( keywords, function ( v ) {
						return -1 === $.inArray( v, originalKeywords );
					} );

					// If the difference has no length, then restore to the original keyword order.
					if ( ! testKeywords.length ) {
						elements.hiddenEl.val( originalKeywords.join( ' ' ) );
					}
				},
			};
		} )(),

		focuses: ( function () {
			let field, container, focuses, originalFocuses;

			function init() {
				let classes;
				if ( typeof coreFocusesList === 'undefined' ) {
					return;
				}

				field = $( '#field-focuses' );
				if ( field.length === 0 ) {
					return;
				}
				if ( $( '#field-owner' ).length === 0 ) {
					$( 'label[for="field-focuses"]' ).parent().remove();
				}
				if ( field.parent().attr( 'colspan' ) === '3' ) {
					field.parent().attr( 'id', 'focuses' );
				} else {
					field.parent().attr( { colspan: 2, id: 'focuses' } );
				}
				field.hide();

				focuses = field.val().replace( /,/g, ' ' ).replace( / +/g, ' ' ).trim();
				if ( focuses.length === 0 ) {
					focuses = [];
				} else {
					focuses = focuses.split( ' ' );
				}
				originalFocuses = $.merge( [], focuses );

				container = $( '#focuses' );

				const ul = $( '<ul />' );
				$.each( coreFocusesList, function ( focus, description ) {
					let ariaPressed = 'false';
					classes = focus.replace( ' ', '-' );
					if ( -1 !== $.inArray( focus, focuses ) ) {
						classes += ' active';
						ariaPressed = 'true';
					}
					ul.append(
						$( '<li />', {
							'data-focus': focus,
							title: description,
							class: classes,
						} ).html(
							`<button type="button" class="core-focuses-button" aria-pressed="${ ariaPressed }">${
								focus === 'administration' ? 'admin' : focus
							}</button>`
						)
					);
				} );
				ul.appendTo( container );
				ul.wrap( '<fieldset id="fieldset-focuses" />' );
				ul.before( '<legend class="core-focuses-legend">Contributor Focuses:</legend>' );

				container.on( 'click', '.core-focuses-button', addRemove );
				container.closest( 'form' ).on( 'submit', submit );
				$( '#field-component' ).on( 'change', componentSync );
			}

			function addRemove() {
				const focus = $( this ).parent();
				if ( focus.hasClass( 'active' ) ) {
					remove( focus );
				} else {
					add( focus );
				}
			}

			function add( focus ) {
				if ( typeof focus === 'string' ) {
					focus = container.find( 'li.' + focus );
				}
				focus.addClass( 'active' );
				focus.find( '.core-focuses-button' ).attr( 'aria-pressed', 'true' );
				focuses.push( focus.data( 'focus' ) );
				updateField();
			}

			function remove( focus ) {
				if ( typeof focus === 'string' ) {
					focus = container.find( 'li.' + focus );
				}
				focus.removeClass( 'active' );
				focus.find( '.core-focuses-button' ).attr( 'aria-pressed', 'false' );
				const removedFocus = focus.data( 'focus' );
				focuses = $.grep( focuses, function ( value ) {
					return value !== removedFocus;
				} );
				updateField();
			}

			function updateField() {
				const orderedFocuses = [];
				$.each( coreFocusesList, function ( focus ) {
					if ( -1 !== $.inArray( focus, focuses ) ) {
						orderedFocuses.push( focus );
					}
				} );
				field.val( orderedFocuses.join( ', ' ) );
			}

			function componentSync() {
				const component = $( this ).val();
				if ( component === 'Network Admin' || component === 'Networks and Sites' ) {
					add( 'multisite' );
				}
			}

			function submit() {
				if ( focuses.length !== originalFocuses.length ) {
					return;
				}

				const testFocuses = $.grep( focuses, function ( v ) {
					return -1 === $.inArray( v, originalFocuses );
				} );

				// If the difference has no length, then restore to the original order.
				if ( ! testFocuses.length ) {
					field.val( originalFocuses.join( ', ' ) );
				}
			}

			return {
				init,
			};
		} )(),

		notifications: ( function () {
			let notifications, endpoint, star, _ticket, _nonce;

			function init( settings ) {
				$( hideCcField );
				if ( ! settings.authenticated ) {
					return;
				}
				endpoint = settings.endpoint;
				if ( settings.nonce ) {
					_nonce = settings.nonce;
				}
				if ( settings.ticket ) {
					_ticket = settings.ticket;
					ticketInit( _ticket );
				}
				$( reportInit );
			}

			function hideCcField() {
				const content = $( '#content' );
				if ( content.hasClass( 'query' ) ) {
					$( 'table.trac-clause tr.actions option[value="cc"]' ).remove();
					$( '#columns' ).find( 'input[type="checkbox"][name="col"][value="cc"]' ).parent().remove();
				}
				if ( content.hasClass( 'ticket' ) ) {
					// Remove the CC field in case the BlackMagic plugin didn't.
					$( '#field-cc' ).parent().parent().prev().remove().end().remove();
					hideCcComments();
					content.on( 'wpTracPostPreview', hideCcComments );
				}
			}

			function hideCcComments() {
				$( '#changelog div.change' )
					.has( 'li.trac-field-cc' )
					.each( function () {
						const change = $( this ),
							changes = change.children( 'ul.changes' );
						/* Three possibilities:
					   The comment is just a single CC (hide the whole comment)
					   The comment is a CC plus a comment (hide the CC line)
					   The comment contains multiple property changes (hide only the CC line)
					*/
						if ( changes.children( 'li' ).length === 1 ) {
							if ( change.children( 'div.comment' ).length === 0 ) {
								change.hide();
							} else {
								changes.hide();
							}
						} else {
							changes.children( 'li.trac-field-cc' ).hide();
						}
					} );
			}

			function ticketInit( ticket ) {
				$.ajax( {
					url: endpoint + '?trac-notifications=' + ticket,
					xhrFields: { withCredentials: true },
				} ).done( function ( data ) {
					if ( data.success ) {
						render( data.data[ 'notifications-box' ] );
						if ( data.data.maintainers ) {
							maintainerLabels( data.data.maintainers );
							//	wpTrac.autocomplete.addNonTicketParticipant( data.data.maintainers ); doesn't work yet, because ticketInit() runs before autocomplete.init()
						}

						if ( data.data.nonce ) {
							_nonce = data.data.nonce;
						}
					}
				} );
			}

			function maintainerLabels( maintainers ) {
				let i, len;
				const labels = {};
				for ( i = 0, len = maintainers.length; i < len; i++ ) {
					labels[ maintainers[ i ] ] = {
						text: 'Component Maintainer',
						title: `@${ maintainers[ i ] } maintains the ${ $( 'td[headers="h_component"]' )
							.text()
							.trim() } component`,
					};
				}
				wpTrac.showContributorLabels( labels );
			}

			function render( data ) {
				$( '#propertyform' ).before( data );
				notifications = $( '#notifications' );
				notifications
					.on( 'click', '.watch-this-ticket', subscribe )
					.on( 'click', '.watching-ticket', unsubscribe )
					.on( 'click', '.block-notifications', block )
					.on( 'click', '.unblock-notifications', unblock );

				$( '#ticket.trac-content > h2' ).prepend(
					`<div class="ticket-star dashicons dashicons-star-${
						notifications.hasClass( 'subscribed' ) ? 'filled' : 'empty'
					}" title="Watch/unwatch this ticket"></div>`
				);

				star = $( '.ticket-star' );

				star.on( 'click', function () {
					if ( $( this ).hasClass( 'dashicons-star-empty' ) ) {
						subscribe();
					} else {
						unsubscribe();
					}
				} );
				$( '.grid-toggle' ).on( 'click', 'a', function () {
					const names = $( this ).hasClass( 'names' );
					notifications.toggleClass( 'show-usernames', names );
					document.cookie =
						'wp_trac_ngrid=' + ( names ? 1 : 0 ) + ';max-age=31557600;domain=.wordpress.org;path=/';
					return false;
				} );

				// Trac notification control is broken due to the Trac upgrade to 1.2
				$(
					'#notifications p.receiving-notifications, #notifications p.receiving-notifications-because, #notifications p.not-receiving-notifications, #notifications .preferences'
				).hide();
			}

			function save( action, ticket, nonce ) {
				ticket = ticket || _ticket;
				nonce = nonce || _nonce;
				$.ajax( {
					type: 'POST',
					url: endpoint,
					xhrFields: { withCredentials: true },
					data: {
						'trac-ticket-sub': ticket,
						action,
						nonce,
					},
				} );
			}

			function subscribe() {
				save( 'subscribe' );
				notifications.removeClass( 'blocked' ).addClass( 'subscribed' );
				star.toggleClass( 'dashicons-star-empty dashicons-star-filled' );
				if ( notifications.hasClass( 'receiving' ) ) {
					notifications.addClass( 'block' );
				}
				changeCount( 1 );
				return false;
			}

			function unsubscribe() {
				save( 'unsubscribe' );
				notifications.removeClass( 'subscribed' );
				star.toggleClass( 'dashicons-star-empty dashicons-star-filled' );
				if ( notifications.hasClass( 'receiving' ) ) {
					notifications.addClass( 'block' );
				}
				changeCount( -1 );
				return false;
			}

			function changeCount( delta ) {
				const count = parseInt( notifications.find( '.count' ).text(), 10 ) + delta;
				notifications.find( '.count' ).text( count );
				notifications.toggleClass( 'count-0', count === 0 ).toggleClass( 'count-1', count === 1 );
			}

			function block() {
				save( 'block' );
				notifications.removeClass( 'block' ).addClass( 'blocked' );
				return false;
			}

			function unblock() {
				save( 'unblock' );
				notifications.removeClass( 'blocked' ).addClass( 'block' );
				return false;
			}

			function reportInit() {
				const tickets = [],
					cells = $( 'table.listing' ).find( 'td.Stars' );

				if ( cells.length === 0 ) {
					return;
				}
				cells.wrapInner( '<span class="count" />' );
				cells.append( ' <div class="dashicons dashicons-star-empty loading trac-report-star"></div>' );
				const stars = $( '.trac-report-star' );
				stars.each( function () {
					const reportStar = $( this );

					const ticket = parseInt(
						reportStar.parent().siblings( 'td.ticket' ).find( 'a' ).text().replace( '#', '' ),
						10
					);
					tickets.push( ticket );
					reportStar.data( 'ticket', ticket );
				} );

				$.ajax( {
					type: 'POST',
					url: endpoint,
					xhrFields: { withCredentials: true },
					data: {
						'trac-ticket-subs': true,
						tickets,
					},
				} ).done( function ( data ) {
					if ( ! data.success ) {
						return;
					}

					if ( data.data.nonce ) {
						_nonce = data.data.nonce;
					}

					stars
						.each( function () {
							if ( -1 !== $.inArray( $( this ).data( 'ticket' ), data.data.tickets ) ) {
								$( this ).toggleClass( 'dashicons-star-empty dashicons-star-filled' );
							}
						} )
						.removeClass( 'loading' )
						.on( 'click', function () {
							const clickedStar = $( this );
							clickedStar.toggleClass( 'dashicons-star-empty dashicons-star-filled' );
							const action = clickedStar.hasClass( 'dashicons-star-filled' )
								? 'subscribe'
								: 'unsubscribe';
							const delta = 'subscribe' === action ? 1 : -1;
							save( action, clickedStar.data( 'ticket' ) );

							let count = parseInt( clickedStar.prev().text(), 10 );
							if ( isNaN( count ) ) {
								count = 0;
							}
							count += delta;
							clickedStar.prev().text( count ? count : '' );
						} );
				} );
			}

			return {
				init,
			};
		} )(),

		githubPRs: ( function () {
			const apiEndpoint = 'https://api.wordpress.org/dotorg/trac/pr/',
				authenticated = !! ( wpTracCurrentUser && wpTracCurrentUser !== 'anonymous' );
			let trac = false,
				ticket = 0,
				primaryGitRepo,
				primaryGitRepoDesc,
				container;

			function init() {
				if ( $body.hasClass( 'core' ) ) {
					trac = 'core';
					primaryGitRepo = 'WordPress/wordpress-develop';
					primaryGitRepoDesc = 'WordPress GitHub mirror';
				} else if ( $body.hasClass( 'meta' ) ) {
					trac = 'meta';
					primaryGitRepo = 'WordPress/wordpress.org';
					primaryGitRepoDesc = 'WordPress.org Meta GitHub mirror';
				} else if ( $body.hasClass( 'bbpress' ) ) {
					trac = 'bbpress';
					primaryGitRepo = 'bbpress/bbPress';
					primaryGitRepoDesc = 'bbPress GitHub mirror';
				} else if ( $body.hasClass( 'buddypress' ) ) {
					trac = 'buddypress';
					primaryGitRepo = 'buddypress/buddypress';
					primaryGitRepoDesc = 'BuddyPress GitHub mirror';
				}

				if ( ! trac ) {
					return;
				}

				// Add ability to include GitHub tickets into a 'my-patches' report.
				// "Just" include a variable called '$GITHUBTICKETS' in the Query.
				const $warning = $( "#warning.system-message:contains('GITHUBTICKETS')" );
				if ( $warning.length ) {
					renderReportLoadGitHubTickets( $warning );
				}

				// This seems to be the easiest place to find the current Ticket ID..
				ticket = $( 'link[rel="canonical"]' )
					?.prop( 'href' )
					?.match( /\/ticket\/(\d+)$/ )?.[ 1 ];
				if ( ! ticket ) {
					return;
				}

				// Add the section immediately.
				renderAddSection();

				// Fetch the PRs immediately
				fetchPRs();

				// deProxy github images, CORS changes.
				deProxyImages();
			}

			// See https://meta.trac.wordpress.org/ticket/7442
			function deProxyImages() {
				$( 'img[src*="i0.wp.com/github.com/"]' ).each( function () {
					const $this = $( this ),
						$parent = $this.parent( 'a' );
					$this.removeAttr( 'crossorigin' ); // We trust GitHub for these images.
					$this.prop( 'src', $this.prop( 'src' ).replace( /i0\.wp\.com/, '' ) );
					$this.prop( 'alt', $this.prop( 'alt' ).replace( /i0\.wp\.com/, '' ) );
					$this.prop( 'title', $this.prop( 'title' ).replace( /i0\.wp\.com/, '' ) );
					$parent.prop( 'href', $parent.prop( 'href' ).replace( /i0\.wp\.com/, '' ) );
				} );
			}

			function fetchPRs() {
				const params = new URLSearchParams( { trac, ticket } );
				if ( authenticated ) {
					params.set( 'authenticated', '1' );
					if ( 'URL' in window ) {
						params.set(
							'_lastmod',
							new URL( window.jQuery( 'a.timeline' ).last().prop( 'href' ) ).searchParams.get( 'from' )
						);
					}
				}
				$.ajax( `${ apiEndpoint }?${ params }` ).done( function ( data ) {
					// Update the number
					container.find( 'h3 .trac-count' ).removeClass( 'hidden' ).find( 'span' ).text( data.length );

					const prContainer = container.find( '.pull-requests' );
					if ( data.length ) {
						// Remove the placeholder.
						prContainer.find( '.loading' ).remove();

						// Render the PRs
						for ( const i in data ) {
							renderPR( prContainer, data[ i ] );
						}
					} else {
						// Change the loading placeholder
						prContainer.find( '.loading div' ).html(
							`To link a Pull Request to this ticket, create a new Pull Request in the
							<a href="https://github.com/${ primaryGitRepo }">${ primaryGitRepoDesc }</a>
							and include this ticket’s URL in the description.`
						);
					}
				} );
			}

			function renderAddSection() {
				// Add the Pull Requests section, #attachments is only present if authenticated or there exists uploads.
				let afterDiv = $( '#attachments' );
				if ( ! afterDiv.length ) {
					afterDiv = $( '#commits' );
				}

				afterDiv.after(
					`<div id="github-prs">
					<h3 class="foldable"><a id="section-pr" href="#section-pr">Pull Requests <span class="trac-count hidden">(<span></span>)</span></a></h3>
					<ul class="pull-requests">
					<li class="loading"><div>Loading…</div></li>
					</ul>
					</div>`
				);
				// keep this for later.
				container = $( '#github-prs' );

				// Make the section collapse.
				container.find( '#section-pr' ).on( 'click', function () {
					const $div = $( this.parentNode.parentNode ).toggleClass( 'collapsed' );
					return ! $div.hasClass( 'collapsed' );
				} );
			}

			function renderReportLoadGitHubTickets( $warning ) {
				let user = wpTracCurrentUser;
				const match = document.location.search.match( /USER=([^&]+)/ );
				if ( match ) {
					user = match[ 1 ];
				}

				// Logged out requests without a user context.
				if ( 'anonymous' === user ) {
					$warning.remove();
					return;
				}

				$warning.html(
					'<strong>Warning:</strong> Tickets with an attached GitHub PRs not included <button>Load PRs</button>'
				);

				$warning.on( 'click', function () {
					$( this ).find( 'button' ).prop( 'disabled', 'disabled' ).text( 'Please wait..' );

					const params = new URLSearchParams( { trac, author: user } );
					if ( authenticated ) {
						params.set( 'authenticated', '1' );
					}
					$.ajax( `${ apiEndpoint }?${ params }` ).done( function ( ticketList ) {
						document.location = `${ document.location }${
							document.location.search ? '&' : '?'
						}GITHUBTICKETS=${ ticketList.join( ',' ) }`;
					} );
				} );
			}

			// Logic to determine what the PRs status is
			function prStatus( data ) {
				const stack = [];
				let emojiState = '';

				// Closed? Skip everything else.
				if ( data.closed_at ) {
					return '✅ Closed';
				}

				// Merge State then
				switch ( data.mergeable_state ) {
					case 'draft':
						stack.push( 'Draft' );
						break;
					case 'blocked':
						// All Good (but blocked due to Branch protection rules, or Merge requirements)
						// or Changes Requested
						// or Unit Tests Failing.
						if (
							data.reviews.CHANGES_REQUESTED ||
							( data.check_runs && Object.values( data.check_runs ).includes( 'failed' ) )
						) {
							// Let the unit tests / reviews section take care of it.
							break;
						} // else fall through.
					case 'clean':
						emojiState = '✅';
						stack.push( 'All checks pass' );
						break;
					case 'dirty':
						emojiState = '❌';
						stack.push( 'Merge conflicts' );
						break;
					case 'unstable':
						emojiState = '❌';
						stack.push( 'Failing tests' );
						break;
					case 'unknown':
						stack.push( 'Unknown' );
						break;
				}

				// Unit Tests?
				if ( data.check_runs ) {
					for ( const provider in data.check_runs ) {
						const providerText = escapeHtml( provider );
						const checkStatus = escapeHtml( data.check_runs[ provider ] );

						switch ( data.check_runs[ provider ] ) {
							case 'in_progress':
								stack.push( providerText + ' running' );
								break;
							case 'failed':
								emojiState = '❌';
								stack.push( providerText );
								break;
							case 'success':
								continue;
							default:
								stack.push( providerText + ' ' + checkStatus );
								break;
						}
					}
				}

				// Changes requested?
				if ( data.reviews ) {
					if ( data.reviews.APPROVED ) {
						emojiState = '✅';
						stack.push(
							$( '<span>Approved</span>' ).prop(
								'title',
								'Changes approved by: ' + data.reviews.APPROVED.join( ', ' )
							)[ 0 ].outerHTML
						);
					}
					if ( data.reviews.CHANGES_REQUESTED ) {
						emojiState = '❌';
						stack.push(
							$( '<span>Changes Requested</span>' ).prop(
								'title',
								'Changes requested by: ' + data.reviews.CHANGES_REQUESTED.join( ', ' )
							)[ 0 ].outerHTML
						);
					}
				}

				return emojiState + ' ' + stack.join( ', ' );
			}

			function renderPR( prContainer, data ) {
				// Not the nicest, but it works and escapes things properly if given correct inputs.
				const htmlElement = function ( element, attributes, text = '' ) {
					return $( '<p>' )
						.append( $( '<' + element + '/>', attributes ).text( text ) )
						.html();
				};

				// Strip off any ticket numbers from the start of the PR title for display.
				data.title = data.title.replace( /^#\d+\s*/, '' );

				prContainer.append(
					`<li>
					<div>${ htmlElement(
						'a',
						{ href: data.changes.html_url, title: data.title },
						'#' + data.number + ' ' + data.title
					) } by&nbsp;${ htmlElement( 'a', { href: data.user.url }, '@' + data.user.name ) }</div>
					<div>${ prStatus( data ) }</div>
					<div>${ htmlElement( 'ins', {}, '+' + data.changes.additions ) }&nbsp;${ htmlElement(
						'del',
						{},
						'-' + data.changes.deletions
					) }</div>
					<div>${ htmlElement( 'a', { href: data.changes.patch_url, class: 'button' }, 'View patch' ) }&nbsp;${ htmlElement(
						'a',
						{ href: data.changes.html_url, class: 'button' },
						'View PR'
					) }</div>
					</li>`
				);
			}

			return {
				init,
			};
		} )(),

		suggestNotGeneral: ( function () {
			const skipWords = [ 'and', 'any', 'all', 'the', 'for', 'get', 'plugins', 'general', 'wordpress' ],
				generalCategories = [ 'General' ],
				componentWords = {};
			let enabled = true,
				noticeDiv;

			function init() {
				// Only on new ticket creations.
				if ( ! wpTrac.isNewTicket() ) {
					return;
				}

				// bbPress Trac.. has a set of components that I wish everyone had.
				if ( $( 'body.bbpress' ).length ) {
					skipWords.push( 'api' );
					skipWords.push( 'component' );
					skipWords.push( 'tools' );
					skipWords.push( 'appearance' );
				}

				// On Meta, WordPress.org site is a "generic" category that shouldn't be used if possible.
				if ( $( 'body.meta' ).length ) {
					generalCategories.push( 'WordPress.org Site' );
					skipWords.push( 'wordpress.org' );
				}

				// Only if we have a 'General' option.
				const components = window
						.jQuery( '#field-component option' )
						.get()
						.map( ( opt ) => opt.value ),
					hasDefaultCat = generalCategories.some( ( value ) => components.includes( value ) );

				if ( ! hasDefaultCat ) {
					return;
				}

				generateComponentWords();

				$( '#field-description,#field-summary,#field-component' ).on( 'blur', maybeSuggest );

				// Disable once the user hits the component option.
				$( '#field-component' ).on( 'change', function () {
					// If they selected a general category keep nagging.
					enabled = -1 !== generalCategories.indexOf( $( this ).val() );

					if ( ! enabled && noticeDiv ) {
						noticeDiv.remove();
						noticeDiv = false;
					}
				} );
			}

			function maybeSuggest() {
				const matchText =
						$( '#field-summary' ).val().toLowerCase() + ' ' + $( '#field-description' ).val().toLowerCase(),
					matchingWords = [],
					matchingComponents = [];

				if ( ! enabled || ! matchText.length ) {
					return;
				}

				for ( const word of Object.keys( componentWords ) ) {
					if ( matchText.includes( word ) ) {
						matchingWords.push( word );
					}
				}

				// Longest match first.
				matchingWords.sort( ( a, b ) => ( a.length > b.length ? -1 : 1 ) );

				matchingWords.forEach( ( word ) => {
					componentWords[ word ].forEach( ( component ) => {
						if ( -1 === matchingComponents.indexOf( component ) ) {
							matchingComponents.push( component );
						}
					} );
				} );

				if ( ! noticeDiv ) {
					noticeDiv = $( '<div id="componentSuggest"/>' ).insertBefore( $( '.buttons' ).first() );

					noticeDiv.on( 'click', 'a.component', function ( e ) {
						e.preventDefault();
						const component = $( this ).text();

						$( `#field-component option[value="${ component }"]` )
							.prop( 'selected', 'selected' )
							.trigger( 'change' );
					} );
				}
				noticeDiv.html( getNoticeHTML( matchingComponents ) );
			}

			function getNoticeHTML( matchingComponents ) {
				const hasMatches = matchingComponents.length > 0,
					template = $(
						`<div class="wp-notice"><p><strong>Have you selected the right component?</strong></p>
						<p>You've not yet selected a component.
						Please check the "Component" option above${ hasMatches ? ' or select from one of the following:' : '.' }</p>
						</div>`
					);

				if ( hasMatches ) {
					const ulList = $( '<ul/>' );

					matchingComponents.forEach( ( component ) => {
						ulList.append( $( `<li><a href="#" class="component">${ component }</a></li>` ) );
					} );
					template.append( ulList );
				}

				return template;
			}

			function generateComponentWords() {
				$( '#field-component option' ).each( function () {
					const component = $( this ).val();

					// Never suggest General..
					if ( 'General' === component ) {
						return;
					}

					const words = component.split( /[^A-Za-z0-9\.']+/ );

					if ( words.length > 1 ) {
						words.push( component );
					}

					// If it's a plural, add the non-plural form.
					words.forEach( ( word ) => {
						if ( 's' === word.substr( -1 ) ) {
							words.push( word.substr( 0, word.length - 1 ) );
						}
					} );

					words.forEach( ( word ) => {
						if ( ! word ) {
							return;
						}

						word = word.toLowerCase();

						if ( component !== word && -1 !== skipWords.indexOf( word ) ) {
							return;
						}

						if ( ! ( word in componentWords ) ) {
							componentWords[ word ] = [];
						}

						componentWords[ word ].push( component );
					} );
				} );

				return componentWords;
			}

			return {
				init,
			};
		} )(),

		addDynamicAssetCacheBuster() {
			/*
			 * Mirrors the `?v=` a server-side rewrite adds to un-queried s.w.org
			 * assets, so dynamic loads request the same URL as the static tags. Inert
			 * until that rewrite returns; it was lost in the Jinja2 port. Do not key
			 * it off scripts_version instead: the URLs would then differ, and
			 * $.loadStyleSheet de-dupes on the exact string, so it would append a
			 * second trac.css after wp-trac.css and Trac's rules would win.
			 */
			let cacheBuster = $( 'script[src^="https://s.w.org"][src*="v="]' ).attr( 'src' );
			if ( cacheBuster ) {
				cacheBuster = ( cacheBuster.match( /v=([0-9]+)$/ ) || [] )[ 1 ];
			}
			const maybeAddCacheBuster = function ( href ) {
				if ( cacheBuster && href.match( /https:\/\/s.w.org/i ) && href.match( /[.](css|js)$/ ) ) {
					href += '?v=' + cacheBuster;
				}
				return href;
			};
			const oldLoadScript = $.loadScript;
			$.loadScript = function ( href, type, charset ) {
				return oldLoadScript( maybeAddCacheBuster( href ), type, charset );
			};
			const oldLoadStylesheet = $.loadStyleSheet;
			$.loadStyleSheet = function ( href ) {
				return oldLoadStylesheet( maybeAddCacheBuster( href ) );
			};
		},

		disableTracAutoFocus() {
			// Disable the Trac autofocus which scrolls past the intro to creating tickets.
			$( '.trac-autofocus' ).removeClass( 'trac-autofocus' );
		},

		/**
		 * Collapses the report-list groups before first paint.
		 *
		 * Trac's folding.js only collapses them on DOM ready, which flashes the fully
		 * expanded list. Deep links to a specific group (#no<n>) skip the pre-collapse,
		 * as folding.js leaves the linked group open.
		 */
		preCollapseReportList() {
			if ( '/report' === window.location.pathname && ! /^#no\d+$/.test( window.location.hash ) ) {
				$( '#content.report .reports > div' ).addClass( 'collapsed' );
			}
		},
	};

	$( document ).ready( wpTrac.init );

	// Perform this as soon as this file loads.
	wpTrac.disableTracAutoFocus();
	wpTrac.addDynamicAssetCacheBuster();
	wpTrac.preCollapseReportList();
} )( window.jQuery );
