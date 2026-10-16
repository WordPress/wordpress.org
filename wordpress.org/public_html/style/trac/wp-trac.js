let wpTrac,
	coreKeywordList,
	gardenerKeywordList,
	hideFromNewTickets,
	reservedTerms,
	coreFocusesList,
	bugTrackerLocations;

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
		'add-to-field-guide': 'Ticket dev-note should be included in the release field guide.',
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

	// Pre-compiled regular expressions for text-node linkers for Gutenberg and mentions.
	// See https://github.com/regexps/mentions-regex/blob/master/index.js#L21
	const combinedRegEx =
		/(?:(^|[^a-zA-Z0-9_＠!@#$%&*])(?:@|＠)(?!\/)([a-zA-Z0-9_](?:[a-zA-Z0-9_\-.]{0,18}[a-zA-Z0-9_])?)(?:\b(?!@|＠)|$))|(?:\bGB-?(\d+)\b)/gi;

	/**
	 * Runs a callback when the DOM is ready.
	 *
	 * @param {Function} fn Callback function.
	 */
	function onReady( fn ) {
		if ( document.readyState === 'loading' ) {
			document.addEventListener( 'DOMContentLoaded', fn );
		} else {
			fn();
		}
	}

	/**
	 * Escapes a value for insertion into an HTML string.
	 *
	 * @param {string} value Value to escape.
	 * @return {string} The escaped value.
	 */
	function escapeHtml( value ) {
		if ( typeof value !== 'string' ) {
			value = String( value ?? '' );
		}
		return value
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' )
			.replace( /'/g, '&#39;' );
	}

	/**
	 * Rewrites regex matches found in an element's text nodes.
	 *
	 * @param {Element}  root     Element whose text nodes are walked.
	 * @param {RegExp}   regex    Global regex tested against each text node's value.
	 * @param {Function} replacer Callback returning a string, DOM Node, or array of either.
	 */
	function linkTextNodes( root, regex, replacer ) {
		const walker = document.createTreeWalker( root, window.NodeFilter.SHOW_TEXT, {
			acceptNode( node ) {
				if ( node.parentElement && node.parentElement.closest( 'a' ) ) {
					return window.NodeFilter.FILTER_REJECT;
				}
				return node.nodeValue && node.nodeValue.trim()
					? window.NodeFilter.FILTER_ACCEPT
					: window.NodeFilter.FILTER_SKIP;
			},
		} );

		const textNodes = [];
		let node;

		while ( ( node = walker.nextNode() ) ) {
			textNodes.push( node );
		}

		textNodes.forEach( function ( textNode ) {
			const value = textNode.nodeValue;

			if ( ! regex.test( value ) ) {
				return;
			}
			regex.lastIndex = 0;

			const matches = [ ...value.matchAll( regex ) ];

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
		gardener: true === window.wpBugGardener,
		currentUser: window.wpTracCurrentUser || null,
		isSubmitting: false,

		init() {
			// Gardener status as a body class, for rules that cannot see the flag.
			document.body.classList.toggle( 'wp-bug-gardener', wpTrac.gardener );

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

			if ( window.wpTracContributorLabels ) {
				wpTrac.showContributorLabels( window.wpTracContributorLabels );
			}

			wpTrac.autocomplete.init();
			wpTrac.linkTicketText();
			wpTrac.githubPRs.init();
			wpTrac.suggestNotGeneral.init();

			if ( ! document.body.classList.contains( 'plugins' ) ) {
				wpTrac.workflow.init();
				if ( document.body.classList.contains( 'core' ) ) {
					wpTrac.reports();
					wpTrac.focuses.init();
				}
			}
		},

		isNewTicket() {
			return '/newticket' === window.location.pathname;
		},

		showContributorLabels( labels ) {
			document.querySelectorAll( 'h3.change .username' ).forEach( ( el ) => {
				const username = el.dataset.username;

				if ( username && username in labels ) {
					const span = document.createElement( 'span' );
					span.className = 'contributor-label';

					if ( typeof labels[ username ] === 'object' ) {
						span.title = labels[ username ].title;
						span.textContent = labels[ username ].text;
					} else {
						span.textContent = labels[ username ];
					}

					const line = el.closest( '.username-line' );
					if ( line ) {
						line.append( '\u2002', span );
					}
				}
			} );
		},

		linkTicketText( selector ) {
			document.querySelectorAll( selector || 'div.change .comment, #ticket .description' ).forEach( ( el ) => {
				linkTextNodes( el, combinedRegEx, function ( match, pre, username, gbNumber ) {
					// Gutenberg Issue (GB1234 or GB-1234)
					if ( gbNumber ) {
						const link = document.createElement( 'a' );
						link.className = 'gutenberg-issue github ext-link';
						link.href = `https://github.com/WordPress/Gutenberg/issues/${ gbNumber }`;

						const icon = document.createElement( 'span' );
						icon.className = 'icon';
						icon.textContent = '\u200B';

						link.append( icon, document.createTextNode( match ) );
						return link;
					}

					// User-Mention (@username)
					if ( username ) {
						if ( reservedTerms.includes( username ) ) {
							return match;
						}

						const meClass = username === wpTrac.currentUser ? ' me' : '';
						const link = document.createElement( 'a' );
						link.className = `mention${ meClass }`;
						link.href = `https://profiles.wordpress.org/${ encodeURIComponent( username ) }/`;
						link.textContent = `@${ username }`;

						return [ pre, link ];
					}

					return match;
				} );
			} );
		},

		// These ticket hacks need to be re-run after ticket previews.
		postPreviewHacks() {
			// Automatically preview images.
			document.querySelectorAll( 'li.trac-field-attachment' ).forEach( ( li ) => {
				const appendTo = li.closest( 'div.change' );
				if ( ! appendTo || appendTo.querySelector( '.trac-image-preview' ) ) {
					return;
				}

				const el = li.querySelector( '.trac-rawlink' );
				const href = el?.getAttribute( 'href' );
				if ( ! href ) {
					return;
				}

				if ( /\.(jpg|jpeg|png|gif|svg|webp)(\?.*)?$/i.test( href ) ) {
					const image = new window.Image();
					image.src = href;
					image.onload = function () {
						if ( ! appendTo.isConnected || appendTo.querySelector( '.trac-image-preview' ) ) {
							return;
						}

						const link = document.createElement( 'a' );
						link.href = href.replace( '/raw-attachment/', '/attachment/' );

						const img = document.createElement( 'img' );
						img.src = href;
						img.width = image.width;
						img.height = image.height;
						img.className = 'trac-image-preview';

						link.append( img );
						appendTo.append( link );
					};
				} else if ( /\.(mp4|mov|webm)(\?.*)?$/i.test( href ) ) {
					const video = document.createElement( 'video' );
					video.src = href;
					video.className = 'trac-image-preview';
					video.controls = true;
					video.preload = 'metadata';
					appendTo.append( video );
				}
			} );

			wpTrac.linkTicketText( '.ticketdraft .comment' );
		},

		hacks() {
			const content = document.getElementById( 'content' );

			// Add deprecated notice for core's test repository.
			if ( document.body.classList.contains( 'core' ) && content?.classList.contains( 'browser' ) ) {
				const testRepoLink = document
					.getElementById( 'repoindex' )
					?.querySelector( 'a[href="/browser/tests"]' );
				testRepoLink?.parentElement?.insertAdjacentHTML(
					'beforeend',
					'<p style="display:inline">Deprecated. <a href="/browser/trunk/tests">Please see default repository</a>.</p>'
				);

				if ( window.location.pathname.startsWith( '/browser/tests' ) ) {
					content?.insertAdjacentHTML(
						'beforebegin',
						'<div class="system-message warning">You are currently viewing the <strong>deprecated</strong> test repository. You may want to <a href="/browser/trunk/tests">view the tests in the default repository</a>.</div>'
					);
				}
			}

			if ( document.body.classList.contains( 'themes' ) ) {
				const hReporter = document.getElementById( 'h_reporter' );
				if ( hReporter ) {
					hReporter.textContent = 'Developer:';
				}
				const hOwner = document.getElementById( 'h_owner' );
				if ( hOwner ) {
					hOwner.textContent = 'Reviewer:';
				}

				// Prevent uploading of ZIP files to Trac.
				// See https://meta.trac.wordpress.org/ticket/3904
				document.querySelectorAll( '#attachment input[type="file"]' ).forEach( ( fileInput ) => {
					fileInput.addEventListener( 'change', function () {
						const ext = this.value.split( '.' ).pop();
						document.getElementById( 'wp-block-zip-upload' )?.remove();
						if ( 'zip' === ext ) {
							this.value = '';
							this.closest( 'div.field' )?.insertAdjacentHTML(
								'afterend',
								'<div class="wp-notice" id="wp-block-zip-upload"><p><strong>Please do not upload ZIPs to Trac.</strong><br>All Theme ZIPs (including updates) should be submitted via <a href="https://wordpress.org/themes/upload/">https://wordpress.org/themes/upload/</a>.</p></div>'
							);
						}
					} );
				} );
			}

			// Change 'Comments' and 'Stars' columns to dashicons glyphs to save space.
			document.querySelectorAll( 'thead a[href*="sort=Comments"]' ).forEach( ( a ) => {
				a.innerHTML = '<div class="dashicons dashicons-admin-comments"></div>';
			} );
			document.querySelectorAll( 'thead a[href*="sort=Stars"]' ).forEach( ( a ) => {
				a.innerHTML = '<div class="dashicons dashicons-star-empty"></div>';
			} );

			// Link username in header.
			wpTrac.linkHeaderUsername();

			// Ticket-only tweaks.
			if ( content?.classList.contains( 'ticket' ) ) {
				wpTrac.redirectTicketsToProperTracker.init();

				// A collection of ticket hacks that must be run again after previews.
				wpTrac.postPreviewHacks();
				content.addEventListener( 'wpTracPostPreview', wpTrac.postPreviewHacks );

				// Allow 'Modify Ticket' to be shown even after a Trac preview tries to close it,
				// but only if it was already open.
				wpTrac.keepModifyTicketOpen();

				// Open WikiFormatting links in a new window.
				content.addEventListener( 'click', ( event ) => {
					const link = event.target.closest( 'a[href$="wiki/WikiFormatting"]' );
					if ( link ) {
						event.preventDefault();
						window.open( link.getAttribute( 'href' ) );
					}
				} );

				// Submit comment form on Cmd/Ctrl + Enter.
				document.getElementById( 'comment' )?.addEventListener( 'keydown', ( event ) => {
					if ( ( event.ctrlKey || event.metaKey ) && event.key === 'Enter' ) {
						document.querySelector( 'input[name="submit"]' )?.click();
					}
				} );

				// Move all of the ticket actions text into the label.
				// Trac markup is like this: `<label>close</label> as fixed`
				document.querySelectorAll( '#action div label' ).forEach( ( label ) => {
					const next = label.nextSibling;
					if ( next && 3 === next.nodeType ) {
						label.textContent += next.nodeValue;
						next.remove();
					}
				} );

				// Point users to open new tickets when they comment on old tickets.
				if ( document.querySelector( '#ticket .milestone.closed' ) ) {
					const component = document.getElementById( 'field-component' )?.value || '',
						ticketId = document.querySelector( '.trac-id' )?.textContent?.trim() || '',
						newticket = `/newticket?component=${ encodeURIComponent(
							component
						) }&description=${ encodeURIComponent( `This is a follow-up to ${ ticketId }.` ) }`;

					document.querySelector( '#trac-add-comment fieldset' )?.insertAdjacentHTML(
						'afterbegin',
						`<p class="ticket-reopen-notice"><span class="dashicons dashicons-info"></span>
						<strong>This ticket was closed on a completed milestone.</strong><br />
						If you have a bug or enhancement to report, please <a href="${ newticket }">open a new ticket</a>.
						Be sure to mention this ticket, ${ escapeHtml( ticketId ) }.</p>`
					);

					if ( ! wpTrac.gardener ) {
						document.getElementById( 'action_reopen' )?.parentElement?.remove();
					}
				}

				const normalize = ( str ) => ( str || '' ).replace( /\r\n/g, '\n' ).trim();

				/**
				 * Checks whether an input or textarea element has unsaved changes compared to its initial value.
				 *
				 * @param {string} selector CSS selector for the form element.
				 * @return {boolean} True if the field exists and its value was modified, false otherwise.
				 */
				function isDirty( selector ) {
					const el = document.querySelector( selector );
					if ( ! el ) {
						return false;
					}

					return normalize( el.value ) !== normalize( el.defaultValue );
				}

				/**
				 * Checks whether an input or textarea element has a non-empty value.
				 *
				 * @param {string} selector CSS selector for the form element.
				 * @return {boolean} True if the field exists and contains non-whitespace text, false otherwise.
				 */
				function hasValue( selector ) {
					return Boolean( document.querySelector( selector )?.value.trim() );
				}

				window.addEventListener( 'beforeunload', function ( event ) {
					if ( wpTrac.isSubmitting ) {
						return;
					}

					const hasUnsavedContent = wpTrac.isNewTicket()
						? hasValue( '#field-summary' ) || hasValue( '#field-description' )
						: hasValue( '#comment' ) || isDirty( '#field-summary' ) || isDirty( '#field-description' );

					if ( ! hasUnsavedContent ) {
						return;
					}

					event.preventDefault();
				} );

				document.getElementById( 'propertyform' )?.addEventListener( 'submit', function ( event ) {
					wpTrac.isSubmitting = true;
					setTimeout( function () {
						if ( event.defaultPrevented ) {
							wpTrac.isSubmitting = false;
						}
					}, 0 );
				} );
			}

			// Add custom buttons to the formatting toolbar.
			wpTrac.wikiToolbar();

			// Force 'Attachments' and 'Modify Ticket' to be shown.
			document.querySelector( '#attachments' )?.classList.remove( 'collapsed' );

			const modifyParent = document.getElementById( 'modify' )?.parentElement;
			modifyParent?.classList.remove( 'collapsed' );

			// Move the Add-Comment before Ticket Modify dialogue.
			const addComment = document.getElementById( 'trac-add-comment' );
			if ( addComment && modifyParent ) {
				modifyParent.before( addComment );
			}

			// Push live comment previews above 'Modify Ticket'.
			const ticketChange = document.getElementById( 'ticketchange' );
			if ( ticketChange && addComment ) {
				addComment.after( ticketChange );
			}

			// Toggle the security notice on component change, if rendered.
			const securityNotice = document.getElementById( 'wp-security-notice' );
			if ( securityNotice ) {
				document.getElementById( 'field-component' )?.addEventListener( 'change', function () {
					securityNotice.style.display = 'Security' === this.value ? '' : 'none';
				} );
			}

			// Prevent links inside a ticket or comment preview from opening in the same window.
			document.querySelectorAll( '.ticketdraft' ).forEach( ( draft ) => {
				draft.addEventListener( 'click', function ( event ) {
					const link = event.target.closest( 'a' );
					if ( link && draft.contains( link ) ) {
						event.preventDefault();
						window.open( link.getAttribute( 'href' ) );
					}
				} );
			} );

			// Allow action text inputs and select fields to be clicked directly.
			const actionContainer = document.getElementById( 'action' );
			if ( actionContainer ) {
				// Remove any jQuery click handlers Trac attached to action radios.
				if ( $ ) {
					$( actionContainer ).find( 'input[name=action]' ).off( 'click' );
				}

				actionContainer.querySelectorAll( 'input[type="text"], select' ).forEach( ( field ) => {
					field.disabled = false;
					field.setAttribute( 'tabindex', '-1' );
				} );

				// Hide action text inputs and select fields from keyboard, unless the corresponding action is focused.
				[ 'focusin', 'focusout' ].forEach( ( type ) => {
					actionContainer.addEventListener( type, ( { target } ) => {
						const isFocus = 'focusin' === type;
						const parent = target.parentElement;

						if ( isFocus && target.matches( 'input[type="text"], select' ) ) {
							parent?.querySelector( 'input[type="radio"]' )?.click();
						}

						if ( target.matches( 'input' ) ) {
							parent?.querySelectorAll( 'input[type="text"], select' ).forEach( ( el ) => {
								if ( isFocus ) {
									el.removeAttribute( 'tabindex' );
								} else {
									el.setAttribute( 'tabindex', '-1' );
								}
							} );
						}
					} );
				} );

				actionContainer.addEventListener( 'change', function ( event ) {
					if ( event.target.matches( 'input[type="radio"]' ) ) {
						const select = event.target.parentElement?.querySelector( 'select' );
						if ( select ) {
							select.disabled = false;
						}
					}
				} );
			}

			const propertyForm = document.getElementById( 'propertyform' );
			const milestone = document.getElementById( 'field-milestone' );

			if ( propertyForm ) {
				// Clear the milestone on wontfix, duplicate, worksforme, invalid.
				if ( milestone && ! milestone.disabled ) {
					propertyForm.addEventListener( 'submit', function () {
						const action = document.querySelector( 'input[name="action"]:checked' )?.value;
						const resolution = document.getElementById( 'action_resolve_resolve_resolution' )?.value;
						if ( 'duplicate' === action || ( 'resolve' === action && 'fixed' !== resolution ) ) {
							milestone.value = '';
						}
					} );
				}

				// Prevent marking a ticket as a duplicate of itself.
				propertyForm.addEventListener( 'submit', function ( event ) {
					const action = document.querySelector( 'input[name="action"]:checked' )?.value,
						currentTicket = parseInt(
							( document.querySelector( '.trac-id' )?.textContent || '' ).replace( '#', '' ),
							10
						),
						dupeInput = document.getElementById( 'action_dupe' ),
						duplicateTicket = parseInt( dupeInput?.value || '', 10 );

					if ( 'duplicate' === action && ( ! duplicateTicket || currentTicket === duplicateTicket ) ) {
						if ( dupeInput ) {
							dupeInput.value = '';
						}
						event.preventDefault();
						event.stopImmediatePropagation();
					}
				} );

				// capital_P_dangit()
				propertyForm.addEventListener( 'submit', function () {
					const summary = document.getElementById( 'field-summary' ),
						description = document.getElementById( 'field-description' ),
						comment = document.getElementById( 'comment' ),
						isNewTicket = wpTrac.isNewTicket();

					const fixP = function ( text ) {
						if ( ! text ) {
							return text;
						}
						return text.replace( /(^|[\s>(„"'\[\u2018\u201C]|&#8216;|&#8220;])Wordpress/g, '$1WordPress' );
					};

					if ( isNewTicket && summary?.value ) {
						summary.value = fixP( summary.value );
					}

					if ( isNewTicket && description?.value ) {
						description.value = fixP( description.value );
					}

					if ( comment?.value ) {
						comment.value = fixP( comment.value );
					}
				} );
			}

			// Add a 'Show only commits/attachments' view option to tickets.
			const commentsOnlyLabel = document.querySelector( 'label[for="trac-comments-only-toggle"]' );
			if ( commentsOnlyLabel ) {
				commentsOnlyLabel.textContent = 'Show only comment text';
			}

			const prefsForm = document.querySelector( 'form#prefs' );
			if ( prefsForm && prefsForm.querySelector( '#trac-comments-order' ) ) {
				prefsForm.insertAdjacentHTML(
					'beforeend',
					'<div><input type="checkbox" id="wp-trac-commits-only" /> <label for="wp-trac-commits-only">Show only commits/attachments</label></div>'
				);
			}

			document.getElementById( 'wp-trac-commits-only' )?.addEventListener( 'change', function () {
				document.querySelectorAll( 'div.change' ).forEach( ( change ) => {
					if ( ! this.checked ) {
						change.style.display = '';
						return;
					}

					const isCommit =
						change.querySelector( '.comment > p > a.changeset' ) &&
						change.querySelector( '.comment div.message p a.ticket' );
					const hasAttachment = Boolean( change.querySelector( 'li.trac-field-attachment' ) );

					change.style.display = isCommit || hasAttachment ? '' : 'none';
				} );
			} );

			// List commits between #ticket and #attachments.
			if ( content?.classList.contains( 'ticket' ) && ! document.querySelector( '#ticket.ticketdraft' ) ) {
				const commitChanges = Array.from( document.querySelectorAll( 'div.change' ) ).filter(
					( el ) =>
						el.querySelector( '.comment > p > a.changeset' ) &&
						el.querySelector( '.comment div.message p a.ticket' )
				);
				const commitsList = document.createElement( 'ul' );
				let commitCount = 0;

				commitChanges.forEach( ( el ) => {
					const comment = el.querySelector( '.comment' );
					const commitLi = document.createElement( 'li' );

					const changesetLink = comment?.querySelector( ':scope > p > a.changeset' )?.cloneNode( true );
					if ( changesetLink ) {
						commitLi.append(
							document.createTextNode( '[' ),
							changesetLink,
							document.createTextNode( '] ' )
						);
					}

					const rawMessage = ( comment?.querySelector( '.message > p' )?.textContent || '' ).trim();
					const firstLine = rawMessage.split( '\n' )[ 0 ].trim();
					const msgSpan = document.createElement( 'span' );
					msgSpan.textContent = firstLine + '…';
					commitLi.append( msgSpan );

					const author = el.querySelector( '.username' )?.dataset.username;
					if ( author ) {
						const authorLink = document.createElement( 'a' );
						authorLink.href = `https://profiles.wordpress.org/${ encodeURIComponent( author ) }/`;
						authorLink.textContent = `@${ author }`;
						commitLi.append( document.createTextNode( ' by\u00A0' ), authorLink );
					}

					const dateText = ( el.querySelector( '.time-ago' )?.textContent || '' ).trim();
					if ( dateText ) {
						commitLi.append( document.createTextNode( ' ' + dateText ) );
					}

					commitsList.append( commitLi );
					commitCount += 1;
				} );

				const commitsWrapper = document.createElement( 'div' );
				commitsWrapper.id = 'commits';
				commitsWrapper.className = 'collapsed';

				const heading = document.createElement( 'h3' );
				heading.className = 'foldable';
				heading.innerHTML = `<a href="#no0" id="no0">Commits <span class="trac-count">(${ commitCount })</span></a>`;

				const commitsBody = document.createElement( 'div' );
				commitsBody.className = 'commits';
				commitsBody.append( commitsList );

				commitsWrapper.append( heading, commitsBody );
				document.getElementById( 'ticket' )?.after( commitsWrapper );
			}

			// See $.fn.enableFolding().
			document.getElementById( 'no0' )?.addEventListener( 'click', function ( event ) {
				const div = this.closest( '#commits' );
				div.classList.toggle( 'collapsed' );
				if ( div.classList.contains( 'collapsed' ) ) {
					event.preventDefault();
				}
			} );

			// 'User Interface' preferences tab => 'Help Links' (and removes icons-only setting).
			const uitab = document.getElementById( 'tab_userinterface' );
			if ( uitab ) {
				if ( uitab.classList.contains( 'active' ) ) {
					uitab.textContent = 'Help Links';
					document.querySelector( 'input[name="ui.use_symbols"]' )?.closest( 'div.field' )?.remove();
				} else {
					const link = uitab.querySelector( 'a' );
					if ( link ) {
						link.textContent = 'Help Links';
					}
				}
			}

			if ( content?.classList.contains( 'search' ) ) {
				// Remove 'Wiki' and 'Milestone' from search.
				document.querySelectorAll( '#fullsearch #milestone, #fullsearch #wiki' ).forEach( ( input ) => {
					input.nextElementSibling?.remove();
					input.remove();
				} );

				// Offer to create a new ticket.
				content
					.querySelector( 'h1' )
					?.insertAdjacentHTML(
						'beforeend',
						`<span class="create-new-ticket button button-large button-primary"><a href="https://login.wordpress.org/?redirect_to=https://${ window.location.host }/newticket" rel="nofollow">Create a new ticket</a></span>`
					);
			}

			// Batch Modify should require a comment.
			const batchModComment = document.getElementById( 'batchmod_value_comment' );
			if ( batchModComment ) {
				batchModComment.required = true;
			}

			// Show the number of query results even on a single page; Trac only renders a heading when they paginate.
			if ( content?.classList.contains( 'query' ) && ! content.querySelector( 'h2.report-result' ) ) {
				const numResults = content.querySelectorAll( 'table.listing tbody tr' ).length;
				if ( numResults ) {
					document
						.querySelector( 'form#query' )
						?.insertAdjacentHTML(
							'afterend',
							`<h2 class="report-result">Results <span class="numresults">(${ numResults })</span></h2>`
						);
				}
			}

			// Hide the "arguments are missing" warning on report views for users who cannot edit the report to fix it.
			if (
				/^\/report\/\d/.test( window.location.pathname ) &&
				! content?.querySelector( '.buttons input[value="Edit report"]' )
			) {
				content?.querySelectorAll( '#warning.system-message' ).forEach( ( warning ) => {
					if ( /arguments are missing/i.test( warning.textContent ) ) {
						warning.style.display = 'none';
					}
				} );
			}

			// Demote the nav "Preferences" link to the footer.
			document
				.getElementById( 'altlinks' )
				?.insertAdjacentHTML(
					'afterbegin',
					'<a class="preferences-link" href="/prefs">Trac UI Preferences</a> '
				);

			// Prevent emoji in ticket text from being replaced with <img> tags.
			document
				.querySelectorAll( '#field-description, #comment, textarea[name="edited_comment"]' )
				.forEach( ( el ) => el.classList.add( 'wp-exclude-emoji' ) );

			// Remove wiki cruft from the home page.
			if ( '/' === window.location.pathname ) {
				document
					.querySelectorAll( '#pagepath, #ctxtnav, .trac-modifiedby, #altlinks h3, #altlinks ul' )
					.forEach( ( el ) => {
						el.style.display = 'none';
					} );
			}

			// bbPress/BuddyPress: move #metanav into a #subnav banner, like their sites' themes.
			if ( 'bbpress' === projectSlug || 'buddypress' === projectSlug ) {
				const items = document.querySelectorAll( '#metanav ul > li' );
				const banner = document.getElementById( 'banner' );
				if ( items.length && banner ) {
					const subnav = document.createElement( 'div' );
					subnav.id = 'subnav';
					subnav.innerHTML = '<div id="subnav-inner"><ul id="nav-user" class="menu"></ul></div>';
					subnav.querySelector( 'ul' ).append( ...items );
					banner.before( subnav );
				}
			}
		},

		/**
		 * Points the login and logout links at WordPress.org's login.
		 */
		updateAuthLinks() {
			const currentUrl = encodeURIComponent( window.location.href );

			document.querySelectorAll( '#metanav a[href="/login"]' ).forEach( ( a ) => {
				a.href = 'https://login.wordpress.org/?redirect_to=' + currentUrl;
				a.classList.add( 'login' );
			} );

			const logoutForm = document.querySelector( '#metanav form#logout' );
			if ( logoutForm ) {
				const logoutLink = document.createElement( 'a' );
				logoutLink.href = `https://login.wordpress.org/logout?redirect_to=${ currentUrl }`;
				logoutLink.textContent = 'Logout';
				logoutForm.replaceWith( logoutLink );
			}
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

			// The cells hold the username as plain text or inside Trac's a.trac-author link; textContent covers both.
			document.querySelectorAll( 'td[headers="h_reporter"], td[headers="h_owner"]' ).forEach( ( td ) => {
				const user = td.textContent.trim();
				if ( ! user ) {
					return; // e.g. an unassigned owner
				}
				const nice = sanitizeNicename( user );
				td.insertAdjacentHTML(
					'afterbegin',
					`<a href="https://profiles.wordpress.org/${ nice }/" data-nicename="${ nice }"><img class="avatar" src="https://wordpress.org/grav-redirect.php?user=${ nice }&s=48" srcset="https://wordpress.org/grav-redirect.php?user=${ nice }&s=96 2x" height="48" width="48" alt="${ nice }'s profile" decoding="async" /></a> `
				);
			} );
		},

		/**
		 * Asks the ticket reporter for feedback when the reporter-feedback keyword is set.
		 *
		 * Deliberately shows for closed tickets with a resolution other than 'fixed'.
		 */
		requestReporterFeedback() {
			const reporter = ( document.querySelector( 'td[headers="h_reporter"]' )?.textContent || '' ).trim();
			const keywords = document.querySelector( 'td[headers="h_keywords"]' )?.textContent || '';
			const resolution = ( document.querySelector( 'td[headers="h_resolution"]' )?.textContent || '' ).trim();

			if (
				! wpTrac.currentUser ||
				reporter !== wpTrac.currentUser ||
				! /\breporter-feedback\b/.test( keywords ) ||
				'fixed' === resolution
			) {
				return;
			}

			document.getElementById( 'ticketchange' )?.insertAdjacentHTML(
				'afterend',
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

			const form = document.querySelector( '#content.ticket form' );
			if ( ! form ) {
				return;
			}

			// "Are you in the right place?" notice.
			const securityHtml =
				'plugins' === projectSlug
					? `<strong>Do not report potential security vulnerabilities here.</strong><br />Please email
					<a class="mail-link" href="mailto:plugins@wordpress.org">plugins@wordpress.org</a>.`
					: `<strong>Do not report potential security vulnerabilities here.</strong><br />See the
					<a href="https://make.wordpress.org/core/handbook/testing/reporting-security-vulnerabilities/">Security FAQ</a>
					and visit the <a href="https://hackerone.com/wordpress">WordPress HackerOne program</a>.`;

			form.insertAdjacentHTML(
				'beforebegin',
				`<div class="wp-notice newticket-not-here">
				<p><strong>ARE YOU IN THE RIGHT PLACE?</strong></p>
				<p class="support"><span class="dashicons dashicons-editor-help"></span>
				<strong>This is not for support.</strong><br />Please try the <a href="${ supportLink }">support forums</a>.</p>
				<p class="security"><span class="dashicons dashicons-lock"></span> ${ securityHtml }</p>
				</div>`
			);

			// Bug-report instructions (core/bbPress/BuddyPress only).
			if ( [ 'core', 'bbpress', 'buddypress' ].includes( projectSlug ) ) {
				const gutenberg =
					'core' === projectSlug
						? '<li>Please create Gutenberg issues on the project’s GitHub <a href="https://github.com/WordPress/gutenberg/issues">issue tracker</a>.</li>'
						: '';
				form.insertAdjacentHTML(
					'beforebegin',
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
			if (
				document.querySelector( '#ticketbox.ticketdraft' ) &&
				'Security' === document.getElementById( 'field-component' )?.value
			) {
				document.querySelector( '#propertyform div.buttons' )?.insertAdjacentHTML(
					'beforebegin',
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
			document.querySelector( '#attachment div.buttons' )?.insertAdjacentHTML(
				'beforebegin',
				`<div class="wp-caution" id="wp-contributions-caution"><p>By contributing code to ${ projectName },
				you grant its use under the GNU General Public License v2 (or later).</p></div>`
			);
			const submitBtn = document.querySelector( '#attachment input[type="submit"][value="Add attachment"]' );
			if ( submitBtn ) {
				submitBtn.value = 'Agree and Upload';
			}
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

			document
				.querySelectorAll(
					'.description a.ext-link, .comment a.ext-link, .attachments a.ext-link, td.message.searchable a.ext-link'
				)
				.forEach( ( a ) => {
					if ( isWporgUrl( a.getAttribute( 'href' ) || '' ) ) {
						a.classList.remove( 'ext-link' );
						a.setAttribute( 'rel', 'ugc' );
					} else {
						a.setAttribute( 'rel', 'ugc nofollow' );
					}
				} );
		},

		// Link the current user's name in the header to their profile.
		linkHeaderUsername() {
			const el = document.querySelector( '#metanav .first' );
			if ( ! el ) {
				return;
			}
			const usernameText = el.textContent.trim();

			if ( usernameText.startsWith( 'logged in as ' ) ) {
				const username = usernameText.replace( 'logged in as ', '' ).trim();
				const link = document.createElement( 'a' );
				link.href = 'https://profiles.wordpress.org/' + encodeURIComponent( username ) + '/';
				link.textContent = username;

				el.textContent = 'logged in as ';
				el.append( link );
			}
		},

		// Allow 'Modify Ticket' to be shown even after a Trac preview tries to close it,
		// but only if it was already open.
		keepModifyTicketOpen() {
			let hadClass;
			const form = document.getElementById( 'propertyform' );

			if ( ! form || ! $ ) {
				return;
			}

			const content = document.getElementById( 'content' ),
				modify = document.getElementById( 'modify' )?.parentElement,
				action = form.getAttribute( 'action' ) || '';

			// Uses jQuery's global ajax events because Trac's internal live-preview runs via jQuery.ajax.
			$( document ).ajaxSend( function ( event, XMLHttpRequest, ajaxOptions ) {
				if ( 0 !== action.indexOf( ajaxOptions.url ) ) {
					return;
				}
				hadClass = modify?.classList.contains( 'collapsed' );
				// Prevent re-rendering of image previews and other changes from causing "jumps" while writing a comment.
				const changelogHeight = document.getElementById( 'changelog' )?.offsetHeight || 0;
				document.getElementById( 'changelog-height' )?.remove();
				document.head.insertAdjacentHTML(
					'beforeend',
					`<style id="changelog-height"> #changelog { height: ${ changelogHeight }px !important; } </style>`
				);
			} );
			$( document ).ajaxComplete( function ( event, XMLHttpRequest, ajaxOptions ) {
				if ( 0 !== action.indexOf( ajaxOptions.url ) ) {
					return;
				}
				if ( ! hadClass ) {
					modify?.classList.remove( 'collapsed' );
				}
				content?.dispatchEvent( new CustomEvent( 'wpTracPostPreview' ) );
				window.setTimeout( function () {
					document.getElementById( 'changelog-height' )?.remove();
				}, 200 );
			} );
		},

		// Add custom buttons to the formatting toolbar.
		// http://trac.edgewall.org/browser/tags/trac-1.0.9/trac/htdocs/js/wikitoolbar.js
		wikiToolbar() {
			function addButton( wikitoolbar, textarea, id, title, afterSelector, fn ) {
				const afterEl = wikitoolbar?.querySelector( afterSelector );
				if ( ! afterEl ) {
					return;
				}

				const button = document.createElement( 'a' );
				button.href = '#';
				button.id = id;
				button.title = title;
				button.tabIndex = 400;

				button.addEventListener( 'click', function ( event ) {
					event.preventDefault();
					if ( ! textarea.disabled && ! textarea.readOnly ) {
						try {
							fn();
						} catch {}
					}
				} );

				afterEl.after( button );
			}

			/**
			 * Encloses the selected text in a textarea with the given prefix and suffix.
			 *
			 * @param {HTMLTextAreaElement} textarea DOM textarea element.
			 * @param {string}              prefix   Text to prepend to selection.
			 * @param {string}              suffix   Text to append to selection.
			 */
			function encloseSelection( textarea, prefix, suffix ) {
				textarea.focus();

				const start = textarea.selectionStart;
				const end = textarea.selectionEnd;
				let selectedText = textarea.value.substring( start, end );

				if ( selectedText.endsWith( ' ' ) ) {
					selectedText = selectedText.slice( 0, -1 );
					suffix += ' ';
				}

				const replacement = prefix + selectedText + suffix;
				const caret = selectedText ? start + replacement.length : start + prefix.length;

				textarea.setRangeText( replacement, start, end, 'preserve' );
				textarea.setSelectionRange( caret, caret );
			}

			document.querySelectorAll( 'textarea.wikitext' ).forEach( ( textarea ) => {
				if ( 'function' !== typeof textarea.setRangeText ) {
					return;
				}

				const resizable = textarea.closest( 'div.trac-resizable' );
				const wikitoolbar = resizable?.parentElement?.querySelector( 'div.wikitoolbar' );

				addButton(
					wikitoolbar,
					textarea,
					'code-php',
					'PHP Code block: {{{#!php example }}}',
					// Trac 1.6 gives toolbar buttons a class, not an id.
					'.trac-wikitoolbar-code',
					function () {
						encloseSelection( textarea, '{{{#!php\n<?php\n', '\n}}}\n' );
					}
				);
			} );
		},

		// If we're not dealing with a trusted bug gardener:
		nonGardeners() {
			// Hide disabled fields (new ticket & ticket modify)
			document.querySelectorAll( '.trac-properties select[disabled]' ).forEach( ( select ) => {
				const td = select.closest( 'td' );
				if ( td ) {
					td.style.display = 'none';
					if ( td.previousElementSibling ) {
						td.previousElementSibling.style.display = 'none';
					}
				}
			} );

			const typeSelect = document.getElementById( 'field-type' );
			const versionSelect = document.getElementById( 'field-version' );
			const version = versionSelect ? parseFloat( versionSelect.value ) : NaN;

			// Remove task, or make a task ticket read only. This supports the ticket type being 'task' or 'task (blessed)'
			if ( typeSelect ) {
				const typeValue = typeSelect.value;
				if ( typeValue.includes( 'task' ) ) {
					const hiddenInput = document.createElement( 'input' );
					hiddenInput.type = 'hidden';
					hiddenInput.name = 'field_type';
					hiddenInput.value = typeValue;

					if ( typeSelect.parentElement ) {
						typeSelect.parentElement.style.verticalAlign = 'middle';
					}
					typeSelect.after( hiddenInput, document.createTextNode( ` ${ typeValue }` ) );
					typeSelect.remove();
				} else {
					typeSelect.querySelectorAll( 'option[value*="task"]' ).forEach( ( opt ) => opt.remove() );
				}
			}

			// Once a Version is set, remove newer versions.
			if ( version && versionSelect ) {
				versionSelect.querySelectorAll( 'option' ).forEach( ( opt ) => {
					const value = parseFloat( opt.value );
					if ( ! value || value > version ) {
						opt.remove();
					}
				} );
			}

			// Rename the "Submit changes" buttons
			document.querySelectorAll( 'input[type="submit"][value="Submit changes"]' ).forEach( ( input ) => {
				input.value = 'Add Comment';
			} );

			// Require a preview before a new ticket can be submitted.
			if ( '/newticket' === window.location.pathname && ! document.querySelector( '#ticketbox.ticketdraft' ) ) {
				const buttonsDiv = document.querySelector( '#propertyform div.buttons' );
				if ( buttonsDiv ) {
					buttonsDiv.innerHTML = '<input type="submit" name="preview" value="Continue to Preview" />';
				}
			}

			// Only gardeners may replace an existing attachment of the same name.
			document.querySelectorAll( '#attachment .options' ).forEach( ( el ) => el.remove() );
		},

		reports() {
			const popup = document.getElementById( 'report-popup' );
			if ( ! popup ) {
				return;
			}

			popup.addEventListener( 'change', function ( event ) {
				const select = event.target.closest( '.tickets-by-topic' );
				if ( ! select ) {
					return;
				}
				const topic = select.value;
				if ( ! topic ) {
					return;
				}
				window.location.href = ( select.dataset.location || '' ) + topic;
			} );

			document.getElementById( 'main' )?.append( popup );

			const headline = document.getElementById( 'headline' );
			let failed = false;

			document.querySelectorAll( '.open-ticket-report' ).forEach( ( trigger ) => {
				trigger.addEventListener( 'click', function ( event ) {
					// Allow opening the report on make.
					if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
						return;
					}

					// Calculate the correct position, even if the header size/etc changes.
					if ( headline ) {
						const rect = headline.getBoundingClientRect();
						popup.style.top = rect.top + window.scrollY + headline.offsetHeight + 'px';
					}

					if ( popup.children.length === 0 ) {
						fetch( 'https://make.wordpress.org/core/reports/?from-trac', {
							credentials: 'include',
						} )
							.then( ( res ) => {
								if ( ! res.ok ) {
									throw new Error( 'Network error' );
								}
								return res.text();
							} )
							.then( ( html ) => {
								const doc = new window.DOMParser().parseFromString( html, 'text/html' );
								doc.querySelectorAll( '.ticket-reports' ).forEach( ( el ) => popup.append( el ) );
								document.body.classList.add( 'ticket-reports-open' );
							} )
							.catch( () => {
								failed = true;
							} );
					} else {
						document.body.classList.toggle( 'ticket-reports-open' );
						event.preventDefault();
					}

					if ( ! failed ) {
						event.preventDefault();
					}
				} );
			} );

			popup.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( '.close' ) ) {
					event.preventDefault();
					document.body.classList.remove( 'ticket-reports-open' );
				}
			} );
		},

		redirectTicketsToProperTracker: ( function () {
			return {
				init() {
					const component = document.getElementById( 'field-component' );
					if ( ! component ) {
						return;
					}

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
							if ( component.value !== c ) {
								component.querySelector( `option[value="${ window.CSS.escape( c ) }"]` )?.remove();
							}
						}
					}

					// Show a notice when the component is selected.
					component.addEventListener( 'change', wpTrac.redirectTicketsToProperTracker.maybeShowNotice );

					// Trigger a warning on load, when the ticket is not closed.
					if ( ! wpTrac.isNewTicket() && ! document.getElementById( 'action_reopen' ) ) {
						wpTrac.redirectTicketsToProperTracker.maybeShowNotice();
					}

					document.getElementById( 'propertyform' )?.addEventListener( 'click', function ( event ) {
						const link = event.target.closest( '#new-tracker-ticket' );
						if ( ! link ) {
							return;
						}
						event.preventDefault();

						const href = link.getAttribute( 'href' ) || '';
						let summaryField = 'summary',
							descriptionField = 'description';

						// Trac (default) and GitHub are supported.
						if ( /github\.com/.test( href ) ) {
							summaryField = 'title';
							descriptionField = 'body';
						}

						const rawSummary = document.getElementById( 'field-summary' )?.value || '';
						const safeSummary = rawSummary.length > 255 ? rawSummary.slice( 0, 252 ) + '...' : rawSummary;

						const params = new URLSearchParams();
						params.set( summaryField, safeSummary );
						params.set( descriptionField, document.getElementById( 'field-description' )?.value || '' );

						const separator = href.includes( '?' ) ? '&' : '?';
						let url = href + separator + params.toString();

						if ( url.length > 1500 ) {
							params.set(
								descriptionField,
								"(Couldn't copy over your description as it was too long. Please paste it here. Your old window was not closed.)"
							);
							url = href + separator + params.toString();
							window.open( url );
						} else {
							wpTrac.isSubmitting = true;
							window.location.href = url;
						}
					} );
				},

				maybeShowNotice() {
					const component = document.getElementById( 'field-component' );
					if ( ! component ) {
						return;
					}

					// Reset prior component notice before collecting .wp-notice elements to toggle.
					document.querySelectorAll( '.wp-notice.component' ).forEach( ( el ) => el.remove() );

					const toggleElements = [
						...Array.from( document.querySelectorAll( 'input[name="attachment"]' ) )
							.map( ( el ) => el.parentElement )
							.filter( Boolean ),
						...document.querySelectorAll( '.ticketdraft, .wp-notice, div.buttons' ),
					];

					const setToggleVisible = ( visible ) => {
						toggleElements.forEach( ( el ) => {
							el.style.display = visible ? '' : 'none';
						} );
					};

					setToggleVisible( false );

					const selectedComponent = component.value;
					if ( ! ( selectedComponent in bugTrackerLocations ) ) {
						setToggleVisible( true );
						return;
					}

					const tracker = bugTrackerLocations[ selectedComponent ];

					// If the component (ie. Editor) allows bypassing the warning show the create buttons.
					if ( ! wpTrac.isNewTicket() || tracker.allow_bypass ) {
						setToggleVisible( true );
					}

					const labelText = escapeHtml( tracker.bug_text || selectedComponent );
					const trackerText = escapeHtml( tracker.tracker_text || tracker.tracker );

					document.querySelectorAll( 'div.buttons' ).forEach( ( buttonsDiv ) => {
						buttonsDiv.insertAdjacentHTML(
							'beforebegin',
							`<div class="wp-notice component">
							<p><strong>Tickets related to ${ labelText }</strong> should be filed on the
							<a href="${ tracker.tracker }">${ trackerText }</a></p>
							<p>Would you mind creating this ticket over there instead if appropriate?
							${
								tracker.enable_copy
									? `<a href="${ tracker.tracker }" id="new-tracker-ticket">Click here to copy your summary and description over</a>.`
									: ''
							}</p>
							${
								wpTrac.isNewTicket() && tracker.allow_bypass
									? `<p>If this isn't related to ${ labelText }, please continue to open this ticket here.</p>`
									: ''
							}
							</div>`
						);
					} );
				},
			};
		} )(),

		autocomplete: ( function () {
			let ticketParticipants = [],
				nonTicketParticipants = [],
				settings = {};

			return {
				init() {
					const comment = document.getElementById( 'comment' );
					if ( ! comment || ! $?.fn?.atwho ) {
						return;
					}

					if ( window.wpTracAutoCompleteUsers ) {
						settings = window.wpTracAutoCompleteUsers;
					}

					this.initTicketParticipants();
					this.initNonTicketParticipants();

					// Adjusts the query so it doesn't search for 'achment' in case Ryan enters too many characters.
					const replacer = function ( query ) {
						return query.replace( /^(achment|achmen|achme|achm|ach|ac|a)/g, '' );
					};

					// jquery.atwho requires a jQuery collection.
					$( comment )
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

					const lowerQuery = query.toLowerCase();
					const results = nonTicketParticipants
						.filter( ( value ) => value.toLowerCase().startsWith( lowerQuery ) )
						.map( ( value ) => ( { name: value } ) );

					callback( results );
				},

				filterTicketParticipants( query ) {
					// Bail out if the query is empty.
					if ( '' === query ) {
						return ticketParticipants;
					}

					const lowerQuery = query.toLowerCase();
					return ticketParticipants.filter( ( value ) => value.toLowerCase().startsWith( lowerQuery ) );
				},

				initTicketParticipants() {
					const users = new Set();
					const excludeSet = new Set( Array.isArray( settings.exclude ) ? settings.exclude : [] );

					// Most recent should show up first.
					Array.from( document.querySelectorAll( '.change .username' ) )
						.reverse()
						.forEach( ( el ) => {
							let username = el.dataset.username;
							const nicename = el.dataset.nicename;

							// Override the username with the nicename if it differs by more than just case (ie. spaces, etc)
							if (
								username &&
								nicename &&
								username.toLowerCase() !== nicename.toLowerCase() &&
								wpTrac.currentUser !== username
							) {
								username = nicename;
							}

							if ( typeof username !== 'undefined' && ! excludeSet.has( username ) ) {
								users.add( username );
							}
						} );

					// Add ticket reporter.
					const reporterTd = document.querySelector( '#ticket td[headers="h_reporter"]' );
					let ticketReporter = ( reporterTd?.textContent || '' ).trim();
					const ticketReporterNicename = reporterTd?.querySelector( 'a' )?.dataset.nicename;

					// Override the username with the nicename if it differs by more than just case (ie. spaces, etc)
					if (
						ticketReporter &&
						ticketReporterNicename &&
						ticketReporter !== wpTrac.currentUser &&
						ticketReporter.toLowerCase() !== ticketReporterNicename.toLowerCase()
					) {
						ticketReporter = ticketReporterNicename;
					}

					if ( ticketReporter && ! excludeSet.has( ticketReporter ) ) {
						users.add( ticketReporter );
					}

					// Exclude current user.
					if ( wpTrac.currentUser ) {
						users.delete( wpTrac.currentUser );
					}

					ticketParticipants = Array.from( users );
				},

				getTicketParticipants() {
					return ticketParticipants;
				},

				addTicketParticipant( ticketParticipant ) {
					if ( ! ticketParticipants.includes( ticketParticipant ) ) {
						ticketParticipants.push( ticketParticipant );
					}
				},

				initNonTicketParticipants() {
					let users = [];

					if ( Array.isArray( settings.include ) ) {
						settings.include.forEach( ( username ) => {
							if ( ! users.includes( username ) && ! ticketParticipants.includes( username ) ) {
								users.push( username );
							}
						} );
					}

					// Exclude current user.
					if ( wpTrac.currentUser ) {
						users = users.filter( ( user ) => user !== wpTrac.currentUser );
					}

					nonTicketParticipants = users;
				},

				getNonTicketParticipants() {
					return nonTicketParticipants;
				},

				addNonTicketParticipant( nonTicketParticipant ) {
					if ( ! nonTicketParticipants.includes( nonTicketParticipant ) ) {
						nonTicketParticipants.push( nonTicketParticipant );
					}
				},

				getAttachments() {
					// Most recent should show up first.
					return Array.from( document.querySelectorAll( 'dl.attachments dt' ) )
						.reverse()
						.map( ( dt ) => ( {
							// Rendered by displayTpl as markup; matching uses `name`.
							display: escapeHtml( dt.textContent.replace( /\n/g, '' ) ),
							name: ( dt.querySelector( 'a[title="View attachment"]' )?.textContent || '' ).replace(
								/\n/g,
								''
							),
						} ) );
				},
			};
		} )(),

		workflow: ( function () {
			let keywords = [],
				originalKeywords = [];
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
					'dev-feedback': 'dev-reviewed',
					'has-privacy-review': 'needs-privacy-review',
					'needs-privacy-review': 'has-privacy-review',
					'has-copy-review': 'needs-copy-review',
					'needs-copy-review': 'has-copy-review',
					'has-screenshots': 'needs-screenshots',
					'needs-screenshots': 'has-screenshots',
				};

			/**
			 * Builds a keyword badge element with an associated remove button.
			 *
			 * @param {string} keyword Keyword name to display.
			 * @return {HTMLSpanElement} The constructed span element.
			 */
			function keywordSpan( keyword ) {
				const span = document.createElement( 'span' );
				span.dataset.keyword = keyword;

				const button = document.createElement( 'button' );
				button.type = 'button';
				button.className = 'keyword-button-remove dashicons dashicons-dismiss';
				button.setAttribute( 'aria-label', `Remove ${ keyword } keyword` );

				span.append( button, document.createTextNode( keyword ) );
				return span;
			}

			return {
				init() {
					elements.hiddenEl = document.getElementById( 'field-keywords' );
					if ( ! elements.hiddenEl ) {
						return;
					}
					elements.hiddenEl.setAttribute( 'aria-label', 'Manual keywords' );

					// Attach change event handler on the field-keywords input.
					elements.hiddenEl.addEventListener( 'change', wpTrac.workflow.populate );

					// Generate the workflow template.
					wpTrac.workflow.template();

					// Load up the initial keywords and the dropdown.
					wpTrac.workflow.populate();

					// Save these for later.
					originalKeywords = [ ...keywords ];

					// Catch the submit to see if keywords were simply reordered.
					elements.hiddenEl.closest( 'form' )?.addEventListener( 'submit', wpTrac.workflow.submit );

					const editKeywordsBtn = document.getElementById( 'edit-keywords' );

					// Keyword removal.
					elements.bin.addEventListener( 'click', function ( event ) {
						const removeBtn = event.target.closest( '.keyword-button-remove' );
						if ( ! removeBtn ) {
							return;
						}
						wpTrac.workflow.removeKeyword( removeBtn.parentElement );

						// Move focus to the Manual keyword button to avoid focus loss on keyword removal.
						if ( editKeywordsBtn ) {
							editKeywordsBtn.classList.add( 'hide-programmatic-focus' );
							editKeywordsBtn.focus();
							editKeywordsBtn.addEventListener(
								'blur',
								function () {
									this.classList.remove( 'hide-programmatic-focus' );
								},
								{ once: true }
							);
						}
					} );

					// Keyword adds.
					const keywordAdd = document.getElementById( 'keyword-add' );
					const handleKeywordAdd = function ( e ) {
						if ( e.type === 'keydown' ) {
							if ( e.key === 'Enter' ) {
								e.stopPropagation();
								e.preventDefault();
							} else {
								return;
							}
						}
						wpTrac.workflow.addKeyword( this.value );
						this.value = '';
					};
					keywordAdd?.addEventListener( 'change', handleKeywordAdd );
					keywordAdd?.addEventListener( 'keydown', handleKeywordAdd );

					// Manual keyword button.
					editKeywordsBtn?.addEventListener( 'click', function () {
						const isVisible = window.getComputedStyle( elements.hiddenEl ).display !== 'none';
						if ( isVisible ) {
							elements.hiddenEl.style.display = 'none';
							this.setAttribute( 'aria-expanded', 'false' );
							return;
						}

						this.setAttribute( 'aria-expanded', 'true' );
						elements.hiddenEl.style.display = 'inline-block';
						elements.hiddenEl.focus();
					} );

					// Handle keyboard interaction on the field-keywords field.
					elements.hiddenEl.addEventListener( 'keydown', function ( event ) {
						if ( event.key === 'Enter' || event.key === 'Escape' ) {
							event.preventDefault();
							editKeywordsBtn?.setAttribute( 'aria-expanded', 'false' );
							editKeywordsBtn?.focus();
							elements.hiddenEl.style.display = 'none';
							wpTrac.workflow.populate();
						}
					} );
				},

				// Generates the workflow template.
				template() {
					const container = elements.hiddenEl.parentElement;
					const prev = container.previousElementSibling;

					// Necessary to keep everything in line.
					const labelWidth = prev ? prev.getBoundingClientRect().width : 0;

					// Rearrange the table to suit our needs.
					prev?.remove();
					container.setAttribute( 'colspan', '2' );
					container.classList.add( 'has-js' );

					const table = container.closest( 'table' );
					if ( table ) {
						table.style.tableLayout = 'fixed';
					}

					// If the owner field exists, then we're on /newticket. Remove it.
					const ownerRow = document.getElementById( 'field-owner' )?.closest( 'tr' );
					if ( ownerRow ) {
						ownerRow.style.display = 'none';
					}

					let html = '<div>';
					html += `<label id="keyword-label" for="keyword-add" style="width:${ labelWidth }px">Workflow Keywords:</label>`;
					html += '<select id="keyword-add"><option value=""> - Add - </option></select>';
					html += `<button type="button" id="edit-keywords" aria-label="Manual keyword" aria-expanded="false">Manual</button>`;
					html += ` <a href="https://make.wordpress.org/core/handbook/contribute/trac/keywords/" target="_blank" rel="noopener noreferrer" aria-label="Keywords documentation" title="Keywords documentation">ℹ</a></div>`;
					html += '<div id="keyword-bin"></div>';

					container.insertAdjacentHTML( 'afterbegin', html );
					elements.bin = document.getElementById( 'keyword-bin' );
				},

				// Populates the keywords and dropdown.
				populate() {
					// For repopulation. Starting over.
					if ( elements.bin.querySelector( 'span' ) ) {
						elements.bin.replaceChildren();
					}

					// Replace commas, collapse spaces, trim, then split by space.
					const rawKeywords = elements.hiddenEl.value.replace( /,/g, ' ' ).replace( /\s+/g, ' ' ).trim();
					keywords = rawKeywords ? rawKeywords.split( ' ' ) : [];
					// Put our cleaned up version back into the hidden field.
					elements.hiddenEl.value = keywords.join( ' ' );

					// If we have a non-empty keyword, let's go through the process of adding the spans.
					if ( keywords.length ) {
						const binFragment = document.createDocumentFragment();
						keywords.forEach( function ( v ) {
							const span = keywordSpan( v );
							if ( v in coreKeywordList ) {
								span.title = coreKeywordList[ v ];
							}
							binFragment.append( span );
						} );
						elements.bin.append( binFragment );
					}

					// Populate the dropdown.
					if ( ! elements.add ) {
						elements.add = document.getElementById( 'keyword-add' );
					}
					if ( elements.add ) {
						elements.add.length = 1;
					}

					const optionsFragment = document.createDocumentFragment();
					Object.keys( coreKeywordList ).forEach( ( k ) => {
						// Don't show special (permission-based) ones.
						if ( ! wpTrac.gardener && gardenerKeywordList.includes( k ) ) {
							return;
						}
						// Don't show workflow keywords such as 'reporter-feedback' for new ticket.
						if ( wpTrac.isNewTicket() && hideFromNewTickets.includes( k ) ) {
							return;
						}

						const option = document.createElement( 'option' );
						option.value = k;
						if ( keywords.includes( k ) ) {
							option.disabled = true;
							option.textContent = '* ' + k;
						} else {
							option.textContent = k;
						}
						optionsFragment.append( option );
					} );
					elements.add.append( optionsFragment );
				},

				// Add a keyword. Takes a sanitized string.
				addKeyword( keyword ) {
					if ( ! keyword || keywords.includes( keyword ) ) {
						return;
					}

					let title = '';
					keywords.push( keyword );

					// Update the dropdown. Core keywords also get a title attribute with their description.
					if ( keyword in coreKeywordList ) {
						const opt = elements.add.querySelector( `option[value="${ window.CSS.escape( keyword ) }"]` );
						if ( opt ) {
							opt.disabled = true;
							opt.textContent = '* ' + keyword;
						}
						title = coreKeywordList[ keyword ];
					}

					// Remove the mutually-exclusive counterpart, if any.
					if ( Object.prototype.hasOwnProperty.call( exclusiveKeywords, keyword ) ) {
						wpTrac.workflow.removeKeyword( exclusiveKeywords[ keyword ] );
					}

					// Add it to the bin, and refresh the hidden input.
					const span = keywordSpan( keyword );
					if ( title ) {
						span.title = title;
					}
					elements.bin.append( span );
					elements.hiddenEl.value = keywords.join( ' ' );
				},

				// Remove a keyword. Takes a DOM element (or jQuery object) in the bin, or a sanitized keyword string.
				removeKeyword( object ) {
					let keyword, el;

					if ( typeof object === 'string' ) {
						keyword = object;
						el = elements.bin.querySelector( `span[data-keyword="${ window.CSS.escape( keyword ) }"]` );
						if ( ! el ) {
							return;
						}
					} else {
						el = object?.jquery ? object[ 0 ] : object;
						if ( ! el ) {
							return;
						}
						keyword = el.dataset.keyword;
					}

					keywords = keywords.filter( ( v ) => v !== keyword );

					// Update the core keyword dropdown.
					if ( keyword in coreKeywordList ) {
						const opt = elements.add.querySelector( `option[value="${ window.CSS.escape( keyword ) }"]` );
						if ( opt ) {
							opt.disabled = false;
							opt.textContent = keyword;
						}
					}
					elements.hiddenEl.value = keywords.join( ' ' );
					el.remove();
				},

				// Check on submit that we're not just re-ordering keywords.
				// Otherwise, Trac flips out and adds a useless 'Keywords changed from X to X' marker.
				submit() {
					if ( ! elements.hiddenEl || keywords.length !== originalKeywords.length ) {
						return;
					}

					const testKeywords = keywords.filter( ( v ) => ! originalKeywords.includes( v ) );

					// If the difference has no length, restore original keyword order to prevent Trac change noise.
					if ( ! testKeywords.length ) {
						elements.hiddenEl.value = originalKeywords.join( ' ' );
					}
				},
			};
		} )(),

		focuses: ( function () {
			let field, container, focuses, originalFocuses;

			function init() {
				field = document.getElementById( 'field-focuses' );
				if ( ! field ) {
					return;
				}
				if ( ! document.getElementById( 'field-owner' ) ) {
					document.querySelector( 'label[for="field-focuses"]' )?.parentElement?.remove();
				}

				container = field.parentElement;
				container.id = 'focuses';
				if ( container.getAttribute( 'colspan' ) !== '3' ) {
					container.setAttribute( 'colspan', '2' );
				}
				field.style.display = 'none';

				const rawFocuses = field.value.replace( /,/g, ' ' ).replace( / +/g, ' ' ).trim();
				focuses = rawFocuses.length === 0 ? [] : rawFocuses.split( ' ' );
				originalFocuses = [ ...focuses ];

				const fieldset = document.createElement( 'fieldset' );
				fieldset.id = 'fieldset-focuses';

				const legend = document.createElement( 'legend' );
				legend.className = 'core-focuses-legend';
				legend.textContent = 'Contributor Focuses:';

				const ul = document.createElement( 'ul' );
				Object.entries( coreFocusesList ).forEach( ( [ focus, description ] ) => {
					let ariaPressed = 'false';
					let classes = focus.replace( ' ', '-' );
					if ( focuses.includes( focus ) ) {
						classes += ' active';
						ariaPressed = 'true';
					}

					const li = document.createElement( 'li' );
					li.dataset.focus = focus;
					li.title = description;
					li.className = classes;
					li.innerHTML = `<button type="button" class="core-focuses-button" aria-pressed="${ ariaPressed }">${
						focus === 'administration' ? 'admin' : focus
					}</button>`;

					ul.append( li );
				} );

				fieldset.append( legend, ul );
				container.append( fieldset );

				container.addEventListener( 'click', function ( event ) {
					const button = event.target.closest( '.core-focuses-button' );
					if ( button && container.contains( button ) ) {
						addRemove.call( button );
					}
				} );
				container.closest( 'form' )?.addEventListener( 'submit', submit );
				document.getElementById( 'field-component' )?.addEventListener( 'change', componentSync );
			}

			function addRemove() {
				const focusLi = this.parentElement;
				if ( focusLi.classList.contains( 'active' ) ) {
					remove( focusLi );
				} else {
					add( focusLi );
				}
			}

			function add( focus ) {
				const focusLi = typeof focus === 'string' ? container.querySelector( 'li.' + focus ) : focus;
				if ( ! focusLi ) {
					return;
				}
				focusLi.classList.add( 'active' );
				focusLi.querySelector( '.core-focuses-button' )?.setAttribute( 'aria-pressed', 'true' );
				if ( ! focuses.includes( focusLi.dataset.focus ) ) {
					focuses.push( focusLi.dataset.focus );
				}
				updateField();
			}

			function remove( focusLi ) {
				if ( ! focusLi ) {
					return;
				}
				focusLi.classList.remove( 'active' );
				focusLi.querySelector( '.core-focuses-button' )?.setAttribute( 'aria-pressed', 'false' );
				const removedFocus = focusLi.dataset.focus;
				focuses = focuses.filter( ( value ) => value !== removedFocus );
				updateField();
			}

			function updateField() {
				const orderedFocuses = Object.keys( coreFocusesList ).filter( ( focus ) => focuses.includes( focus ) );
				field.value = orderedFocuses.join( ', ' );
			}

			function componentSync() {
				const component = this.value;
				if ( component === 'Network Admin' || component === 'Networks and Sites' ) {
					add( 'multisite' );
				}
			}

			function submit() {
				if ( focuses.length !== originalFocuses.length ) {
					return;
				}

				const testFocuses = focuses.filter( ( v ) => ! originalFocuses.includes( v ) );

				// If the difference has no length, then restore to the original order.
				if ( ! testFocuses.length ) {
					field.value = originalFocuses.join( ', ' );
				}
			}

			return {
				init,
			};
		} )(),

		notifications: ( function () {
			let notifications, endpoint, star, _ticket, _nonce;

			function init( settings ) {
				onReady( hideCcField );
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
				onReady( reportInit );
			}

			function hideCcField() {
				const content = document.getElementById( 'content' );
				if ( ! content ) {
					return;
				}
				if ( content.classList.contains( 'query' ) ) {
					document
						.querySelectorAll( 'table.trac-clause tr.actions option[value="cc"]' )
						.forEach( ( el ) => el.remove() );
					document
						.querySelectorAll( '#columns input[type="checkbox"][name="col"][value="cc"]' )
						.forEach( ( el ) => el.parentElement?.remove() );
				}
				if ( content.classList.contains( 'ticket' ) ) {
					// Remove the CC field in case the BlackMagic plugin didn't.
					const ccWrapper = document.getElementById( 'field-cc' )?.parentElement?.parentElement;
					ccWrapper?.previousElementSibling?.remove();
					ccWrapper?.remove();
					hideCcComments();
					content.addEventListener( 'wpTracPostPreview', hideCcComments );
				}
			}

			function hideCcComments() {
				document.querySelectorAll( '#changelog div.change' ).forEach( ( change ) => {
					const ccLi = change.querySelector( 'li.trac-field-cc' );
					if ( ! ccLi ) {
						return;
					}
					const changes = change.querySelector( ':scope > ul.changes' );
					if ( ! changes ) {
						return;
					}

					/*
					 * Three possibilities:
					 * 1. The comment is just a single CC (hide the whole comment)
					 * 2. The comment is a CC plus a comment (hide the CC line)
					 * 3. The comment contains multiple property changes (hide only the CC line)
					 */
					const liChildren = changes.querySelectorAll( ':scope > li' );
					if ( liChildren.length === 1 ) {
						if ( ! change.querySelector( ':scope > div.comment' ) ) {
							change.style.display = 'none';
						} else {
							changes.style.display = 'none';
						}
					} else {
						changes.querySelectorAll( ':scope > li.trac-field-cc' ).forEach( ( li ) => {
							li.style.display = 'none';
						} );
					}
				} );
			}

			function ticketInit( ticket ) {
				fetch( `${ endpoint }?trac-notifications=${ encodeURIComponent( ticket ) }`, {
					credentials: 'include',
				} )
					.then( ( res ) => res.json() )
					.then( ( data ) => {
						if ( data.success ) {
							onReady( () => {
								render( data.data[ 'notifications-box' ] );
								if ( data.data.maintainers ) {
									maintainerLabels( data.data.maintainers );
								}
							} );

							if ( data.data.nonce ) {
								_nonce = data.data.nonce;
							}
						}
					} )
					.catch( () => {} );
			}

			function maintainerLabels( maintainers ) {
				const labels = {};
				const componentName = (
					document.querySelector( 'td[headers="h_component"]' )?.textContent || ''
				).trim();
				maintainers.forEach( ( maintainer ) => {
					labels[ maintainer ] = {
						text: 'Component Maintainer',
						title: `@${ maintainer } maintains the ${ componentName } component`,
					};
				} );
				wpTrac.showContributorLabels( labels );
			}

			function render( data ) {
				document.getElementById( 'propertyform' )?.insertAdjacentHTML( 'beforebegin', data );
				notifications = document.getElementById( 'notifications' );
				if ( ! notifications ) {
					return;
				}

				notifications.addEventListener( 'click', function ( event ) {
					if ( event.target.closest( '.watch-this-ticket' ) ) {
						event.preventDefault();
						subscribe();
					} else if ( event.target.closest( '.watching-ticket' ) ) {
						event.preventDefault();
						unsubscribe();
					} else if ( event.target.closest( '.block-notifications' ) ) {
						event.preventDefault();
						block();
					} else if ( event.target.closest( '.unblock-notifications' ) ) {
						event.preventDefault();
						unblock();
					}
				} );

				const isSubscribed = notifications.classList.contains( 'subscribed' );
				document
					.querySelector( '#ticket.trac-content > h2' )
					?.insertAdjacentHTML(
						'afterbegin',
						`<div class="ticket-star dashicons dashicons-star-${
							isSubscribed ? 'filled' : 'empty'
						}" title="Watch/unwatch this ticket"></div>`
					);

				star = document.querySelector( '.ticket-star' );
				star?.addEventListener( 'click', function () {
					if ( this.classList.contains( 'dashicons-star-empty' ) ) {
						subscribe();
					} else {
						unsubscribe();
					}
				} );

				document.querySelectorAll( '.grid-toggle' ).forEach( ( toggle ) => {
					toggle.addEventListener( 'click', function ( event ) {
						const link = event.target.closest( 'a' );
						if ( ! link ) {
							return;
						}
						event.preventDefault();
						const names = link.classList.contains( 'names' );
						notifications.classList.toggle( 'show-usernames', names );
						document.cookie =
							'wp_trac_ngrid=' +
							( names ? 1 : 0 ) +
							';max-age=31557600;domain=.wordpress.org;path=/;SameSite=Lax;Secure';
					} );
				} );

				// Trac notification control is broken due to the Trac upgrade to 1.2
				notifications
					.querySelectorAll(
						'p.receiving-notifications, p.receiving-notifications-because, p.not-receiving-notifications, .preferences'
					)
					.forEach( ( el ) => {
						el.style.display = 'none';
					} );
			}

			function save( action, ticket, nonce, isRetry = false ) {
				ticket = ticket || _ticket;
				nonce = nonce || _nonce || '';
				const body = new URLSearchParams( {
					'trac-ticket-sub': ticket,
					action,
					nonce,
				} );

				fetch( endpoint, {
					method: 'POST',
					credentials: 'include',
					body,
				} ).catch( () => {
					if ( ! isRetry ) {
						setTimeout( () => {
							save( action, ticket, nonce, true );
						}, 1000 );
					}
				} );
			}

			function toggleStarClasses( el ) {
				if ( ! el ) {
					return;
				}
				el.classList.toggle( 'dashicons-star-empty' );
				el.classList.toggle( 'dashicons-star-filled' );
			}

			function subscribe() {
				save( 'subscribe' );
				notifications.classList.remove( 'blocked' );
				notifications.classList.add( 'subscribed' );
				toggleStarClasses( star );
				if ( notifications.classList.contains( 'receiving' ) ) {
					notifications.classList.add( 'block' );
				}
				changeCount( 1 );
			}

			function unsubscribe() {
				save( 'unsubscribe' );
				notifications.classList.remove( 'subscribed' );
				toggleStarClasses( star );
				if ( notifications.classList.contains( 'receiving' ) ) {
					notifications.classList.add( 'block' );
				}
				changeCount( -1 );
			}

			function changeCount( delta ) {
				const countEl = notifications.querySelector( '.count' );
				const count = Number.parseInt( countEl?.textContent || '', 10 ) || 0;
				count += delta;
				if ( countEl ) {
					countEl.textContent = count > 0 ? String( count ) : '';
				}
				notifications.classList.toggle( 'count-0', count <= 0 );
				notifications.classList.toggle( 'count-1', count === 1 );
			}

			function block() {
				save( 'block' );
				notifications.classList.remove( 'block' );
				notifications.classList.add( 'blocked' );
			}

			function unblock() {
				save( 'unblock' );
				notifications.classList.remove( 'blocked' );
				notifications.classList.add( 'block' );
			}

			function reportInit() {
				const tickets = [],
					stars = [],
					cells = document.querySelectorAll( 'table.listing td.Stars' );

				if ( cells.length === 0 ) {
					return;
				}

				cells.forEach( ( cell ) => {
					const countSpan = document.createElement( 'span' );
					countSpan.className = 'count';
					while ( cell.firstChild ) {
						countSpan.append( cell.firstChild );
					}
					cell.append( countSpan );

					const ticketLink = cell.closest( 'tr' )?.querySelector( 'td.ticket a' );
					const ticket = parseInt( ( ticketLink?.textContent || '' ).replace( '#', '' ), 10 );
					tickets.push( ticket );

					const reportStar = document.createElement( 'div' );
					reportStar.className = 'dashicons dashicons-star-empty loading trac-report-star';
					reportStar.dataset.ticket = String( ticket );

					cell.append( document.createTextNode( ' ' ), reportStar );
					stars.push( reportStar );
				} );

				const body = new URLSearchParams();
				body.set( 'trac-ticket-subs', 'true' );
				tickets.forEach( ( t ) => body.append( 'tickets[]', String( t ) ) );

				fetch( endpoint, {
					method: 'POST',
					credentials: 'include',
					body,
				} )
					.then( ( res ) => res.json() )
					.then( ( data ) => {
						if ( ! data.success ) {
							return;
						}

						if ( data.data.nonce ) {
							_nonce = data.data.nonce;
						}

						const subscribedSet = new Set( data.data.tickets || [] );

						stars.forEach( ( reportStar ) => {
							const ticketId = parseInt( reportStar.dataset.ticket, 10 );
							if ( subscribedSet.has( ticketId ) ) {
								toggleStarClasses( reportStar );
							}
							reportStar.classList.remove( 'loading' );
						} );

						document.querySelectorAll( 'table.listing' ).forEach( ( table ) => {
							table.addEventListener( 'click', function ( event ) {
								const clickedStar = event.target.closest( '.trac-report-star' );
								if ( ! clickedStar || ! table.contains( clickedStar ) ) {
									return;
								}
								toggleStarClasses( clickedStar );
								const action = clickedStar.classList.contains( 'dashicons-star-filled' )
									? 'subscribe'
									: 'unsubscribe';
								const delta = 'subscribe' === action ? 1 : -1;
								save( action, parseInt( clickedStar.dataset.ticket, 10 ) );

								const prev = clickedStar.previousElementSibling;
								if ( prev ) {
									const count = Number.parseInt( prev.textContent, 10 ) || 0;
									count += delta;
									prev.textContent = count ? String( count ) : '';
								}
							} );
						} );
					} )
					.catch( () => {} )
					.finally( () => {
						stars.forEach( ( s ) => s.classList.remove( 'loading' ) );
					} );
			}

			return {
				init,
			};
		} )(),

		githubPRs: ( function () {
			const apiEndpoint = 'https://api.wordpress.org/dotorg/trac/pr/',
				authenticated = Boolean( window.wpTracCurrentUser && window.wpTracCurrentUser !== 'anonymous' );
			let trac = false,
				ticket = 0,
				primaryGitRepo,
				primaryGitRepoDesc,
				container;

			function init() {
				const classList = document.body.classList;

				if ( classList.contains( 'core' ) ) {
					trac = 'core';
					primaryGitRepo = 'WordPress/wordpress-develop';
					primaryGitRepoDesc = 'WordPress GitHub mirror';
				} else if ( classList.contains( 'meta' ) ) {
					trac = 'meta';
					primaryGitRepo = 'WordPress/wordpress.org';
					primaryGitRepoDesc = 'WordPress.org Meta GitHub mirror';
				} else if ( classList.contains( 'bbpress' ) ) {
					trac = 'bbpress';
					primaryGitRepo = 'bbpress/bbPress';
					primaryGitRepoDesc = 'bbPress GitHub mirror';
				} else if ( classList.contains( 'buddypress' ) ) {
					trac = 'buddypress';
					primaryGitRepo = 'buddypress/buddypress';
					primaryGitRepoDesc = 'BuddyPress GitHub mirror';
				}

				if ( ! trac ) {
					return;
				}

				// Add ability to include GitHub tickets into a 'my-patches' report.
				// "Just" include a variable called '$GITHUBTICKETS' in the Query.
				const warning = Array.from( document.querySelectorAll( '#warning.system-message' ) ).find( ( el ) =>
					el.textContent.includes( 'GITHUBTICKETS' )
				);
				if ( warning ) {
					renderReportLoadGitHubTickets( warning );
				}

				// This seems to be the easiest place to find the current Ticket ID..
				const canonicalHref = document.querySelector( 'link[rel="canonical"]' )?.href;
				ticket = canonicalHref?.match( /\/ticket\/(\d+)(?:[?#]|$)/ )?.[ 1 ];
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
				document.querySelectorAll( 'img[src*="i0.wp.com/github.com/"]' ).forEach( ( img ) => {
					const parentLink = img.parentElement?.tagName === 'A' ? img.parentElement : null;
					img.removeAttribute( 'crossorigin' ); // We trust GitHub for these images.
					img.src = img.src.replace( /i0\.wp\.com\//, '' );
					img.alt = img.alt.replace( /i0\.wp\.com\//, '' );
					img.title = img.title.replace( /i0\.wp\.com\//, '' );
					if ( parentLink ) {
						parentLink.href = parentLink.href.replace( /i0\.wp\.com\//, '' );
					}
				} );
			}

			function fetchPRs() {
				if ( ! container ) {
					return;
				}
				const params = new URLSearchParams( { trac, ticket } );
				if ( authenticated ) {
					params.set( 'authenticated', '1' );

					const timelines = document.querySelectorAll( '#changelog a.timeline' );
					if ( timelines.length ) {
						const from = new URL( timelines[ timelines.length - 1 ].href ).searchParams.get( 'from' );
						if ( from ) {
							params.set( '_lastmod', from );
						}
					}
				}

				fetch( `${ apiEndpoint }?${ params }` )
					.then( ( res ) => {
						if ( ! res.ok ) {
							throw new Error( 'Failed to load PRs' );
						}
						return res.json();
					} )
					.then( ( data ) => {
						// Update the number
						const countWrapper = container.querySelector( 'h3 .trac-count' );
						if ( countWrapper ) {
							countWrapper.classList.remove( 'hidden' );
							const countSpan = countWrapper.querySelector( 'span' );
							if ( countSpan ) {
								countSpan.textContent = String( data.length );
							}
						}

						const prContainer = container.querySelector( '.pull-requests' );
						if ( data.length ) {
							// Remove the placeholder.
							prContainer.querySelector( '.loading' )?.remove();

							// Render the PRs
							data.forEach( ( pr ) => renderPR( prContainer, pr ) );
						} else {
							// Change the loading placeholder
							const loadingDiv = prContainer.querySelector( '.loading div' );
							if ( loadingDiv ) {
								loadingDiv.innerHTML = `To link a Pull Request to this ticket, create a new Pull Request in the
								<a href="https://github.com/${ primaryGitRepo }">${ primaryGitRepoDesc }</a>
								and include this ticket’s URL in the description.`;
							}
						}
					} )
					.catch( () => {
						const loadingDiv = container?.querySelector( '.pull-requests .loading div' );
						if ( loadingDiv ) {
							loadingDiv.textContent = 'Failed to load Pull Requests.';
						}
					} );
			}

			function renderAddSection() {
				// Add the Pull Requests section, #attachments is only present if authenticated or there exists uploads.
				const afterDiv = document.getElementById( 'attachments' ) || document.getElementById( 'commits' );
				if ( ! afterDiv ) {
					return;
				}

				afterDiv.insertAdjacentHTML(
					'afterend',
					`<div id="github-prs">
					<h3 class="foldable"><a id="section-pr" href="#section-pr">Pull Requests <span class="trac-count hidden">(<span></span>)</span></a></h3>
					<ul class="pull-requests">
					<li class="loading"><div>Loading…</div></li>
					</ul>
					</div>`
				);

				container = document.getElementById( 'github-prs' );

				// Make the section collapse.
				container.querySelector( '#section-pr' )?.addEventListener( 'click', function ( event ) {
					const div = this.parentNode.parentNode;
					div.classList.toggle( 'collapsed' );
					if ( div.classList.contains( 'collapsed' ) ) {
						event.preventDefault();
					}
				} );
			}

			function renderReportLoadGitHubTickets( warning ) {
				let user = window.wpTracCurrentUser || 'anonymous';
				const match = document.location.search.match( /USER=([^&]+)/ );
				if ( match ) {
					user = match[ 1 ];
				}

				// Logged out requests without a user context.
				if ( 'anonymous' === user ) {
					warning.remove();
					return;
				}

				warning.innerHTML =
					'<strong>Warning:</strong> Tickets with an attached GitHub PRs not included <button>Load PRs</button>';

				warning.addEventListener( 'click', function ( event ) {
					const button = event.target.closest( 'button' );
					if ( ! button ) {
						return;
					}

					button.disabled = true;
					button.textContent = 'Please wait…';

					const params = new URLSearchParams( { trac, author: user } );
					if ( authenticated ) {
						params.set( 'authenticated', '1' );
					}

					fetch( `${ apiEndpoint }?${ params }` )
						.then( ( res ) => {
							if ( ! res.ok ) {
								throw new Error( 'Failed' );
							}
							return res.json();
						} )
						.then( ( ticketList ) => {
							document.location = `${ document.location }${
								document.location.search ? '&' : '?'
							}GITHUBTICKETS=${ ticketList.join( ',' ) }`;
						} )
						.catch( () => {
							button.disabled = false;
							button.textContent = 'Retry loading PRs';
						} );
				} );
			}

			function createElement( tagName, attributes = {}, ...children ) {
				const el = document.createElement( tagName );

				Object.entries( attributes ).forEach( ( [ key, val ] ) => {
					if ( key === 'className' || key === 'class' ) {
						el.className = val;
					} else {
						el.setAttribute( key, val );
					}
				} );

				children.flat().forEach( ( child ) => {
					if ( child instanceof window.Node ) {
						el.append( child );
					} else if ( child !== null && child !== undefined ) {
						el.append( document.createTextNode( String( child ) ) );
					}
				} );

				return el;
			}

			// Logic to determine what the PRs status is
			function prStatusNode( data ) {
				const statusContainer = document.createElement( 'div' );

				// Closed? Skip everything else.
				if ( data.closed_at ) {
					statusContainer.textContent = '✅ Closed';
					return statusContainer;
				}

				let emojiState = '';
				const items = [];

				// Merge State then
				switch ( data.mergeable_state ) {
					case 'draft':
						items.push( 'Draft' );
						break;
					case 'blocked':
						// All Good (but blocked due to Branch protection rules, or Merge requirements)
						// or Changes Requested
						// or Unit Tests Failing.
						if (
							data.reviews?.CHANGES_REQUESTED ||
							( data.check_runs && Object.values( data.check_runs ).includes( 'failed' ) )
						) {
							// Let the unit tests / reviews section take care of it.
							break;
						} // else fall through.
					case 'clean':
						emojiState = '✅';
						items.push( 'All checks pass' );
						break;
					case 'dirty':
						emojiState = '❌';
						items.push( 'Merge conflicts' );
						break;
					case 'unstable':
						emojiState = '❌';
						items.push( 'Failing tests' );
						break;
					case 'unknown':
						items.push( 'Unknown' );
						break;
				}

				// Unit Tests?
				if ( data.check_runs ) {
					for ( const [ provider, status ] of Object.entries( data.check_runs ) ) {
						switch ( status ) {
							case 'in_progress':
								items.push( `${ provider } running` );
								break;
							case 'failed':
								emojiState = '❌';
								items.push( provider );
								break;
							case 'success':
								continue;
							default:
								items.push( `${ provider } ${ status }` );
								break;
						}
					}
				}

				// Reviews
				if ( data.reviews ) {
					if ( data.reviews.APPROVED?.length ) {
						emojiState = '✅';
						items.push(
							createElement(
								'span',
								{ title: 'Changes approved by: ' + data.reviews.APPROVED.join( ', ' ) },
								'Approved'
							)
						);
					}
					if ( data.reviews.CHANGES_REQUESTED?.length ) {
						emojiState = '❌';
						items.push(
							createElement(
								'span',
								{ title: 'Changes requested by: ' + data.reviews.CHANGES_REQUESTED.join( ', ' ) },
								'Changes Requested'
							)
						);
					}
				}
				if ( emojiState ) {
					statusContainer.append( document.createTextNode( emojiState + ' ' ) );
				}

				items.forEach( ( item, index ) => {
					if ( index > 0 ) {
						statusContainer.append( document.createTextNode( ', ' ) );
					}
					statusContainer.append( item );
				} );

				return statusContainer;
			}

			function renderPR( prContainer, data ) {
				// Strip off any ticket numbers from the start of the PR title for display.
				const displayTitle = data.title.replace( /^#\d+\s*/, '' );

				const li = createElement(
					'li',
					{},
					createElement(
						'div',
						{},
						createElement(
							'a',
							{ href: data.changes.html_url, title: displayTitle },
							`#${ data.number } ${ displayTitle }`
						),
						' by\u00A0',
						createElement( 'a', { href: data.user.url }, `@${ data.user.name }` )
					),
					prStatusNode( data ),
					createElement(
						'div',
						{},
						createElement( 'ins', {}, `+${ data.changes.additions }` ),
						'\u00A0',
						createElement( 'del', {}, `-${ data.changes.deletions }` )
					),
					createElement(
						'div',
						{},
						createElement( 'a', { href: data.changes.patch_url, class: 'button' }, 'View patch' ),
						'\u00A0',
						createElement( 'a', { href: data.changes.html_url, class: 'button' }, 'View PR' )
					)
				);

				prContainer.append( li );
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

				const classList = document.body.classList;

				// bbPress Trac.. has a set of components that I wish everyone had.
				if ( classList.contains( 'bbpress' ) ) {
					skipWords.push( 'api', 'component', 'tools', 'appearance' );
				}

				// On Meta, WordPress.org site is a "generic" category that shouldn't be used if possible.
				if ( classList.contains( 'meta' ) ) {
					generalCategories.push( 'WordPress.org Site' );
					skipWords.push( 'wordpress.org' );
				}

				const componentSelect = document.getElementById( 'field-component' );
				if ( ! componentSelect ) {
					return;
				}

				// Only if we have a 'General' option.
				const components = Array.from( componentSelect.options ).map( ( opt ) => opt.value );
				const hasDefaultCat = generalCategories.some( ( value ) => components.includes( value ) );

				if ( ! hasDefaultCat ) {
					return;
				}

				generateComponentWords( componentSelect );

				document
					.querySelectorAll( '#field-description, #field-summary, #field-component' )
					.forEach( ( field ) => field.addEventListener( 'blur', maybeSuggest ) );

				// Disable once the user hits the component option.
				componentSelect.addEventListener( 'change', function () {
					// If they selected a general category keep nagging.
					enabled = generalCategories.includes( this.value );

					if ( ! enabled && noticeDiv ) {
						noticeDiv.remove();
						noticeDiv = null;
					}
				} );
			}

			function maybeSuggest() {
				const summaryVal = document.getElementById( 'field-summary' )?.value || '';
				const descVal = document.getElementById( 'field-description' )?.value || '';
				const matchText = ( summaryVal + ' ' + descVal ).toLowerCase();
				const matchingWords = [],
					matchingComponents = [];

				if ( ! enabled || ! matchText.trim().length ) {
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
						if ( ! matchingComponents.includes( component ) ) {
							matchingComponents.push( component );
						}
					} );
				} );

				if ( ! noticeDiv ) {
					noticeDiv = document.createElement( 'div' );
					noticeDiv.id = 'componentSuggest';
					document.querySelector( '.buttons' )?.before( noticeDiv );

					noticeDiv.addEventListener( 'click', function ( e ) {
						const link = e.target.closest( 'a.component' );
						if ( ! link ) {
							return;
						}
						e.preventDefault();
						const component = link.textContent;
						const select = document.getElementById( 'field-component' );
						if ( select ) {
							select.value = component;
							select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						}
					} );
				}
				noticeDiv.replaceChildren( getNoticeNode( matchingComponents ) );
			}

			function getNoticeNode( matchingComponents ) {
				const hasMatches = matchingComponents.length > 0;
				const wrapper = document.createElement( 'div' );
				wrapper.className = 'wp-notice';
				wrapper.innerHTML = `<p><strong>Have you selected the right component?</strong></p>
					<p>You've not yet selected a component.
					Please check the "Component" option above${ hasMatches ? ' or select from one of the following:' : '.' }</p>`;

				if ( hasMatches ) {
					const ulList = document.createElement( 'ul' );
					matchingComponents.forEach( ( component ) => {
						const li = document.createElement( 'li' );
						const a = document.createElement( 'a' );
						a.href = '#';
						a.className = 'component';
						a.textContent = component;
						li.append( a );
						ulList.append( li );
					} );
					wrapper.append( ulList );
				}

				return wrapper;
			}

			function generateComponentWords( componentSelect ) {
				Array.from( componentSelect.options ).forEach( ( opt ) => {
					const component = opt.value;

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
						if ( 's' === word.slice( -1 ) ) {
							words.push( word.slice( 0, -1 ) );
						}
					} );

					words.forEach( ( word ) => {
						if ( ! word ) {
							return;
						}

						word = word.toLowerCase();

						if ( component !== word && skipWords.includes( word ) ) {
							return;
						}

						if ( ! ( word in componentWords ) ) {
							componentWords[ word ] = [];
						}

						componentWords[ word ].push( component );
					} );
				} );
			}

			return {
				init,
			};
		} )(),

		addDynamicAssetCacheBuster() {
			/*
			 * Mirrors the `?v=` a server-side rewrite adds to un-queried s.w.org
			 * assets, so dynamic loads request the same URL as the static tags.
			 */
			const scriptEl = document.querySelector( 'script[src^="https://s.w.org"][src*="v="]' );
			let cacheBuster = scriptEl?.getAttribute( 'src' );
			if ( cacheBuster ) {
				cacheBuster = ( cacheBuster.match( /[?&]v=([0-9]+)/ ) || [] )[ 1 ];
			}
			const maybeAddCacheBuster = function ( href ) {
				if ( cacheBuster && /https:\/\/s\.w\.org/i.test( href ) && /\.(css|js)$/.test( href ) ) {
					href += '?v=' + cacheBuster;
				}
				return href;
			};

			if ( $ ) {
				const oldLoadScript = $.loadScript;
				$.loadScript = function ( href, type, charset ) {
					return oldLoadScript( maybeAddCacheBuster( href ), type, charset );
				};
				const oldLoadStylesheet = $.loadStyleSheet;
				$.loadStyleSheet = function ( href ) {
					return oldLoadStylesheet( maybeAddCacheBuster( href ) );
				};
			}
		},

		disableTracAutoFocus() {
			// Disable the Trac autofocus which scrolls past the intro to creating tickets.
			document.querySelectorAll( '.trac-autofocus' ).forEach( ( el ) => el.classList.remove( 'trac-autofocus' ) );
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
				document
					.querySelectorAll( '#content.report .reports > div' )
					.forEach( ( el ) => el.classList.add( 'collapsed' ) );
			}
		},
	};

	onReady( wpTrac.init );

	// Perform this as soon as this file loads.
	wpTrac.disableTracAutoFocus();
	wpTrac.addDynamicAssetCacheBuster();
	wpTrac.preCollapseReportList();
} )( window.jQuery );
