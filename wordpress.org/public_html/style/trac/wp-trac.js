/* globals wpTracAutoCompleteUsers, wpTracContributorLabels, wpTracCurrentUser */
var wpTrac, coreKeywordList, gardenerKeywordList, reservedTerms, coreFocusesList, bugTrackerLocations, $body;

(function($){

	coreKeywordList = {
		'has-patch' : 'Proposed solution attached and ready for review.',
		'needs-patch' : 'Ticket needs a new patch.',
		'needs-refresh' : 'Patch no longer applies cleanly and needs to be updated.',
		'changes-requested': 'Feedback has been provided and the patch needs to be updated.',
		'reporter-feedback' : 'Feedback is needed from the reporter.',
		'dev-feedback' : 'Feedback is needed from a core developer.',
		'dev-reviewed' : 'Indicates that a ticket has been reviewed by two committers and can be backported when used in combination with the commit keyword.',
		'2nd-opinion' : 'A second opinion is desired for the problem or solution.',
		'close' : 'The ticket is a candidate for closure.',
		'needs-testing' : 'Patch has a particular need for testing.',
		'has-testing-info' : 'Steps have been provided to reproduce the issue or test a patch.',
		'needs-testing-info' : 'A more detailed testing procedure is needed to reproduce the issue, or to validate a patch works as expected.',
		'needs-design' : 'A designer should create a prototype of how the suggested changes should look/behave before writing code.',
		'needs-design-feedback' : 'A designer should review and give feedback on the proposed changes.',
		'has-unit-tests' : 'Proposed solution has unit test coverage.',
		'needs-unit-tests' : 'Ticket has a particular need for unit tests.',
		'has-dev-note' : 'Ticket with a published post on the development blog.',
		'needs-dev-note' : 'Ticket needs a post on the development blog.',
		'add-to-field-guide': 'Ticket dev-note should be included in the releasese field guide.',
		'has-privacy-review' : 'Input has been given from the core privacy team reviewing the privacy implications of the suggested changes.',
		'needs-privacy-review' : 'Input is needed from the core privacy team with regards to the privacy implications of the suggested changes.',
		'has-copy-review' : 'Input has been given from a copywriter reviewing the suggested verbiage changes.',
		'needs-copy-review' : 'Input is needed from a copywriter with regards to the suggested verbiage changes.',
		'needs-docs' : 'Inline documentation is needed.',
		'needs-user-docs' : 'The User Documentation needs to be updated or expanded.',
		'has-screenshots' : 'Visual changes are documented with screenshots.',
		'needs-screenshots' : 'Screenshots are needed as a visual change log.',
		'commit' : 'Patch is a suggested commit candidate.',
		'early' : 'Ticket should be addressed early in the next dev cycle.',
		'i18n-change' : 'A string change, used only after string freeze.',
		'good-first-bug': 'This ticket is great for a new contributor to work on, generally because it is easy or well-contained.',
		'fixed-major': 'The commits of this ticket need to be backported.'
	};

	coreFocusesList = {
		'ui' : 'Ticket is focused on user interface changes.',
		'accessibility' : 'Accessibility focus.',
		'javascript' : 'Heavy JavaScript focus.',
		'css' : 'CSS focus.',
		// 'unit tests' : 'PHP or JS unit tests.',
		'docs' : 'Inline documentation focus.',
		'rtl' : 'Right-to-left languages.',
		'administration' : 'Administration related, but assigned a more specific component.',
		'template' : 'Relating to theme templating, but assigned a more specific component.',
		'multisite' : 'Relating to multisite, but assigned a more specific component.',
		'rest-api' : 'Relating to the REST API, but assigned a more specific component.',
		'performance' : 'Performance or caching (but not the Cache API component).',
		'privacy' : 'Privacy focus.',
		'sustainability': 'Relating to improving the sustainability of WordPress.',
		'ui-copy' : 'Copy focus for the user interface.',
		'coding-standards' : 'Coding Standards focus.',
		'php-compatibility' : 'Relating to PHP forward and backward compatibility. A phpNN keyword identifies the PHP version that introduced the incompatibility.'
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
			allow_bypass: true
		},
		'Editor': {
			tracker: 'https://github.com/WordPress/gutenberg/issues/new/choose',
			tracker_text: 'Gutenberg GitHub Repository',
			bug_text: "the Gutenberg Editor",
			allow_bypass: true,
		},
		'WordCamp Site & Plugins': {
			tracker: 'https://github.com/WordPress/wordcamp.org/issues/new/choose',
			tracker_text: 'WordCamp.org GitHub Repository',
		},
		'Five For The Future': {
			tracker: 'https://github.com/WordPress/five-for-the-future/issues/new',
			tracker_text: 'Five for the Future GitHub Repository',
			enable_copy: true
		},
		'Learn (learn.wordpress.org)': {
			tracker: 'https://github.com/WordPress/Learn/issues/new/choose',
			tracker_text: 'WordPress.org Learn GitHub Repository',
		},
		'Pattern Directory': {
			tracker: 'https://github.com/WordPress/pattern-directory/issues/new/choose',
			tracker_text: 'WordPress.org Pattern Directory GitHub Repository',
		},
		'Openverse': {
			tracker: 'https://github.com/WordPress/openverse/issues/new/choose',
			tracker_text: 'Openverse GitHub Repository',
		},
		'Global Header/Footer': {
			tracker: 'https://github.com/WordPress/wporg-mu-plugins/issues/new?labels=Header+%26+Footer',
			tracker_text: 'WordPress.org mu-plugins GitHub Repository',
			enable_copy: true
		},
		'News (wordpress.org/news)': {
			tracker: 'https://github.com/WordPress/wporg-news-2021/issues/new',
			tracker_text: 'WordPress.org News GitHub Repository',
			enable_copy: true
		},
		'bbpress.org' : {
			tracker: 'https://bbpress.trac.wordpress.org/newticket?component=Site+-+bbPress.org',
			tracker_text: 'bbPress Trac instance',
			enable_copy: true
		},
		'buddypress.org': {
			tracker: 'https://buddypress.trac.wordpress.org/newticket?component=BuddyPress.org+Sites',
			tracker_text: 'BuddyPress Trac instance',
			enable_copy: true
		}
	};

	gardenerKeywordList = [ 'commit', 'early', 'i18n-change', 'good-first-bug', 'fixed-major', 'dev-reviewed' ];

	// phpDocumentor tags, but also a few common @-terms.
	reservedTerms = [
		'access', 'author', 'category', 'copyright', 'covers', 'coversNothing', 'deprecated', 'example',
		'expectedDeprecated', 'final', 'filesource', 'global', 'group', 'home', 'ignore', 'import',
		'inheritdoc', 'internal', 'license', 'link', 'media', 'mention', 'mentions', 'method', 'name',
		'notification', 'notifications', 'package', 'param', 'private', 'property', 'property-read',
		'requires', 'return', 'returns', 'see', 'since', 'static', 'staticvar', 'subpackage',
		'term', 'terms', 'throws', 'ticket', 'toc', 'todo', 'tutorial', 'type',
		'user', 'username', 'uses', 'var', 'version', 'wordpress', 'wp',
	];

	$body = $( document.body );

	wpTrac = {

		gardener: 'undefined' !== typeof wpBugGardener,
		currentUser: 'undefined' !== typeof wpTracCurrentUser ? wpTracCurrentUser : '',

		init: function() {
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

		isNewTicket: function() {
			return (
				window.location.pathname === '/newticket' ||
				$( 'form[action*="/newticket"]' ).length > 0
			);
		},

		showContributorLabels: function( labels ) {
			$( 'h3.change .username' ).each( function() {
				var html,
					$el = $( this ),
					username = $el.data( 'username' );

				if ( username in labels ) {
					if ( typeof labels[ username ] === 'object' ) {
						html = $( '<span />', {'class': 'contributor-label', 'title': labels[ username ].title }).text( labels[ username ].text );
					} else {
						html = $( '<span />', {'class': 'contributor-label'}).text( labels[ username ]);
					}
					$el.closest( '.username-line' ).append( '&ensp;' + html.prop('outerHTML') );
				}
			});
		},

		linkMentions: function( selector ) {
			// See https://github.com/regexps/mentions-regex/blob/master/index.js#L21
			var mentionsRegEx = /(^|[^a-zA-Z0-9_＠!@#$%&*])(?:(?:@|＠)(?!\/))([a-zA-Z0-9_\-.]{1,20})(?:\b(?!@|＠)|$)/g,
				mentionsInAttrRegEx = new RegExp( '="[^"]*?' + mentionsRegEx.source + '[\\s\\S]*?"' );

			$( selector || 'div.change .comment, #ticket .description' ).each( function() {
				var $comment = $( this ).html();

				if ( mentionsRegEx.test( $comment ) ) {
					var placeholders = [];

					if ( mentionsInAttrRegEx.test( $comment ) ) {
						// Preserve mentions in HTML attributes.
						$comment = $comment.replace( mentionsInAttrRegEx, function( match ) {
							placeholders.push( match );
							return '__PLACEHOLDER__';
						} );
					}

					$comment = $comment.replace( mentionsRegEx, function( match, pre, username ) {
						if ( -1 !== $.inArray( username, reservedTerms ) ) {
							return match;
						}

						var meClass = ( username === wpTrac.currentUser ) ? ' me' : '';
						return pre + '<a class="mention' + meClass + '" href="https://profiles.wordpress.org/' + username + '">@' + username + '</a>';
					} );

					// Restore mentions in HTML attributes.
					if ( placeholders.length ) {
						$comment = $comment.replace( '__PLACEHOLDER__', function() {
							return placeholders.shift();
						} );
					}

					$( this ).html( $comment );
				}
			});
		},

		linkGutenbergIssues: function( selector ) {
			var gutenRegEx = /\bGB[-]?(\d+)([^<]*<\/a>)?/gi,
				gutenInAttrRegEx = new RegExp( '="[^"]*?' + gutenRegEx.source + '[\\s\\S]*?"' );

			$( selector || 'div.change .comment, #ticket .description' ).each( function() {
				var $comment = $( this ).html();

				if ( gutenRegEx.test( $comment ) ) {
					var placeholders = [];

					if ( gutenInAttrRegEx.test( $comment ) ) {
						// Preserve matches in HTML attributes.
						$comment = $comment.replace( gutenInAttrRegEx, function( match ) {
							placeholders.push( match );
							return '__PLACEHOLDER__';
						} );
					}

					$comment = $comment.replace( gutenRegEx, function( match, issueNumber, closing_a_present ) {
						if ( closing_a_present ) {
							// Already linked
							return match;
						}

						return '<a class="gutenberg-issue github ext-link" href="https://github.com/WordPress/Gutenberg/issues/' + issueNumber + '"><span class="icon">&#8203;</span>' + match + '</a>';
					} );

					// Restore matches in HTML attributes.
					if ( placeholders.length ) {
						$comment = $comment.replace( '__PLACEHOLDER__', function() {
							return placeholders.shift();
						} );
					}

					$( this ).html( $comment );
				}
			});
		},

		// These ticket hacks need to be re-run after ticket previews.
		postPreviewHacks: function() {
			// Automatically preview images.
			$('li.trac-field-attachment').each( function() {
				var href, el, image, appendTo,
					li = $(this);
				if ( li.parent().parent().find( '.trac-image-preview' ).length ) {
					return;
				}
				el = li.find('.trac-rawlink');
				href = el.attr('href');
				if ( ! href.match(/\.(jpg|jpeg|png|gif|svg)$/i) ) {
					return;
				}
				appendTo = li.parent().parent(); // div.change
				image = new Image();
				image.src = href;
				image.onload = function() {
					$('<img />')
						.attr({
							src: href,
							width: image.width,
							height: image.height,
							class: 'trac-image-preview'
						})
						.appendTo( appendTo )
						.wrap( '<a href="' + href.replace('/raw-attachment/', '/attachment/') + '" />' );
				};
			});

			wpTrac.linkGutenbergIssues( '.ticketdraft .comment' );
			wpTrac.linkMentions( '.ticketdraft .comment' );
		},

		hacks: function() {
			var content = $( '#content' );

			// Add deprecated notice for core's test repository.
			if ( $body.hasClass( 'core' ) && content.hasClass( 'browser' ) ) {
				$( '#repoindex tbody .odd .name a[href="/browser/tests"]' )
					.parent()
					.append( '<p style="display:inline">Deprecated. <a href="/browser/trunk/tests">Please see default repository</a>.' );

				if ( window.location.pathname.substring( 0, 14 ) === '/browser/tests' ) {
					content.before( $( '<div />', {
						'class': 'system-message warning',
						'html': 'You are currently viewing the <strong>deprecated</strong> test repository. You may want to <a href="/browser/trunk/tests">view the tests in the default repository</a>.',
					} ) );
				}
			}

			if ( $body.hasClass( 'themes' ) ) {
				$( '#h_reporter' ).text( 'Developer:' );
				$( '#h_owner' ).text( 'Reviewer:' );

				// Prevent uploading of ZIP files to Trac.
				// See https://meta.trac.wordpress.org/ticket/3904
				$( '#attachment input[type="file"]' ).change( function() {
					var ext = this.value.split('.').pop();
					$( '#wp-block-zip-upload' ).remove(); // Hide the notice if it's already in the DOM
					if ( 'zip' == ext ) {
						this.value = '';

						$(this).parents('div.field').after( '<div class="wp-notice" id="wp-block-zip-upload"><p><strong>Please do not upload ZIPs to Trac.</strong><br>All Theme ZIPs (including updates) should be submitted via <a href="https://wordpress.org/themes/upload/">https://wordpress.org/themes/upload/</a>.</p></div>' );
					}
				} );
			}

			// Change 'Comments' and 'Stars' columns to dashicons glyphs to save space.
			$('th a[href*="sort=Comments"]').html('<div class="dashicons dashicons-admin-comments"></div>');
			$('th a[href*="sort=Stars"]').html('<div class="dashicons dashicons-star-empty"></div>');

			// Link username in header.
			(function($) {
				var el = $('#metanav').find('.first'),
					username;
				username = el.text();
				if ( 0 === username.indexOf( 'logged in as' ) ) {
					username = username.replace( 'logged in as ', '' );
					el.html( $('<a />', { href: 'https://profiles.wordpress.org/' + username }).text( username ) ).prepend( 'logged in as ');
				}
			})(jQuery);

			// Ticket-only tweaks.
			if ( content.hasClass( 'ticket' ) ) {
				wpTrac.redirectTicketsToProperTracker.init();

				// A collection of ticket hacks that must be run again after previews.
				wpTrac.postPreviewHacks();
				content.on( 'wpTracPostPreview', wpTrac.postPreviewHacks );

				// Allow 'Modify Ticket' to be shown even after a Trac preview tries to close it,
				// but only if it was already open.
				(function(){
					var action, hadClass,
						form = $('#propertyform'),
						modify = $('#modify').parent();

					if ( ! form.length ) {
						return;
					}
					action = form.attr('action');
					$(document).ajaxSend( function( event, XMLHttpRequest, ajaxOptions ) {
						if ( 0 !== action.indexOf( ajaxOptions.url ) ) {
							return;
						}
						hadClass = modify.hasClass('collapsed');
						// Prevent re-rendering of image previews and other changes from causing "jumps" while writing a comment.
						$(document.head).append( '<style id="changelog-height"> #changelog { height: ' + $('#changelog').height() + 'px !important; } </style>' );
					});
					$(document).ajaxComplete( function( event, XMLHttpRequest, ajaxOptions ) {
						if ( 0 !== action.indexOf( ajaxOptions.url ) ) {
							return;
						}
						if ( ! hadClass ) {
							modify.removeClass('collapsed');
						}
						content.triggerHandler( 'wpTracPostPreview' );
						window.setTimeout( function() { $('#changelog-height').remove(); }, 200 );
					});
				})();

				// Open WikiFormatting links in a new window.
				$( '#content.ticket' ).on( 'click', 'a[href$="wiki/WikiFormatting"]', function() {
					window.open( $( this ).attr( 'href' ) );
					return false;
				});

				// Submit comment form on Cmd/Ctrl + Enter.
				$( '#comment' ).keydown( function( event ) {
					if ( event.ctrlKey && ( event.keyCode === 10 || event.keyCode === 13 ) ) {
						$( 'input[name="submit"]' ).click();
					}
				});

				// Move all of the ticket actions text into the label.
				// Trac markup is like this: `<label>close</label> as fixed`
				jQuery('#action div label' ).each( function() {
					if ( this.nextSibling && Node.TEXT_NODE === this.nextSibling.nodeType ) {
						this.textContent += this.nextSibling.nodeValue;
						this.nextSibling.nodeValue = '';
					}
				} );

				// Point users to open new tickets when they comment on old tickets.
				if ( $('#ticket').find('.milestone').hasClass('closed') ) {
					var component = $('#field-component').val(), ticket_id = $('.trac-id').text(),
						newticket = '/newticket?component=' + encodeURIComponent( component ) + '&description=' + encodeURIComponent( 'This is a follow-up to ' + ticket_id + '.' );
					$('#trac-add-comment fieldset').prepend('<p class="ticket-reopen-notice"><span class="dashicons dashicons-info"></span> <strong>This ticket was closed on a completed milestone.</strong><br /> If you have a bug or enhancement to report, please <a href="' + newticket + '">open a new ticket</a>. Be sure to mention this ticket, ' + ticket_id + '.</p>');
					if ( ! wpTrac.gardener ) {
						$('#action_reopen').parent().remove();
					}
				}

				// Rudimentary save alerts for new tickets (summary/description) and comments.
				window.onbeforeunload = function() {
					if ( wpTrac.isNewTicket() ) {
						if ( ! $( '#field-description' ).val() && ! $( '#field-summary' ).val() ) {
							return;
						}
					} else if ( ! $( '#comment' ).val() ) {
						return;

					}
					return 'The changes you made will be lost if you navigate away from this page.';
				};
				$( '.buttons' ).on( 'click', 'input', function() {
					window.onbeforeunload = null;
				});
			}

			// Add custom buttons to the formatting toolbar.
			// http://trac.edgewall.org/browser/tags/trac-1.0.9/trac/htdocs/js/wikitoolbar.js
			(function($) {
				function extendWikiFormattingToolbar() {
					var $textarea = $( this ), textarea = $textarea[0], $wikitoolbar;
					if ( 'undefined' === typeof document.selection && 'undefined' === typeof textarea.setSelectionRange ) {
						return;
					}

					$wikitoolbar = $textarea.parents( 'div.trac-resizable' ).siblings( 'div.wikitoolbar' );

					// after = ID of an existing button.
					function addButton( id, title, after, fn ) {
						var $button = $( '<a />', { 'href': '#', 'id': id, 'title': title, 'tabIndex': 400 } );
						$button.on( 'click', function() {
							if ( false === $textarea.prop( 'disabled' ) && false === $textarea.prop( 'readonly' ) ) {
								try { fn(); } catch (e) { }
							}
							return false;
						});
						$wikitoolbar.find( after ).after( $button );
					}

					function encloseSelection( prefix, suffix ) {
						var start, end, sel, scrollPos, subst;
						textarea.focus();
						if ( 'undefined' !== typeof document.selection ) {
							sel = document.selection.createRange().text;
						} else if ( 'undefined' !== typeof textarea.setSelectionRange ) {
							start = textarea.selectionStart;
							end = textarea.selectionEnd;
							scrollPos = textarea.scrollTop;
							sel = textarea.value.substring( start, end );
						}
						if ( sel.match( / $/ ) ) { // exclude ending space char, if any
							sel = sel.substring( 0, sel.length - 1 );
							suffix = suffix + ' ';
						}
						subst = prefix + sel + suffix;
						if ( 'undefined' !== typeof document.selection) {
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

					addButton( 'code-php', 'PHP Code block: {{{#!php example }}}', '#code', function() {
						encloseSelection( "{{{#!php\n<?php\n", "\n}}}\n" ); // jshint ignore:line
					});
				}
				$( 'textarea.wikitext' ).each( extendWikiFormattingToolbar );
			})(jQuery);

			// Force 'Attachments' and 'Modify Ticket' to be shown.
			$('#attachments').removeClass('collapsed');
			$('#modify').parent().removeClass('collapsed');

			// Move the Add-Comment before Ticket Modify dialogue.
			$('#trac-add-comment').insertBefore( $('#modify').parent() );

			// Push live comment previews above 'Modify Ticket'.
			$('#ticketchange').insertAfter('#trac-add-comment');

			// Toggle the security notice on component change, if rendered.
			if ( $('#wp-security-notice').length ) {
				$('#field-component').change( function() {
					$('#wp-security-notice').toggle( 'Security' === $(this).val() );
				});
			}

			// Prevent links inside a ticket or comment preview from opening in the same window.
			$( '.ticketdraft' ).on( 'click', 'a', function() {
				window.open( $( this ).attr( 'href' ) );
				return false;
			});

			// Allow action text inputs and select fields to be clicked directly.
			$('#action')
				.find('input[type=text], select').enable().focus( function() {
					$(this).siblings('input[type=radio]').click();
				}).end()
				.find('input[name=action]').unbind('click').end()
				.find('div').has('select').find('input[type=radio]').change( function() {
					$(this).siblings('select').enable();
				});

			// Hide action text inputs and select fields from keyboard, unless the corresponding action is focused.
			$('#action')
				.find('input[type=text], select').each( function() {
					$(this).attr('tabindex', '-1');
				}).end()
				.find('input').blur( function() {
					$(this).parent().find('input[type=text], select').attr('tabindex', '-1');
				}).focus( function() {
					$(this).parent().find('input[type=text], select').removeAttr('tabindex');
				});

			// Clear the milestone on wontfix, duplicate, worksforme, invalid.
			var milestone = $('#field-milestone');
			if ( ! milestone.prop('disabled') ) {
				$('#propertyform').submit( function() {
					var action = $('input[name=action]:checked').val();
					if ( 'duplicate' === action || ( 'resolve' === action && 'fixed' !== $('#action_resolve_resolve_resolution').val() ) ) {
						milestone.val('');
					}
				});
			}

			// Prevent marking a ticket as a duplicate of itself.
			$('#propertyform').submit( function() {
				var action = $('input[name="action"]:checked').val(),
					currentTicket = parseInt( $('.trac-id').text().replace('#', '') ),
					duplicateTicket = parseInt( $('#action_dupe').val() );

				if ( 'duplicate' === action && ( ! duplicateTicket || currentTicket === duplicateTicket ) ) {
					$('#action_dupe').val('');
					return false;
				}
			});

			// capital_P_dangit()
			$( '#propertyform' ).on( 'submit', function() {
				var $summary     = $( '#field-summary' ),
					$description = $( '#field-description' ),
					$comment     = $( '#comment' ),
					isNewTicket  = wpTrac.isNewTicket();

				// Simple replacement for ticket summary.
				if ( isNewTicket ) {
					$summary.val( $summary.val().replaceAll( 'Wordpress', 'WordPress' ) );
				}

				// Use the more judicious replacement for ticket description and comments.
				$.each( [ ' Wordpress', '&#8216;Wordpress', '&#8220;Wordpress', '>Wordpress', '(Wordpress' ], function( index, value ) {
					var replacement = value.replaceAll( 'Wordpress', 'WordPress' );

					if ( $description.length && isNewTicket ) {
						$description.val( $description.val().replaceAll( value, replacement ) );
					}
					if ( $comment.length ) {
						$comment.val( $comment.val().replaceAll( value, replacement ) );
					}
				} );
			} );

			// Add a 'Show only commits/attachments' view option to tickets.
			$('label[for="trac-comments-only-toggle"]').text('Show only comment text');
			$('form#prefs')
				.has('#trac-comments-order')
					.append('<div><input type="checkbox" id="wp-trac-commits-only" /> <label for="wp-trac-commits-only">Show only commits/attachments</label></div>');
			$('#wp-trac-commits-only').change( function() {
				if ( ! this.checked ) {
					$('div.change').show();
					return;
				}
				$('div.change')
					.hide()
						// Best we can do to target a.
						.has('.comment > p > a.changeset')
							.has('.comment div.message p a.ticket')
							.show()
						.end()
					.end()
					.has('li.trac-field-attachment')
					.show();
			});

			// List commits between #ticket and #attachments.
			if ( $( '#content.ticket' ).length && ! $( '#ticket.ticketdraft' ).length ) {
				var $commitChanges = $( 'div.change' ).has( '.comment > p > a.changeset' ).has( '.comment div.message p a.ticket' ),
					$commits = $( '<ul/>' ), commitCount = 0;

				$commitChanges.each( function( i, el ) {
					var $el = $( el ), $comment = $el.find( '.comment' ), commitNumber, firstLine,
						author, date, $commit = $( '<li>' );

					commitNumber = $comment.find( '> p ').html().trim().replace( /^In /, '' ).replace( /:<br>$/, '' );
					$commit.append( '[' + commitNumber + '] ' );

					firstLine = $comment.find( '.message > p' ).html().trim().replace( /<br>$/, '' );
					$commit.append( firstLine + '&hellip;' );

					author = $el.find( '.username' ).data( 'username' );
					$commit.append( ' by <a href="https://profiles.wordpress.org/' + author + '">@' + author + '</a>' );

					date = $el.find( '.time-ago' ).html();
					$commit.append( ' ' + date );

					$commits.append( $commit );
					commitCount += 1;
				});

				$( '#ticket' ).after(
					$( '<div/>', {
						id: 'commits',
						class: 'collapsed'
					})
					.append(
						$( '<h3/>', {
							class: 'foldable'
						})
						.html( '<a href="#no0" id="no0">Commits <span class="trac-count">(' + commitCount + ')</span></a>' )
					).append(
						$( '<div/>', {
							class: 'commits'
						})
						.append( $commits )
					)
				);
			}

			// See $.fn.enableFolding().
			$( '#no0' ).on( 'click', function() {
				var $div = $( this.parentNode.parentNode ).toggleClass( 'collapsed' );
				return ! $div.hasClass( 'collapsed' );
			});

			// 'User Interface' preferences tab => 'Help Links' (and removes icons-only setting).
			var uitab = $('#tab_userinterface');
			if ( uitab.length ) {
				if ( uitab.hasClass('active') ) {
					uitab.text('Help Links');
					$('input[name="ui.use_symbols"]').closest('div.field').remove();
				} else {
					uitab.find('a').text('Help Links');
				}
			}

			if ( content.hasClass( 'search' ) ) {
				// Remove 'Wiki' and 'Milestone' from search.
				$( '#fullsearch #milestone' ).next().remove().end().remove();
				$( '#fullsearch #wiki' ).next().remove().end().remove();
			}
		},

		// If we're not dealing with a trusted bug gardener:
		nonGardeners: function() {
			var version,
				elements = {};

			// Hide disabled fields (new ticket & ticket modify)
			$('.trac-properties select[disabled]').parents( 'td' ).hide().prev().hide();

			elements.type = $('#field-type');
			elements.version = $('#field-version');
			version = parseFloat( elements.version.val() );

			// Remove task, or make a task ticket read only. This supports the ticket type being 'task' or 'task (blessed)'
			if ( elements.type.length ) {
				if ( -1 !== elements.type.val().indexOf( 'task' ) ) {
					elements.type.after('<input type="hidden" name="field_type" value="' + elements.type.val() + '" /> ' + elements.type.val() )
						.parent().css('vertical-align', 'middle').end()
						.remove();
				} else {
					elements.type.find('option[value*="task"]').remove();
				}
			}

			// Once a Version is set, remove newer versions.
			if ( version ) {
				elements.version.find('option').each( function() {
					var value = parseFloat( $(this).val() );

					if ( ! value || value > version ) {
						$(this).remove();
					}
				});
			}

			// Rename the "Submit changes" buttons
			$('input[type="submit"][value="Submit changes"]').prop( 'value', 'Add Comment' );
		},

		reports: function() {
			var popup = $( '#report-popup' ),
				$headline = $( '#headline' ),
				failed = false;

			$( '#report-popup' ).on( 'change', '.tickets-by-topic', function() {
				var topic = $(this).val();
				if ( ! topic ) {
					return;
				}
				window.location.href = $(this).data( 'location' ) + topic;
				return false;
			});

			popup.appendTo( '#main' );

			$( '.open-ticket-report' ).click( function( event ) {
				// Allow opening the report on make.
				if ( event.metaKey || event.ctrlKey || event.shiftKey ) {
					return;
				}

				// Calculate the correct position, even if the header size/etc changes.
				popup.css( 'top', ( $headline.offset().top + $headline.outerHeight() ) + 'px' );

				if ( popup.children().length === 0 ) {
					$.ajax({
						url: 'https://make.wordpress.org/core/reports/?from-trac',
						xhrFields: { withCredentials: true }
					}).done( function( data ) {
						$( data ).find( '.ticket-reports' ).appendTo( popup );
						$body.addClass( 'ticket-reports-open' );
					}).fail( function() {
						failed = true;
					});
				} else {
					$body.toggleClass( 'ticket-reports-open' );
					event.preventDefault();
				}
				if ( ! failed ) {
					event.preventDefault();
				}
			});
			$( '#report-popup' ).on( 'click', '.close', function() {
				$body.removeClass( 'ticket-reports-open' );
				return false;
			});
		},

		redirectTicketsToProperTracker: ( function() {
			var component = $('#field-component');

			return {
				init: function() {
					// Special hack to not show the warning on Meta Trac for the WordPress.org site.
					if ( window.location.host === 'meta.trac.wordpress.org' ) {
						delete bugTrackerLocations['WordPress.org Site'];
					}

					// Prevent changing to the component if need be.
					if ( ! wpTrac.isNewTicket() ) {
						for ( var c in bugTrackerLocations ) {
							if ( ! bugTrackerLocations[c].prevent_changing_to ) {
								continue;
							}
							if ( component.val() != c ) {
								component.children('option[value="' + c + '"]').remove();
							}
						}
					}

					// Show a notice when the component is selected.
					component.change( wpTrac.redirectTicketsToProperTracker.maybeShowNotice );

					// Trigger a warning on load, when the ticket is not closed.
					if ( ! wpTrac.isNewTicket() && ! $('#action_reopen').length ) {
						wpTrac.redirectTicketsToProperTracker.maybeShowNotice();
					}

					$('#propertyform').on( 'click', '#new-tracker-ticket', function() {
						var url, url_params = {}, href = $(this).attr( 'href' ),
							summary_field = 'summary', description_field = 'description';

						// Trac (default) and GitHub are supported.
						if ( href.match( /github.com/ ) ) {
							summary_field = 'title';
							description_field = 'body';
						}

						url_params[summary_field]     = $('#field-summary').val();
						url_params[description_field] = $('#field-description').val()

						url = href + ( href.indexOf( '?' ) !== -1 ? '&' : '?' ) + $.param( url_params );
						if ( url.length > 1500 ) {
							url_params[description_field] = '(Couldn\'t copy over your description as it was too long. Please paste it here. Your old window was not closed.)';
							url = href + ( href.indexOf( '?' ) !== -1 ? '&' : '?' ) + $.param( url_params );
							window.open( url );
						} else {
							window.location.href = url;
						}
						return false;
					});
				},

				maybeShowNotice: function() {
					var toggle = $('input[name="attachment"]').parent().add('.ticketdraft').add('.wp-notice').add('div.buttons');

					// Reset.
					$('.wp-notice.component').remove();
					toggle.hide();

					var selectedComponent = component.val();
					if ( !( selectedComponent in bugTrackerLocations ) ) {
						toggle.show();
						return;
					}

					var tracker = bugTrackerLocations[ selectedComponent ];

					// If the component (ie. Editor) allows bypassing the warning show the create buttons.
					if ( ! wpTrac.isNewTicket() || tracker.allow_bypass ) {
						toggle.show();
					}

					$('div.buttons').before(
						'<div class="wp-notice component"><p>' +
							'<strong>Tickets related to ' + ( tracker.bug_text || selectedComponent ) + '</strong> should be filed on the ' +
							'<a href="' + tracker.tracker + '">' + ( tracker.tracker_text || tracker.tracker ) + '</a>' +
						'</p><p>' +
							'Would you mind creating this ticket over there instead if appropriate? ' +
							( tracker.enable_copy ? '<a href="' + tracker.tracker + '" id="new-tracker-ticket">Click here to copy your summary and description over</a>.' : '' ) +
						'</p>' +
							( wpTrac.isNewTicket() && tracker.allow_bypass ? "<p>If this isn't related to " + ( tracker.bug_text || selectedComponent ) + ', please continue to open this ticket here.</p>' : '' ) +
						'</div>'
					);
				}
			};
		}() ),

		autocomplete: (function() {
			var ticketParticipants = [],
				nonTicketParticipants = [],
				settings = {};

			return {
				init: function() {
					if ( ! $( '#comment' ).length ) {
						return;
					}

					if ( 'undefined' !== typeof wpTracAutoCompleteUsers ) {
						settings = wpTracAutoCompleteUsers;
					}

					this.initTicketParticipants();
					this.initNonTicketParticipants();

					// Adjusts the query so it doesn't search for 'achment' in case Ryan enters too many characters.
					var replacer = function ( query ) {
						return query.replace( /^(achment|achmen|achme|achm|ach|ac|a)/g, '' );
					};

					$( '#comment' ).atwho({
						at:        '@',
						callbacks: {
							filter:       this.filterTicketParticipants,
							remoteFilter: this.filterNonTicketParticipants
						}
					}).atwho({
						at:         '[att',
						insertTpl:  '${atwho-at}achment:"${name}"]',
						displayTpl: '<li>${display}</li>',
						data:       this.getAttachments(),
						callbacks: {
							filter: function( query, data, searchKey ) {
								return this.callDefault( 'filter', replacer( query ), data, searchKey );
							},
							sorter: function( query, items, searchKey ) {
								return this.callDefault( 'sorter', replacer( query ), items, searchKey );
							},
							highlighter: function( li, query ) {
								return this.callDefault( 'highlighter', li, replacer( query ) );
							}
						}
					});
				},

				filterNonTicketParticipants: function( query, callback ) {
					// Bail out if the query is empty.
					if ( '' === query ) {
						return callback();
					}

					var results = [],
						regex = new RegExp( '^' + query, 'ig' ); // start of string

					$.each( nonTicketParticipants, function( key, value ) {
						if ( value.toLowerCase().match( regex ) ) {
							results.push( { name: value } );
						}
					});

					callback( results );
				},

				filterTicketParticipants: function( query ) {
					// Bail out if the query is empty.
					if ( '' === query ) {
						return ticketParticipants;
					}

					var results = [],
						regex = new RegExp( '^' + query, 'ig' ); // start of string

					$.each( ticketParticipants, function( key, value ){
						if ( value.toLowerCase().match( regex ) ) {
							results.push( value );
						}
					});

					return results;
				},

				initTicketParticipants: function() {
					var users  = [], exclude = [];

					if ( 'undefined' !== settings.exclude ) {
						exclude = settings.exclude;
					}

					// Most recent should show up first.
					$( $( '.change .username' ).get().reverse() ).each( function() {
						var username = $(this).data( 'username' );

						// Override the username with the nicename if it differs by more than just case (ie. spaces, etc)
						if (
							$(this).data( 'nicename' ) &&
							username.toLowerCase() != $(this).data( 'nicename' ).toLowerCase() &&
							wpTrac.currentUser !== username
						) {
							username = $(this).data( 'nicename' );
						}

						if (
							typeof username !== 'undefined' &&
							-1 === $.inArray( username, users ) &&
							-1 === $.inArray( username, exclude )
						) {
							users.push( username );
						}
					});

					// Add ticket reporter.
					var ticketReporter = $.trim( $( '#ticket td[headers="h_reporter"]' ).text() );
					var ticketReporterNicename = $( '#ticket td[headers="h_reporter"] a' ).data( 'nicename' );
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
						users = $.grep( users, function( user ) {
							return user !== wpTrac.currentUser;
						});
					}

					ticketParticipants = users;
				},

				getTicketParticipants: function() {
					return ticketParticipants;
				},

				addTicketParticipant: function( ticketParticipant ) {
					if ( -1 === $.inArray( ticketParticipant, ticketParticipants ) ) {
						$.merge( ticketParticipants, [ ticketParticipant ] );
					}
				},

				initNonTicketParticipants: function() {
					var users  = [], exclude = [];

					if ( 'undefined' !== settings.exclude ) {
						exclude = settings.exclude;
					}

					if ( 'undefined' !== typeof settings.include ) {
						$.each( settings.include, function( k, username ) {
							if ( -1 === $.inArray( username, users ) && -1 === $.inArray( username, ticketParticipants ) ) {
								users.push( username );
							}
						});
					}

					// Exclude current user.
					if ( 'undefined' !== wpTrac.currentUser ) {
						users = $.grep( users, function( user ) {
							return user !== wpTrac.currentUser;
						});
					}

					nonTicketParticipants = users;
				},

				getNonTicketParticipants: function() {
					return nonTicketParticipants;
				},

				addNonTicketParticipant: function( nonTicketParticipant ) {
					if ( -1 === $.inArray( nonTicketParticipant, nonTicketParticipants ) ) {
						$.merge( nonTicketParticipants, [ nonTicketParticipant ] );
					}
				},

				getAttachments: function() {
					var attachments = [];

					// Most recent should show up first.
					$( $( 'dl.attachments dt' ).get().reverse() ).each( function() {
						attachments.push({
							display: $( this ).text().replace( /\n/g,'' ),
							name: $( this ).find( 'a[title="View attachment"]' ).text().replace( /\n/g,'' )
						});
					});

					return attachments;
				}
			};
		}()),

		workflow: (function() {
			var keywords = {},
				originalKeywords = {},
				elements = {};

			return {
				init: function() {
					elements.hiddenEl = $( '#field-keywords' ).attr( 'aria-label', 'Manual keywords' );
					if ( ! elements.hiddenEl.length ) {
						return;
					}

					// Attach change event handler on the field-keywords input.
					elements.hiddenEl.change( wpTrac.workflow.populate );

					// Designed so the list could have come from another file.
					if ( typeof coreKeywordList === 'undefined' ) {
						return;
					}

					// Generate the workflow template.
					wpTrac.workflow.template();

					// Load up the initial keywords and the dropdown.
					wpTrac.workflow.populate();

					// Save these for later.
					originalKeywords = $.merge([], keywords);

					// Catch the submit to see if keywords were simply reordered.
					elements.hiddenEl.parents('form').submit( wpTrac.workflow.submit );

					// Keyword removal.
					elements.bin.on( 'click', '.keyword-button-remove', function() {
						wpTrac.workflow.removeKeyword( $(this).parent() );
						// Move focus to the Manual keyword button to avoid focus loss on keyword removal.
						$( '#edit-keywords' )
							.addClass( 'hide-programmatic-focus' )
							.focus()
							.on( 'blur', function() {
								$( this ).removeClass( 'hide-programmatic-focus' );
							} );
					});

					// Keyword adds.
					$('#keyword-add').bind('change keypress', function(e) {
						if ( e.type === 'keypress' ) {
							if ( e.which === 13 ) {
								e.stopPropagation();
								e.preventDefault();
							} else {
								return;
							}
						}
						wpTrac.workflow.addKeyword( $(this).val() );
						$(this).val('');
					});

					// Manual keyword button.
					$('#edit-keywords').click( function() {
						if ( elements.hiddenEl.is( ':visible' ) ) {
							elements.hiddenEl.hide();
							$( this ).attr( 'aria-expanded', 'false' );
							return;
						}

						$( this ).attr( 'aria-expanded', 'true' );
						elements.hiddenEl.show().focus();
					});

					// Handle keyboard interaction on the field-keywords field.
					$( '#field-keywords' ).on( 'keydown', function( event ) {
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
							$( '#edit-keywords' )
								.attr( 'aria-expanded', 'false' )
								.focus();
						}
					} );
				},

				// Generates the workflow template.
				template : function() {
					var container = elements.hiddenEl.parent(), html, labelWidth;

					// Necessary to keep everything in line.
					labelWidth = container.prev().width();

					// Rearrange the table to suit our needs.
					container.prev().detach().end()
						.attr('colspan', '2').addClass('has-js')
						.parents('table').css('table-layout', 'fixed');

					// If the owner field exists, then we're on /newticket. Remove it.
					$('#field-owner').parents('tr').hide();

					html = '<div><label id="keyword-label" for="keyword-add" style="width:' + labelWidth + 'px">Workflow Keywords:</label>';
					html += '<select id="keyword-add"><option value=""> - Add - </option></select>';
					html += '<button type="button" id="edit-keywords" aria-label="Manual keyword" aria-expanded="false">Manual</button></div>';
					html += '<div id="keyword-bin"></div>';
					container.prepend( html );
					elements.bin = $('#keyword-bin');
				},

				// Populates the keywords and dropdown.
				populate : function() {
					// For repopulation. Starting over.
					if ( elements.bin.find('span').length ) {
						elements.bin.empty();
					}

					// Replace commas, collapse spaces, trim, then split by space.
					keywords = $.trim( elements.hiddenEl.val().replace(/,/g, ' ').replace(/ +/g, ' ') ).split(' ');

					// Put our cleaned up version back into the hidden field.
					elements.hiddenEl.val( keywords.join(' ') );

					// If we have a non-empty keyword, let's go through the process of adding the spans.
					if ( 1 !== keywords.length || keywords[0] !== '' ) {
						$.each( keywords, function( k, v ) {
							var html = $( '<span />' ).text( v ).attr( 'data-keyword', v ).prepend( $( '<button type="button" aria-label="Remove keyword" class="keyword-button-remove dashicons dashicons-dismiss" />' ).attr( 'aria-label', 'Remove ' + v + ' keyword' ) );
							if ( v in coreKeywordList ) {
								html.attr('title', coreKeywordList[v]);
							}
							html.appendTo( elements.bin );
						});
					}

					// Populate the dropdown.
					if ( elements.add ) {
						elements.add.children().not('[value=""]').remove();
					} else {
						elements.add = $('#keyword-add');
					}

					$.each( coreKeywordList, function( k ) {
						// Don't show special (permission-based) ones.
						if ( ! wpTrac.gardener && -1 !== $.inArray( k, gardenerKeywordList ) ) {
							return;
						}
						elements.add.append( '<option value="' + k + ( -1 !== $.inArray( k, keywords ) ? '" disabled="disabled">* ' : '">' ) + k + '</option>' );
					});
				},

				// Add a keyword. Takes a sanitized string.
				addKeyword : function( keyword ) {
					if ( ! keyword ) {
						return;
					}

					var html, title = '';

					// Don't add it again.
					if ( -1 !== $.inArray( keyword, keywords ) ) {
						return;
					}
					keywords.push( keyword );

					// Update the dropdown. Core keywords also get a title attribute with their description.
					if ( keyword in coreKeywordList ) {
						elements.add.find('option[value=' + keyword + ']').prop('disabled', true).text('* ' + keyword);
						title = coreKeywordList[keyword];
					}

					if ( 'has-patch' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-patch' );
					} else if ( 'needs-patch' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-patch' );
					}

					if ( 'has-testing-info' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-testing-info' );
					} else if ( 'needs-testing-info' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-testing-info' );
					}

					if ( 'has-unit-tests' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-unit-tests' );
					} else if ( 'needs-unit-tests' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-unit-tests' );
					}

					if ( 'has-dev-note' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-dev-note' );
					} else if ( 'needs-dev-note' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-dev-note' );
					}

					if ( 'dev-reviewed' === keyword ) {
						wpTrac.workflow.removeKeyword( 'dev-feedback' );
					}

					if ( 'has-privacy-review' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-privacy-review' );
					} else if ( 'needs-privacy-review' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-privacy-review' );
					}

					if ( 'has-copy-review' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-copy-review' );
					} else if ( 'needs-copy-review' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-copy-review' );
					}

					if ( 'has-screenshots' === keyword ) {
						wpTrac.workflow.removeKeyword( 'needs-screenshots' );
					} else if ( 'needs-screenshots' === keyword ) {
						wpTrac.workflow.removeKeyword( 'has-screenshots' );
					}

					// Add it to the bin, and refresh the hidden input.
					html = $( '<span />' ).text( keyword ).attr( 'data-keyword', keyword ).prepend( $( '<button type="button" aria-label="Remove keyword" class="keyword-button-remove dashicons dashicons-dismiss" />' ).attr( 'aria-label', 'Remove ' + keyword +' keyword' ) );
					if ( title ) {
						html.attr('title', title);
					}
					html.appendTo( elements.bin );
					elements.hiddenEl.val( keywords.join(' ') );
				},

				// Remove a keyword. Takes a jQuery object of a keyword in the bin, or a sanitized keyword as a string.
				removeKeyword : function( object ) {
					var keyword;
					if ( typeof object === 'string' ) {
						keyword = object;
						object  = elements.bin.find('span[data-keyword="' + keyword + '"]');

						if ( ! object.length ) {
							return;
						}
					} else {
						keyword = object.text();
					}

					keywords = $.grep( keywords, function(v) {
						return v !== keyword;
					});

					// Update the core keyword dropdown.
					if ( keyword in coreKeywordList ) {
						elements.add.find('option[value=' + keyword + ']').prop('disabled', false).text( keyword );
					}
					elements.hiddenEl.val( keywords.join(' ') );
					object.remove();
				},

				// Check on submit that we're not just re-ordering keywords.
				// Otherwise, Trac flips out and adds a useless 'Keywords changed from X to X' marker.
				submit : function() {
					if ( keywords.length !== originalKeywords.length ) {
						return;
					}

					var testKeywords = $.grep( keywords, function(v) {
						return -1 === $.inArray( v, originalKeywords );
					});

					// If the difference has no length, then restore to the original keyword order.
					if ( ! testKeywords.length ) {
						elements.hiddenEl.val( originalKeywords.join(' ') );
					}
				}
			};
		}()),

		focuses: (function() {
			var field, container, focuses, originalFocuses;

			function init() {
				var ul, classes;
				if ( typeof coreFocusesList === 'undefined' ) {
					return;
				}

				field = $( '#field-focuses' );
				if ( field.length === 0 ) {
					return;
				}
				if ( $( '#field-owner' ).length === 0 ) {
					$('label[for="field-focuses"]').parent().remove();
				}
				if ( field.parent().attr( 'colspan' ) === 3 ) {
					field.parent().attr( 'id', 'focuses' );
				} else {
					field.parent().attr({ colspan: 2, id: 'focuses' });
				}
				field.hide();

				focuses = $.trim( field.val().replace(/,/g, ' ').replace(/ +/g, ' ') );
				if ( focuses.length === 0 ) {
					focuses = [];
				} else {
					focuses = focuses.split( ' ' );
				}
				originalFocuses = $.merge( [], focuses );

				container = $( '#focuses' );

				ul = $( '<ul />' );
				$.each( coreFocusesList, function( focus, description ) {
					var ariaPressed = 'false';
					classes = focus.replace( ' ', '-' );
					if ( -1 !== $.inArray( focus, focuses ) ) {
						classes += ' active';
						ariaPressed = 'true';
					}
					ul.append( $( '<li />', {
						'data-focus' : focus,
						title: description,
						class: classes
					} ).html( '<button type="button" class="core-focuses-button" aria-pressed="' + ariaPressed + '">' + ( focus === 'administration' ? 'admin' : focus ) + '</a>' ) );
				});
				ul.appendTo( container );
				ul.wrap( '<fieldset id="fieldset-focuses" />' );
				ul.before( '<legend class="core-focuses-legend">Contributor Focuses:</legend>' );

				container.on( 'click', '.core-focuses-button', addRemove );
				container.closest( 'form' ).on( 'submit', submit );
				$( '#field-component' ).on( 'change', componentSync );
			}

			function addRemove() {
				var focus = $( this ).parent();
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
				var remove = focus.data( 'focus' );
				focuses = $.grep( focuses, function( value ) {
					return value !== remove;
				} );
				updateField();
			}

			function updateField() {
				var orderedFocuses = [];
				$.each( coreFocusesList, function( focus ) {
					if ( -1 !== $.inArray( focus, focuses ) ) {
						orderedFocuses.push( focus );
					}
				});
				field.val( orderedFocuses.join( ', ' ) );
			}

			function componentSync() {
				var component = $(this).val();
				if ( component === 'Network Admin' || component === 'Networks and Sites' ) {
					add( 'multisite' );
				}
			}

			function submit() {
				if ( focuses.length !== originalFocuses.length ) {
					return;
				}

				var testFocuses = $.grep( focuses, function(v) {
					return -1 === $.inArray( v, originalFocuses );
				});

				// If the difference has no length, then restore to the original order.
				if ( ! testFocuses.length ) {
					field.val( originalFocuses.join( ', ' ) );
				}
			}

			return {
				init: init
			};
		}()),

		notifications: (function() {
			var notifications, endpoint, star, _ticket, _nonce;

			function init( settings ) {
				$( hide_cc_field );
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

			function hide_cc_field() {
				var content = $( '#content' );
				if ( content.hasClass( 'query' ) ) {
					$( 'table.trac-clause tr.actions option[value="cc"]' ).remove();
					$( '#columns' ).find( 'input[type="checkbox"][name="col"][value="cc"]' ).parent().remove();
				}
				if ( content.hasClass( 'ticket' ) ) {
					// Remove the CC field in case the BlackMagic plugin didn't.
					$('#field-cc').parent().parent().prev().remove().end().remove();
					hide_cc_comments();
					content.on( 'wpTracPostPreview', hide_cc_comments );
				}
			}

			function hide_cc_comments() {
				$( '#changelog div.change' ).has( 'li.trac-field-cc' ).each( function() {
					var change = $(this), changes = change.children( 'ul.changes' );
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
				});
			}

			function ticketInit( ticket ) {
				$.ajax({
					url: endpoint + '?trac-notifications=' + ticket,
					xhrFields: { withCredentials: true }
				}).success( function( data ) {
					if ( data.success ) {
						render( data.data['notifications-box'] );
						if ( data.data.maintainers ) {
							maintainerLabels( data.data.maintainers );
						//	wpTrac.autocomplete.addNonTicketParticipant( data.data.maintainers ); doesn't work yet, because ticketInit() runs before autocomplete.init()
						}

						if ( data.data.nonce ) {
							_nonce = data.data.nonce;
						}
					}
				});
			}

			function maintainerLabels( maintainers ) {
				var i, len, labels = {};
					for ( i = 0, len = maintainers.length; i < len; i++ ) {
					labels[ maintainers[i] ] = {
						text:  'Component Maintainer',
						title: '@' + maintainers[i] + ' maintains the ' + $.trim( $('td[headers="h_component"]').text() ) + ' component'
					};
				}
				wpTrac.showContributorLabels( labels );
			}

			function render( data ) {
				$( '#propertyform' ).before( data );
				notifications = $('#notifications');
				notifications.on( 'click', '.watch-this-ticket', subscribe )
					.on( 'click', '.watching-ticket', unsubscribe )
					.on( 'click', '.block-notifications', block )
					.on( 'click', '.unblock-notifications', unblock );

				$('#ticket.trac-content > h2').prepend( '<div class="ticket-star dashicons dashicons-star-' +
					( notifications.hasClass('subscribed') ? 'filled' : 'empty' ) + '" title="Watch/unwatch this ticket"></div>' );

				star = $('.ticket-star');

				star.click( function() {
					$(this).hasClass('dashicons-star-empty') ? subscribe() : unsubscribe();
				});
				$('.grid-toggle').on( 'click', 'a', function() {
					var names = $(this).hasClass('names');
					notifications.toggleClass('show-usernames', names );
					document.cookie = 'wp_trac_ngrid=' + (names ? 1 : 0) + ';max-age=31557600;domain=.wordpress.org;path=/';
					return false;
				});

				// Trac notification control is broken due to the Trac upgrade to 1.2
				$( '#notifications p.receiving-notifications, #notifications p.receiving-notifications-because, #notifications p.not-receiving-notifications, #notifications .preferences' ).hide();
			}

			function save( action, ticket, nonce ) {
				ticket = ticket || _ticket;
				nonce = nonce || _nonce;
				$.ajax({
					type: 'POST',
					url: endpoint,
					xhrFields: { withCredentials: true },
					data: {
						'trac-ticket-sub': ticket,
						action: action,
						nonce: nonce
					}
				});
			}

			function subscribe() {
				save( 'subscribe' );
				notifications.removeClass('blocked').addClass('subscribed');
				star.toggleClass('dashicons-star-empty dashicons-star-filled');
				if ( notifications.hasClass('receiving') ) {
					notifications.addClass('block');
				}
				change_count( 1 );
				return false;
			}

			function unsubscribe() {
				save( 'unsubscribe' );
				notifications.removeClass('subscribed');
				star.toggleClass('dashicons-star-empty dashicons-star-filled');
				if ( notifications.hasClass('receiving') ) {
					notifications.addClass('block');
				}
				change_count( -1 );
				return false;
			}

			function change_count( delta ) {
				var count = parseInt( notifications.find('.count').text(), 10 ) + delta;
				notifications.find('.count').text( count );
				notifications.toggleClass( 'count-0', count === 0 ).toggleClass( 'count-1', count === 1 );
			}

			function block() {
				save( 'block' );
				notifications.removeClass('block').addClass('blocked');
				return false;
			}

			function unblock() {
				save( 'unblock' );
				notifications.removeClass('blocked').addClass('block');
				return false;
			}

			function reportInit() {
				var stars,
					tickets = [],
					cells = $('table.listing').find('td.Stars');

				if ( cells.length === 0 ) {
					return;
				}
				cells.wrapInner( '<span class="count" />' );
				cells.append(' <div class="dashicons dashicons-star-empty loading trac-report-star"></div>' );
				stars = $('.trac-report-star');
				stars.each( function() {
					var ticket,
						star = $(this);

					ticket = parseInt( star.parent().siblings('td.ticket').find('a').text().replace('#', ''), 10 );
					tickets.push( ticket );
					star.data( 'ticket', ticket );
				});

				$.ajax({
					type: 'POST',
					url: endpoint,
					xhrFields: { withCredentials: true },
					data: {
						'trac-ticket-subs' : true,
						'tickets' : tickets
					}
				}).success( function( data ) {
					if ( ! data.success ) {
						return;
					}

					if ( data.data.nonce ) {
						_nonce = data.data.nonce;
					}

					stars.each( function() {
						if ( -1 !== $.inArray( $(this).data( 'ticket' ), data.data.tickets ) ) {
							$(this).toggleClass( 'dashicons-star-empty dashicons-star-filled' );
						}
					}).removeClass('loading').on( 'click', function() {
						var action, count, delta,
							star = $(this);
						star.toggleClass( 'dashicons-star-empty dashicons-star-filled' );
						action = star.hasClass('dashicons-star-filled') ? 'subscribe' : 'unsubscribe';
						delta = 'subscribe' === action ? 1 : -1;
						save( action, star.data( 'ticket' ) );

						count = parseInt( star.prev().text(), 10 );
						if ( isNaN( count ) ) {
							count = 0;
						}
						count += delta;
						star.prev().text( count ? count : '' );
					});
				});
			}

			return {
				init: init
			};
		}()),

		githubPRs: (function() {
			var apiEndpoint = 'https://api.wordpress.org/dotorg/trac/pr/',
				authenticated = !! ( wpTracCurrentUser && wpTracCurrentUser !== "anonymous" ),
				trac = false, ticket = 0,
				primaryGitRepo, primaryGitRepoDesc, container;

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
				var $warning = $("#warning.system-message:contains('GITHUBTICKETS')");
				if ( $warning.length ) {
					renderReportLoadGitHubTickets( $warning );
				}

				// This seems to be the easiest place to find the current Ticket ID..
				var canonical = $( 'link[rel="canonical"]' ).prop( 'href' );
				if ( canonical ) {
					ticket = canonical.match( /\/ticket\/(\d+)$/ )[1];
				}

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
				$('img[src*="i0.wp.com/github.com/"]').each( function() {
					var $this = $(this), $parent = $this.parent('a');
					$this.removeAttr('crossorigin'); // We trust GitHub for these images.
					$this.prop( 'src', $this.prop('src').replace(/i0\.wp\.com/, '' ) );
					$this.prop( 'alt', $this.prop('alt').replace(/i0\.wp\.com/, '' ) );
					$this.prop( 'title', $this.prop('title').replace(/i0\.wp\.com/, '' ) );
					$parent.prop( 'href', $parent.prop('href').replace(/i0\.wp\.com/, '' ) );
				} );
			}

			function fetchPRs() {
				$.ajax(
					apiEndpoint
						+ '?trac=' + trac
						+ '&ticket=' + ticket
						+ ( authenticated ? '&authenticated=1' : '' )
						+ ( ( authenticated && 'URL' in window ) ? '&_lastmod=' + ( new URL( jQuery('a.timeline').last().prop('href') ) ).searchParams.get( 'from' ) : '' )
				).success( function( data ) {
					// Update the number
					container.find( 'h3 .trac-count' ).removeClass( 'hidden' ).find( 'span' ).text( data.length );

					var prContainer = container.find( '.pull-requests' );
					if ( data.length ) {
						// Remove the placeholder.
						prContainer.find( '.loading' ).remove();

						// Render the PRs
						for ( var i in data ) {
							renderPR( prContainer, data[i] );
						}
					} else {
						// Change the loading placeholder
						prContainer.find( '.loading div' ).html( 'To link a Pull Request to this ticket, create a new Pull Request in the <a href="https://github.com/' + primaryGitRepo + '">' + primaryGitRepoDesc + '</a> and include this ticket’s URL in the description.' );
					}
				});
			}

			function renderAddSection() {
				// Add the Pull Requests section, #attachments is only present if authenticated or there exists uploads.
				var afterDiv = $( '#attachments' );
				if ( ! afterDiv.length ) {
					afterDiv = $( '#commits' );
				}

				afterDiv.after(
					'<div id="github-prs">' +
						'<h3 class="foldable"><a id="section-pr" href="#section-pr">Pull Requests <span class="trac-count hidden">(<span></span>)</span></a></h3>' +
						'<ul class="pull-requests">' +
						'<li class="loading"><div>Loading…</div></li>' +
						'</ul>' +
					'</div>'
				);
				// keep this for later.
				container = $( '#github-prs' );

				// Make the section collapse.
				container.find( '#section-pr' ).on( 'click', function() {
					var $div = $( this.parentNode.parentNode ).toggleClass( 'collapsed' );
					return ! $div.hasClass( 'collapsed' );
				} );
			}

			function renderReportLoadGitHubTickets( $warning ) {
				var user = wpTracCurrentUser,
					match = document.location.search.match( /USER=([^&]+)/ );
				if ( match ) {
					user = match[1];
				}

				// Logged out requests without a user context.
				if ( 'anonymous' === user ) {
					$warning.remove();
					return;
				}

				$warning.html(
					'<strong>Warning:</strong> Tickets with an attached GitHub PRs not included <button>Load PRs</button>'	
				);

				$warning.on( 'click', function() {
					$(this).find('button').prop( 'disabled', 'disabled' ).text( 'Please wait..' );

					$.ajax(
						apiEndpoint
							+ '?trac=' + trac
							+ '&author=' + user
							+ ( authenticated ? '&authenticated=1' : '' )
					).success( function( ticketList ) {
						document.location = document.location.toString() +
							( document.location.search ? '&' : '?' ) +
							'GITHUBTICKETS=' + ticketList.join(',');
					} );
				} )

			}

			// Logic to determine what the PRs status is
			function prStatus( data ) {
				var stack = [],
					emojiState = '';

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
							(
								data.check_runs &&
								'failed' == Object.values( data.check_runs ).reduce( function( result, element ) {
									return 'failed' == element ? element : result;
								}, 'no-reviews' )
							)
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
					for ( var provider in data.check_runs ) {
						switch ( data.check_runs[ provider ] ) {
							case 'in_progress':
								stack.push( provider + ' running' );
								break;
							case 'failed':
								emojiState = '❌';
								stack.push( provider );
								break;
							case 'success':
								continue;
							default:
								stack.push( provider + ' ' + data.check_runs[ provider ] );
								break;
						}
					}
				}

				// Changes requested?
				if ( data.reviews ) {
					if ( data.reviews.APPROVED ) {
						emojiState = '✅';
						stack.push(
							$('<span>Approved</span>').prop(
								'title',
								'Changes approved by: ' + data.reviews.APPROVED.join(', ')
							)[0].outerHTML
						);
					}
					if ( data.reviews.CHANGES_REQUESTED ) {
						emojiState = '❌';
						stack.push(
							$('<span>Changes Requested</span>').prop(
								'title',
								'Changes requested by: ' + data.reviews.CHANGES_REQUESTED.join(', ')
							)[0].outerHTML
						);
					}
				}

				return emojiState + ' ' + stack.join( ', ' );
			}

			function renderPR( container, data ) {
				// Not the nicest, but it works and escapes things properly if given correct inputs.
				var htmlElement = function( element, attributes, text = '' ) {
					return $( '<p>' ).append(
						$( '<' + element + '/>', attributes ).text( text )
					).html();
				}

				// Strip off any ticket numbers from the start of the PR title for display.
				data.title = data.title.replace( /^#\d+\s*/, '' );

				container.append(
					'<li>' +
					'<div>' +
						htmlElement(
							'a',
							{ href: data.changes.html_url, title: data.title },
							'#' + data.number + ' ' + data.title
						) +
						' by ' +
						htmlElement( 'a', { href: data.user.url }, '@' + data.user.name ) +
					'</div>' +
					'<div>' +
						prStatus( data ) +
					'</div>' +
					'<div>' +
						htmlElement( 'ins', {}, '+' + data.changes.additions ) +
						'&nbsp;' +
						htmlElement( 'del', {}, '-' + data.changes.deletions ) +
					'</div>' +
					'<div>' +
						htmlElement( 'a', { href: data.changes.patch_url, class: 'button' }, 'View patch' ) +
						'&nbsp;' +
						htmlElement( 'a', { href: data.changes.html_url, class: 'button' }, 'View PR' ) +
					'</div>' +
					'</li>'
				);
			}

			return {
				init: init
			};
		}()),

		suggestNotGeneral: ( function() {
			var enabled = true,
				skipWords = [ 'and', 'any', 'all', 'the', 'for', 'get', 'plugins', 'general', 'wordpress' ],
				generalCategories = [ 'General' ],
				componentWords = {},
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
				const components = jQuery( '#field-component option' ).get().map( opt => opt.value ),
					hasDefaultCat = generalCategories.filter( value => components.includes( value ) );

				if ( ! hasDefaultCat ) {
					return;
				}

				generateComponentWords();

				$( '#field-description,#field-summary,#field-component' ).on( 'blur', maybeSuggest );

				// Disable once the user hits the component option.
				$( '#field-component' ).on( 'change', function() {
					// If they selected a general category keep nagging.
					enabled = ( -1 !== generalCategories.indexOf( $(this).val() ) );

					if ( ! enabled && noticeDiv ) {
						noticeDiv.remove();
						noticeDiv = false;
					}
				} );
			}

			function maybeSuggest() {
				var matchText = $( '#field-summary' ).val().toLowerCase() + ' ' + $( '#field-description' ).val().toLowerCase(),
					matchingWords = [],
					matchingComponents = [];

				if ( ! enabled || ! matchText.length ) {
					return;
				}

				for ( const [word, components] of Object.entries( componentWords ) ) {
					if ( matchText.includes( word ) ) {
						matchingWords.push( word );
					}
				}

				// Longest match first.
				matchingWords.sort( (a,b) => a.length > b.length ? -1 : 1 );

				matchingWords.forEach( (word) => {
					componentWords[ word ].forEach( (component) => {
						if ( -1 == matchingComponents.indexOf( component ) ) {
							matchingComponents.push( component );
						}
					});
				} );

				if ( ! noticeDiv ) {
					noticeDiv = $( '<div id="componentSuggest"/>' ).insertBefore( $( '.buttons').first() );

					noticeDiv.on( 'click', 'a.component', function(e) {
						e.preventDefault();
						const component = $(this).text();

						$( `#field-component option[value="${component}"]` ).prop( 'selected', 'selected' ).change();
					} );
				}
				noticeDiv.html( getNoticeHTML( matchingComponents ) );
			}

			function getNoticeHTML( matchingComponents ) {
				var hasMatches = matchingComponents.length > 0,
					template = $(
						'<div class="wp-notice"><p><strong>Have you selected the right component?</strong></p>' +
						"<p>You've not yet selected a component. " +
							'Please check the "Component" option above' +
							( hasMatches ? ' or select from one of the following:' :  '.' ) +
						'</p>' +
						'</div>'
					),
					ulList;

				if ( hasMatches ) {
					ulList = $('<ul/>' );

					matchingComponents.forEach( (component) => {
						ulList.append( $( `<li><a href="#" class="component">${component}</a></li>` ) );
					} );
					template.append( ulList );
				}

				return template;
			}

			function generateComponentWords() {
				$( '#field-component option' ).each( function() {
					const component = $(this).val(),
						words = component.split( /[^A-Za-z0-9\.']+/ )

					// Never suggest General..
					if ( 'General' === component ) {
						return;
					}

					if ( words.length > 1 ) {
						words.push( component );
					}

					// If it's a plural, add the non-plural form.
					words.forEach( (word) => {
						if ( 's' === word.substr( -1 ) ) {
							words.push( word.substr( 0, word.length - 1 ) );
						}
					} );

					words.forEach( (word) => {
						if ( ! word ) return;

						word = word.toLowerCase();

						if ( component != word && -1 !== skipWords.indexOf( word ) ) return;

						if ( ! ( word in componentWords ) ) {
							componentWords[ word ] = [];
						}

						componentWords[ word ].push( component );
					} );
				} );

				return componentWords;
			}

			return {
				init: init
			}

		}() ),

		patchTracFor122Changes: function() {
			// TODO: This needs to be removed, the Trac assets on s.w.org are probably outdated and need updating.
			console.log( "wp-trac: Applying compat patches for Trac 1.2.2" );
			// From Trac 1.2.2 threaded_comments.js:
			window.applyCommentsOrder = window.applyCommentsOrder || function() {}

			// Add Params to s.w.org scripts and stylesheets when loaded dynamically.
			var cache_buster = jQuery('script[src^="https://s.w.org"][src*="v="]').attr('src');
			if ( cache_buster ) {
				cache_buster = cache_buster.match(/v=([0-9]+)$/)[1];
			}
			var maybe_add_cache_buster = function( href ) {
				if ( cache_buster && href.match( /https:\/\/s.w.org/i ) && href.match( /[.](css|js)$/ ) ) {
					href += '?v=' + cache_buster;
				}
				return href;
			}
			var oldLoadScript = $.loadScript;
			$.loadScript = function(href, type, charset) {
				return oldLoadScript( maybe_add_cache_buster( href ), type, charset );
			}
			var oldLoadStylesheet = $.loadStyleSheet;
			$.loadStyleSheet = function( href ) {
				return oldLoadStylesheet( maybe_add_cache_buster( href ) );
			}

			// From Trac 1.2.2 trac.js:
			$.loadScript                = $.loadScript                || function() {}
			$.fn.exclusiveOnClick       = $.fn.exclusiveOnClick       || function() {}
			$.fn.addSelectAllCheckboxes = $.fn.addSelectAllCheckboxes || function() {}
			$.fn.disableOnSubmit        = $.fn.disableOnSubmit        || function() {}
			$.fn.disableSubmit          = $.fn.disableSubmit          || function() {}
		},

		disableTracAutoFocus: function() {
			// Disable the Trac autofocus which scrolls past the intro to creating tickets.
			$(".trac-autofocus").removeClass( 'trac-autofocus' );
		}

	};

	$(document).ready( wpTrac.init );

	// Perform this as soon as this file loads.
	wpTrac.disableTracAutoFocus();
	wpTrac.patchTracFor122Changes();

})(jQuery);

/**
 * String.prototype.replaceAll() polyfill. For Internet Explorer.
 * 
 * https://gomakethings.com/how-to-replace-a-section-of-a-string-with-another-one-with-vanilla-js/
 * https://vanillajstoolkit.com/polyfills/stringreplaceall/
 *
 * @author Chris Ferdinandi
 * @license MIT
 */
if ( ! String.prototype.replaceAll ) {
	String.prototype.replaceAll = function(str, newStr) {

		// If a regex pattern
		if ( Object.prototype.toString.call(str).toLowerCase() === '[object regexp]' ) {
			return this.replace(str, newStr);
		}

		// If a string
		return this.replace(new RegExp(str, 'g'), newStr);

	};
}
