var wpTrac, coreKeywordList, gardenerKeywordList;

(function($){

	coreKeywordList = {
		'has-patch' : 'Proposed solution attached and ready for review.',
		'needs-patch' : 'Ticket needs a new patch.',
		'needs-refresh' : 'Patch no longer applies cleanly and needs to be updated.',
		'reporter-feedback' : 'Feedback is needed from the reporter.',
		'dev-feedback' : 'Feedback is needed from a core developer.',
		'2nd-opinion' : 'A second opinion is desired for the problem or solution.',
		'close' : 'The ticket is a candidate for closure.',
		'needs-testing' : 'Patch has a particular need for testing.',
		'ui-focus' : 'Ticket is focused on user interface changes.',
		'ui-feedback' : 'Feedback is needed from the user interface perspective, generally from the UI team.',
		'ux-feedback' : 'Feedback is needed from the user experience perspective, generally from a UX lead.',
		'needs-ui' : 'Needs user interface work, generally from the UI team.',
		'needs-unit-tests' : 'Ticket has a particular need for unit tests.',
		'needs-docs' : 'Inline documentation is needed.',
		'docs-feedback' : 'Feedback is needed from the docs team.',
		'rtl-feedback' : 'Feedback or work is needed from the RTL perspective.',
		'needs-codex' : 'The Codex needs to be updated or expanded.',
		'commit' : 'Patch is a suggested commit candidate.',
		'early' : 'Ticket should be addressed early in the next dev cycle.',
		'i18n-change' : 'A string change, used only after string freeze.',
		'good-first-bug': 'This ticket is great for a new contributor to work on, generally because it is easy or well-contained.'
	};

	gardenerKeywordList = [ 'commit', 'early', 'i18n-change' ];

	wpTrac = {

		gardener: typeof wpBugGardener !== 'undefined',

		init: function() {
			wpTrac.hacks();
			if ( ! $(document.body).hasClass( 'plugins' ) ) {
				wpTrac.workflow.init();
			}
			wpTrac.nonGardeners();
		},

		// These ticket hacks need to be re-run after ticket previews.
		postPreviewHacks: function() {
			// Automatically preview images.
			$('li.trac-field-attachment').each( function() {
				var href, el, image, appendTo,
					li = $(this);
				el = li.find('.trac-rawlink');
				href = el.attr('href');
				if ( ! href.match(/\.(jpg|jpeg|png|gif)$/i) ) {
					return;
				}
				appendTo = li.parent().parent(); // div.change
				image = new Image;
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

			// Restore the 'Delete' comment buttons, if any. The Trac plugin places them in a location we don't want.
			// See https://meta.trac.wordpress.org/changeset/204.
			$('div.change').children('.trac-ticket-buttons').each( function() {
				var el = $(this);
				el.children().appendTo( el.prev().children('.trac-ticket-buttons') ).end().end().remove();
			});
		},

		hacks: function() {
			var content = $( '#content' );

			// Change 'Comments' and 'Stars' columns to dashicons glyphs to save space
			$('th a[href*="sort=Comments"]').html('<div class="dashicons dashicons-admin-comments"></div>');
			$('th a[href*="sort=Stars"]').html('<div class="dashicons dashicons-star-empty"></div>');

			if ( content.hasClass( 'ticket' ) ) {
				if ( $(document.body).hasClass( 'core' ) ) {
					wpTrac.coreToMeta();
				}

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
			}

			// Add After the Deadline (only add it if it loaded)
			if ( $.isFunction( $.fn.addProofreader ) ) {
				$('textarea').addProofreader();
				$('.AtD_proofread_button').each(function() {
					$(this).parent().appendTo( $(this).parents('fieldset').find('.wikitoolbar') );
				});
			}

			// Force 'Attachments' and 'Modify Ticket' to be shown
			$('#attachments').removeClass('collapsed');
			$("#modify").parent().removeClass('collapsed');

			// Push live comment previews above 'Modify Ticket'
			$('#ticketchange').insertAfter('#trac-add-comment');

			// Toggle the security notice on component change, if rendered
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

			// Allow action text inputs to be clicked directly.
			$('#action')
				.find('input[type=text]').enable().focus( function() {
					$(this).siblings('input[type=radio]').click();
				}).end()
				.find('input[name=action]').unbind('click').end()
				.find('div').has('select').find('input[type=radio]').change( function() {
					$(this).siblings('select').enable();
				});

			// Clear the milestone on wontfix, duplicate, worksforme, invalid
			var milestone = $('#field-milestone');
			if ( ! milestone.prop('disabled') ) {
				$('#propertyform').submit( function() {
					var action = $('input[name=action]:checked').val();
					if ( 'duplicate' === action || ( 'resolve' === action && 'fixed' !== $('#action_resolve_resolve_resolution').val() ) ) {
						milestone.val('');
					}
				});
			}

			// Add a 'Show only commits/attachments' view option to tickets
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
						// Best we can do to target a
						.has('.comment p a.changeset')
							.has('.comment div.message p a.ticket')
							.show()
						.end()
					.end()
					.has('li.trac-field-attachment')
					.show();
			});

			// 'User Interface' preferences tab => 'Help Links' (and removes icons-only setting)
			var uitab = $('#tab_userinterface');
			if ( uitab.length ) {
				if ( uitab.hasClass('active') ) {
					uitab.text('Help Links');
					$('input[name="ui.use_symbols"]').closest('div.field').remove();
				} else {
					uitab.find('a').text('Help Links');
				}
			}
		},

		// If we're not dealing with a trusted bug gardener:
		nonGardeners: function() {
			if ( wpTrac.gardener ) {
				return;
			}

			var version,
				elements = {},
				remove = true;

			// If we're on /newticket (based on the field-owner check), declutter.
			if ( $('#field-owner').length ) {
				$('#field-priority, #field-severity, #field-milestone, #field-cc, #field-keywords').parents('td').hide().prev().hide();
			}

			elements.type = $('#field-type');
			elements.version = $('#field-version');
			version = elements.version.val();

			// Remove task (blessed), or make a task ticket read only.
			if ( 'task (blessed)' === elements.type.val() ) {
				elements.type.after('<input type="hidden" name="field_type" value="task (blessed)" /> task (blessed)')
					.parent().css('vertical-align', 'middle').end()
					.remove();
			} else {
				elements.type.find('option[value="task (blessed)"]').remove();
			}

			// Once a Version is set, remove newer versions.
			if ( version ) {
				elements.version.find('option').each( function() {
					var value = $(this).val();
					if ( version === value )
						remove = false;
					else if ( remove && value )
						$(this).remove();
				});
			}
		},

		coreToMeta: function() {
			var component = $('#field-component');
			if ( window.location.pathname !== '/newticket' ) {
				if ( ! wpTrac.gardener && component.val() !== 'WordPress.org site' ) {
					component.children('option[value="WordPress.org site"]').remove();
				}
				return;
			}

			component.change( function() {
				var toggle = $('input[name="attachment"]').parent().add('.ticketdraft').add('.wp-notice').add('div.buttons');
				if ( $(this).val() !== 'WordPress.org site' ) {
					toggle.show();
					$('.wp-notice.component').remove();
					return;
				}
				toggle.hide();
				$('div.buttons').after( '<div class="wp-notice component"><p><strong>The WordPress.org site now has its own Trac</strong> at ' +
					'<a href="//meta.trac.wordpress.org/">meta.trac.wordpress.org</a>.</p><p>Would you mind opening this ticket over there instead? ' +
					'<a href="//meta.trac.wordpress.org/newticket" id="new-meta-ticket">Click here</a> to copy your summary and description over.</p></div>' );
			});
	
			$('#propertyform').on( 'click', '#new-meta-ticket', function() {
				var url, href = $(this).attr( 'href' );
				url = href + '?' + $.param({ summary: $('#field-summary').val(), description: $('#field-description').val() });
				if ( url.length > 1500 ) {
					url = href + '?' + $.param({
						summary: $('#field-summary').val(),
						description: "(Couldn't copy over your description as it was too long. Please paste it here. Your old window was not closed.)"
					});
					window.open( url );
				} else {
					window.location.href = url;
				}
				return false;
			});
		},

		workflow: (function() {
			var keywords = {},
				originalKeywords = {},
				elements = {};

			return {
				init: function() {
					elements.hiddenEl = $('#field-keywords');
					if ( ! elements.hiddenEl.length ) {
						return;
					}

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
					elements.bin.on( 'click', 'a', function(e) {
						e.preventDefault();
						wpTrac.workflow.removeKeyword( $(this).parent() );
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

					// Manual link.
					$('#edit-keywords').click( function() {
						elements.hiddenEl.show().focus();
						$(this).hide();
						elements.hiddenEl.change( wpTrac.workflow.populate );
					});
				},

				// Generates the workflow template.
				template : function() {
					var container = elements.hiddenEl.parent(), html, labelWidth;

					// Necessary to keep everything in line. The + 4 is a careful CSS balance.
					labelWidth = container.prev().width() + 4;

					// Rearrange the table to suit our needs.
					container.prev().detach().end()
						.attr('colspan', '2').addClass('has-js')
						.parents('table').css('table-layout', 'fixed');

					// If the owner field exists, then we're on /newticket. Remove it.
					$('#field-owner').parents('tr').remove();

					html = '<a id="edit-keywords">manual</a>';
					html += '<div><label id="keyword-label" for="keyword-add" style="width:' + labelWidth + 'px">Workflow Keywords:</label>';
					html += '<select id="keyword-add"><option value=""> - Add - </option></select></div>';
					html += '<div id="keyword-bin"></div>';
					container.prepend( html );
					elements.bin = $('#keyword-bin');

					// Walk in the footsteps of Firefox autocomplete's trail of destruction,
					// tidying the radio buttons in its wake. See #WP17051.
					if ( $.browser.mozilla ) {
						$('#action input:radio').each( function() {
							this.checked = this.defaultChecked;
						});
					}
				},

				// Populates the keywords and dropdown.
				populate : function() {
					// For repopulation. Starting over.
					if ( elements.bin.find('span').length )
						elements.bin.empty();

					// Replace commas, collapse spaces, trim, then split by space.
					keywords = $.trim( elements.hiddenEl.val().replace(',', ' ').replace(/ +/g, ' ') ).split(' ');

					// Put our cleaned up version back into the hidden field.
					elements.hiddenEl.val( keywords.join(' ') );

					// If we have a non-empty keyword, let's go through the process of adding the spans.
					if ( 1 !== keywords.length || keywords[0] !== '' ) {
						$.each( keywords, function( k, v ) {
							var html = $('<span />').text(v).attr('data-keyword', v).prepend('<a href="#" />');
							if ( v in coreKeywordList )
								html.attr('title', coreKeywordList[v]);
							html.appendTo( elements.bin );
						});
					}

					// Populate the dropdown.
					if ( elements.add ) {
						elements.add.empty();
					} else {
						elements.add = $('#keyword-add');
					}

					$.each( coreKeywordList, function( k, v ) {
						// Don't show special (permission-based) ones.
						if ( ! wpTrac.gardener && -1 !== $.inArray( k, gardenerKeywordList ) )
							return;
						elements.add.append( '<option value="' + k + ( -1 !== $.inArray( k, keywords ) ? '" disabled="disabled">* ' : '">' ) + k + '</option>' );
					});
				},

				// Add a keyword. Takes a sanitized string.
				addKeyword : function( keyword ) {
					if ( ! keyword )
						return;
					var html, title = '';
					// Don't add it again.
					if ( -1 !== $.inArray( keyword, keywords ) )
						return;
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

					// Add it to the bin, and refresh the hidden input.
					html = $('<span />').text(keyword).attr('data-keyword', keyword).prepend('<a href="#" />');
					if ( title )
						html.attr('title', title);
					html.appendTo( elements.bin );
					elements.hiddenEl.val( keywords.join(' ') );
				},

				// Remove a keyword. Takes a jQuery object of a keyword in the bin, or a sanitized keyword as a string.
				removeKeyword : function( object ) {
					var keyword;
					if ( typeof object === 'string' ) {
						keyword = object;
						object = elements.bin.find('span[data-keyword="' + keyword + '"]');
						if ( ! object.length )
							return;
					} else {
						keyword = object.text();
					}

					keywords = $.grep( keywords, function(v) {
						return v != keyword;
					});

					// Update the core keyword dropdown.
					if ( keyword in coreKeywordList )
						elements.add.find('option[value=' + keyword + ']').prop('disabled', false).text( keyword );
					elements.hiddenEl.val( keywords.join(' ') );
					object.remove();
				},

				// Check on submit that we're not just re-ordering keywords.
				// Otherwise, Trac flips out and adds a useless 'Keywords changed from X to X' marker.
				submit : function(e) {
					if ( keywords.length !== originalKeywords.length )
						return;
					var testKeywords = $.grep( keywords, function(v) {
						return -1 === $.inArray( v, originalKeywords );
					});
					// If the difference has no length, then restore to the original keyword order.
					if ( ! testKeywords.length )
						elements.hiddenEl.val( originalKeywords.join(' ') );
				}
			}
		}()),

		notifications: (function() {
			var notifications, endpoint, _ticket;

			function init( settings ) {
				$( hide_cc_field );
				if ( ! settings.authenticated ) {
					return;
				}
				endpoint = settings.endpoint;
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
						$( render( data ) );
					}
				});
			}

			function render( data ) {
				$( '#propertyform' ).before( data.data['notifications-box'] );
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
			}

			function save( action, ticket ) {
				ticket = ticket || _ticket;
				$.ajax({
					type: 'POST',
					url: endpoint,
					xhrFields: { withCredentials: true },
					data: {
						'trac-ticket-sub': ticket,
						action: action
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
		}())

	};

	$(document).ready( wpTrac.init );

})(jQuery);
