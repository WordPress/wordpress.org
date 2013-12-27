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
		'i18n-change' : 'A string change, used only after string freeze.'
	};

	gardenerKeywordList = [ 'commit', 'early', 'i18n-change' ];

	wpTrac = {

		keywords : {},
		originalKeywords : {},
		field : {},
		gardener : typeof wpBugGardener !== 'undefined',

		init : function() {
			// Change 'Comments' columns to a dashicons glyph to save space
			$('th a[href*="sort=Comments"]').html('<div class="dashicons dashicons-admin-comments"></div>');

			// Add After the Deadline
			$('textarea').addProofreader();

			$('.AtD_proofread_button').each(function() {
				$(this).parent().appendTo( $(this).parents('fieldset').find('.wikitoolbar') );
			});

			// Force 'Attachments' and 'Modify Ticket' to be shown
			$('#attachments').removeClass('collapsed');
			$("#modify").parent().removeClass('collapsed');

			// Toggle the security notice on component change, if rendered
			if ( $('#wp-security-notice').length ) {
				$('#field-component').change( function() {
					$('#wp-security-notice').toggle( 'Security' === $(this).val() );
				});
			}

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
			wpTrac.field.milestone = $('#field-milestone');
			if ( ! wpTrac.field.milestone.prop('disabled') ) {
				$('#propertyform').submit( function() {
					var action = $('input[name=action]:checked').val();
					if ( 'duplicate' === action || ( 'resolve' === action && 'fixed' !== $('#action_resolve_resolve_resolution').val() ) ) {
						wpTrac.field.milestone.val('');
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

			// Start of Keywords manipulation.
			wpTrac.hiddenEl = $('#field-keywords');
			if ( ! wpTrac.hiddenEl.length )
				return;

			// Designed so the list could have come from another file.
			if ( typeof coreKeywordList === 'undefined' )
				return;

			// If we're not a gardener and we're on /newticket (field-owner check), declutter.
			if ( ! wpTrac.gardener && $('#field-owner').length ) {
				$('#field-priority, #field-severity, #field-milestone, #field-cc, #field-keywords').parents('td').hide().prev().hide();
			}
	
			// Generate the workflow template.
			wpTrac.template();

			wpTrac.field.add = $('#keyword-add');

			// Load up the initial keywords and the dropdown.
			wpTrac.populate();

			// Save these for later.
			wpTrac.originalKeywords = $.merge([], wpTrac.keywords);

			// Catch the submit to see if keywords were simply reordered.
			wpTrac.hiddenEl.parents('form').submit( wpTrac.submit );

			// Keyword removal.
			$('#keyword-bin').delegate('a', 'click', function(e) {
				e.preventDefault();
				wpTrac.removeKeyword( $(this).parent() );
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
				wpTrac.addKeyword( $(this).val() );
				$(this).val('');
			});

			// Manual link.
			$('#edit-keywords').click( function() {
				wpTrac.hiddenEl.show().focus();
				$(this).hide();
				wpTrac.hiddenEl.change( wpTrac.populate );
			});

			// If we're not dealing with a trusted bug gardener:
			if ( ! wpTrac.gardener ) {
				var remove = true, version;
				wpTrac.field.type = $('#field-type');
				wpTrac.field.version = $('#field-version');
				version = wpTrac.field.version.val();

				// Remove task (blessed), or make a task ticket read only.
				if ( 'task (blessed)' === wpTrac.field.type.val() ) {
					wpTrac.field.type.after('<input type="hidden" name="field_type" value="task (blessed)" /> task (blessed)')
						.parent().css('vertical-align', 'middle').end()
						.remove();
				} else {
					wpTrac.field.type.find('option[value="task (blessed)"]').remove();
				}

				// Once a Version is set, remove newer versions.
				if ( version ) {
					wpTrac.field.version.find('option').each( function() {
						var value = $(this).val();
						if ( version === value )
							remove = false;
						else if ( remove && value )
							$(this).remove();
					});
				}
			}
		},

		// Generates the workflow template.
		template : function() {
			var container = wpTrac.hiddenEl.parent(), html, labelWidth;

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

			// Walk in the footsteps of Firefox autocomplete's trail of destruction,
			// tidying the radio buttons in its wake. See WP#17051.
			if ( $.browser.mozilla ) {
				$('#action input:radio').each( function() {
					this.checked = this.defaultChecked;
				});
			}
		},

		// Populates the keywords and dropdown.
		populate : function() {
			var bin = $('#keyword-bin');

			// For repopulation. Starting over.
			if ( bin.find('span').length )
				bin.empty();

			// Replace commas, collapse spaces, trim, then split by space.
			wpTrac.keywords = $.trim( wpTrac.hiddenEl.val().replace(',', ' ').replace(/ +/g, ' ') ).split(' ');

			// Put our cleaned up version back into the hidden field.
			wpTrac.hiddenEl.val( wpTrac.keywords.join(' ') );

			// If we have a non-empty keyword, let's go through the process of adding the spans.
			if ( 1 !== wpTrac.keywords.length || wpTrac.keywords[0] !== '' ) {
				$.each( wpTrac.keywords, function( k, v ) {
					var html = $('<span />').text(v).attr('data-keyword', v).prepend('<a href="#" />');
					if ( v in coreKeywordList )
						html.attr('title', coreKeywordList[v]);
					html.appendTo( bin );
				});
			}

			// Populate the dropdown.
			$.each( coreKeywordList, function( k, v ) {
				// Don't show special (permission-based) ones.
				if ( ! wpTrac.gardener && -1 !== $.inArray( k, gardenerKeywordList ) )
					return;
				wpTrac.field.add.append( '<option value="' + k + ( -1 !== $.inArray( k, wpTrac.keywords ) ? '" disabled="disabled">* ' : '">' ) + k + '</option>' );
			});
		},

		// Add a keyword. Takes a sanitized string.
		addKeyword : function( keyword ) {
			if ( ! keyword )
				return;
			var html, title = '';
			// Don't add it again.
			if ( -1 !== $.inArray( keyword, wpTrac.keywords ) )
				return;
			wpTrac.keywords.push( keyword );

			// Update the dropdown. Core keywords also get a title attribute with their description.
			if ( keyword in coreKeywordList ) {
				wpTrac.field.add.find('option[value=' + keyword + ']').prop('disabled', true).text('* ' + keyword);
				title = coreKeywordList[keyword];
			}

			if ( 'has-patch' === keyword )
				wpTrac.removeKeyword( 'needs-patch' );
			else if ( 'needs-patch' === keyword )
				wpTrac.removeKeyword( 'has-patch' );

			// Add it to the bin, and refresh the hidden input.
			html = $('<span />').text(keyword).attr('data-keyword', keyword).prepend('<a href="#" />');
			if ( title )
				html.attr('title', title);
			html.appendTo( $('#keyword-bin') );
			wpTrac.hiddenEl.val( wpTrac.keywords.join(' ') );
		},

		// Remove a keyword. Takes a jQuery object of a keyword in the bin, or a sanitized keyword as a string.
		removeKeyword : function( object ) {
			var keyword;
			if ( typeof object === 'string' ) {
				keyword = object;
				object = $('#keyword-bin').find('span[data-keyword="' + keyword + '"]');
				if ( ! object.length )
					return;
			} else {
				keyword = object.text();
			}

			wpTrac.keywords = $.grep(wpTrac.keywords, function(v) {
				return v != keyword;
			});
			// Update the core keyword dropdown.
			if ( keyword in coreKeywordList )
				wpTrac.field.add.find('option[value=' + keyword + ']').prop('disabled', false).text( keyword );
			wpTrac.hiddenEl.val( wpTrac.keywords.join(' ') );
			object.remove();
		},

		// Check on submit that we're not just re-ordering keywords.
		// Otherwise, Trac flips out and adds a useless 'Keywords changed from X to X' marker.
		submit : function(e) {
			if ( wpTrac.keywords.length !== wpTrac.originalKeywords.length )
				return;
			var testKeywords = $.grep(wpTrac.keywords, function(v) {
				return -1 === $.inArray( v, wpTrac.originalKeywords );
			});
			// If the difference has no length, then restore to the original keyword order.
			if ( ! testKeywords.length )
				wpTrac.hiddenEl.val( wpTrac.originalKeywords.join(' ') );
		}

	};

	$(document).ready( wpTrac.init );

})(jQuery);
