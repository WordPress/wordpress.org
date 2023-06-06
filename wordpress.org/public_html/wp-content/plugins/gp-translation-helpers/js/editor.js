/* global $gp, $gp_translation_helpers_editor, wpApiSettings, $gp_comment_feedback_settings, console, $gp_editor_options  */
/* eslint camelcase: "off" */
jQuery( function( $ ) {
	var focusedRowId = '';
	// When a user clicks on a sidebar tab, the visible tab and div changes.
	$gp.editor.table.on( 'click', '.sidebar-tabs li', function() {
		var tab = $( this );
		var tabId = tab.attr( 'data-tab' );
		var divId = tabId.replace( 'tab', 'div' );
		var originalId = tabId.replace( /[^\d-]/g, '' ).replace( /^-+/g, '' );
		change_visible_tab( tab );
		change_visible_div( divId, originalId );
	} );

	// When a new translation row is opened (with double click, clicking in the "Details" button,
	// or with the hotkeys), the translation textarea is focused, so the tabs (header tabs and
	// divs with the content) for the right sidebar are updated.
	$gp.editor.table.on( 'focus', 'tr.editor textarea.foreign-text', function() {
		var tr = $( this ).closest( 'tr.editor' );
		var rowId = tr.attr( 'row' );
		var translation_status = tr.find( '.panel-header' ).find( 'span' ).html();

		if ( focusedRowId === rowId ) {
			return;
		}
		focusedRowId = rowId;
		loadTabsAndDivs( tr );
		if ( $gp_editor_options.can_approve && ( 'waiting' === translation_status || 'fuzzy' === translation_status ) ) {
			fetchOpenAIReviewResponse( rowId, tr, false );
		}
	} );

	$gp.editor.table.on( 'click', 'a.retry-auto-review', function( event ) {
		var tr = $( this ).closest( 'tr.editor' );
		var rowId = tr.attr( 'row' );
		event.preventDefault();
		tr.find( '.openai-review .auto-review-result' ).html( '' );
		tr.find( '.openai-review .suggestions__loading-indicator' ).show();
		fetchOpenAIReviewResponse( rowId, tr, true );
	} );

	// Shows/hides the reply form for a comment in the discussion.
	$gp.editor.table.on( 'click', 'a.comment-reply-link', function( event ) {
		var commentId = $( this ).attr( 'data-commentid' );
		event.preventDefault();
		$( '#comment-reply-' + commentId ).toggle().find( 'textarea' ).focus();
		if ( $gp_translation_helpers_editor.reply_text === $( this ).text() ) {
			$( this ).text( $gp_translation_helpers_editor.cancel_reply_text );
		} else {
			$( this ).text( $gp_translation_helpers_editor.reply_text );
		}
		return false;
	} );

	// Creates a shadow post, with a format like "gth_original9753" (the number changes)
	// to avoid creating empty posts (without comments).
	function createShadowPost( formdata, submitComment ) {
		var data = {
			action: 'create_shadow_post',
			data: formdata,
			_ajax_nonce: wpApiSettings.nonce,
		};

		$.ajax(
			{
				type: 'POST',
				url: wpApiSettings.admin_ajax_url,
				data: data,
			}
		).done(
			function( response ) {
				formdata.post = response.data;
				submitComment( formdata );
			}
		);
	}

	// Sends the new comment or the reply to an existing comment.
	$gp.editor.table.on( 'submit', '.meta.discussion .comment-form', function( e ) {
		var $commentform = $( e.target );
		var postId = $commentform.attr( 'id' ).split( '-' )[ 1 ];
		var divDiscussion = $commentform.closest( '.meta.discussion' );
		var rowId = divDiscussion.attr( 'data-row-id' );
		var requestUrl = $gp_translation_helpers_editor.translation_helper_url + rowId + '?nohc';

		var submitComment = function( formdata ) {
			$.ajax( {
				url: wpApiSettings.root + 'wp/v2/comments',
				method: 'POST',
				beforeSend: function( xhr ) {
					xhr.setRequestHeader( 'X-WP-Nonce', wpApiSettings.nonce );
				},
				data: formdata,
			} ).done( function( response ) {
				if ( 'undefined' !== typeof ( response.data ) ) {
					// There's probably a better way, but response.data is only set for errors.
					// TODO: error handling.
				} else {
					$commentform.find( 'textarea[name=comment]' ).val( '' );
					$.getJSON( requestUrl, function( data ) {
						$( '[data-tab="sidebar-tab-discussion-' + rowId + '"]' ).html( 'Discussion&nbsp;(' + data[ 'helper-translation-discussion-' + rowId ].count + ')' );
						$( '#sidebar-div-discussion-' + rowId ).html( data[ 'helper-translation-discussion-' + rowId ].content );
					} );
				}
			} );
		};

		var formdata = {
			content: $commentform.find( 'textarea[name=comment]' ).val(),
			parent: $commentform.find( 'input[name=comment_parent]' ).val(),
			post: postId,
			meta: {
				translation_id: $commentform.find( 'input[name=translation_id]' ).val(),
				locale: $commentform.find( 'input[name=comment_locale]' ).val(),
				comment_topic: $commentform.find( 'select[name=comment_topic]' ).val(),
			},
		};
		e.preventDefault();
		e.stopImmediatePropagation();

		$( 'input.submit' ).prop( 'disabled', true );

		if ( ! formdata.meta.translation_id ) {
			formdata.meta.translation_id = 0;
		}

		if ( formdata.meta.locale ) {
			/**
			 * Set the locale to an empty string if option selected has value 'typo' or 'context'
			 * to force comment to be posted to the English discussion page
			 */
			if ( formdata.meta.comment_topic === 'typo' || formdata.meta.comment_topic === 'context' ) {
				formdata.meta.locale = '';
			}
		}

		if ( isNaN( Number( postId ) ) ) {
			createShadowPost( formdata, submitComment );
		} else {
			submitComment( formdata );
		}

		return false;
	} );

	// Copies the translation from another language to the current translation.
	$gp.editor.table.on( 'click', 'button.sidebar-other-locales', function() {
		var textToCopy = $( this ).closest( 'li' ).find( 'a' ).text();
		var textareaToPaste = $( this ).closest( '.editor' ).find( 'textarea.foreign-text' );
		var selectionStart = textareaToPaste.get( 0 ).selectionStart;
		var selectionEnd = textareaToPaste.get( 0 ).selectionEnd;
		var textToCopyLength = textToCopy.length;
		textareaToPaste.val( textareaToPaste.val().substring( 0, selectionStart ) +
			textToCopy +
			textareaToPaste.val().substring( selectionEnd, textareaToPaste.val().length ) );
		selectionStart += textToCopyLength;
		selectionEnd += textToCopyLength;
		if ( selectionEnd > textareaToPaste.val().length ) {
			selectionEnd = textareaToPaste.val().length;
		}
		textareaToPaste.get( 0 ).setSelectionRange( selectionStart, selectionEnd );
	} );

	// Fires the double click event in the first row of the table if we only
	// have a row, because GlotPress opens the first editor if the current
	// table has only one, so with the double click we load the content sidebar.
	// eslint-disable-next-line vars-on-top
	var previewRows = $gp.editor.table.find( 'tr.preview' );
	if ( 1 === previewRows.length ) {
		$( 'tr.preview td' ).trigger( 'dblclick' );
	}

	/**
	 * Hides all tabs and show one of them, the last clicked.
	 *
	 * @param {Object} tab The selected tab.
	 */
	function change_visible_tab( tab ) {
		var tabId = tab.attr( 'data-tab' );
		tab.siblings().removeClass( 'current' );
		tab.parents( '.sidebar-tabs ' ).find( '.helper' ).removeClass( 'current' );
		tab.addClass( 'current' );

		$( '#' + tabId ).addClass( 'current' );
	}

	/**
	 * Hides all divs and show one of them, the last clicked.
	 *
	 * @param {string} tabId      The select tab id.
	 * @param {number} originalId The id of the original string to translate.
	 */
	function change_visible_div( tabId, originalId ) {
		$( '#sidebar-div-meta-' + originalId ).hide();
		$( '#sidebar-div-discussion-' + originalId ).hide();
		$( '#sidebar-div-history-' + originalId ).hide();
		$( '#sidebar-div-other-locales-' + originalId ).hide();
		$( '#' + tabId ).show();
	}

	/**
	 * Adds a button to each translation from another locales.
	 *
	 * @param {string} sidebarDiv The div where we add the buttons.
	 */
	function add_copy_button( sidebarDiv ) {
		var lis = $( sidebarDiv + ' .other-locales li' );
		lis.each( function() {
			var html = $( this ).html();
			html += '<button class="sidebar-other-locales button is-small copy-suggestion"> Copy </button>';
			$( this ).html( html );
		} );
	}

	/**
	 * Load the content in the tabs (header tab and content) for the opened row.
	 *
	 * @param {Object} element The element that triggers the action.
	 */
	function loadTabsAndDivs( element ) {
		var originalId = element.closest( 'tr' ).attr( 'id' ).substring( 7 );
		var requestUrl = $gp_translation_helpers_editor.translation_helper_url + originalId + '?nohc';
		$.getJSON( requestUrl, function( data ) {
			$( '[data-tab="sidebar-tab-discussion-' + originalId + '"]' ).html( 'Discussion&nbsp;(' + data[ 'helper-translation-discussion-' + originalId ].count + ')' );
			$( '#sidebar-div-discussion-' + originalId ).html( data[ 'helper-translation-discussion-' + originalId ].content );
			$( '[data-tab="sidebar-tab-history-' + originalId + '"]' ).html( 'History&nbsp;(' + data[ 'helper-history-' + originalId ].count + ')' );
			$( '#sidebar-div-history-' + originalId ).html( data[ 'helper-history-' + originalId ].content );
			$( '[data-tab="sidebar-tab-other-locales-' + originalId + '"]' ).html( 'Other&nbsp;locales&nbsp;(' + data[ 'helper-other-locales-' + originalId ].count + ')' );
			$( '#sidebar-div-other-locales-' + originalId ).html( data[ 'helper-other-locales-' + originalId ].content );
			add_copy_button( '#sidebar-div-other-locales-' + originalId );
		} );
	}

	/**
	 * Fetch translation review from OpenAI.
	 *
	 * @param {string}  rowId      The row-id attribute of the current row.
	 * @param {string}  currentRow The current row.
	 * @param {boolean} isRetry    The current row.
	 */
	function fetchOpenAIReviewResponse( rowId, currentRow, isRetry ) {
		var payload = {};
		var data = {};
		var original_str = currentRow.find( '.original' );
		var glossary_prompt = '';
		var translationId = $gp.editor.translation_id_from_row_id( rowId );

		$.each( $( original_str ).find( '.glossary-word' ), function( k, word ) {
			$.each( $( word ).data( 'translations' ), function( i, e ) {
				glossary_prompt += 'where "' + word.textContent + '" is translated as "' + e.translation + '" when it is a ' + e.pos;
				if ( e.comment ) {
					glossary_prompt += ' (' + e.comment + ')';
				}
				glossary_prompt += ', ';
			} );
		} );

		if ( '' !== glossary_prompt ) {
			glossary_prompt = 'You are required to follow these rules, ' + glossary_prompt + 'for words found in the English text you are translating.';
		}
		payload.locale_slug = $gp_comment_feedback_settings.locale_slug;
		payload.translation_id = translationId;
		payload.glossary_query = glossary_prompt;
		payload.is_retry = isRetry;

		data = {
			action: 'fetch_openai_review',
			data: payload,
			_ajax_nonce: $gp_comment_feedback_settings.nonce,
		};

		$.ajax(
			{
				type: 'POST',
				url: $gp_comment_feedback_settings.url,
				data: data,
			}
		).done(
			function( response ) {
				currentRow.find( '.openai-review .suggestions__loading-indicator' ).hide();
				if ( 200 === response.data.status ) {
					currentRow.find( '.openai-review .auto-review-result' ).html( '<h4>Auto-review by ChatGPT' ).append( $( '<span/>' ).text( response.data.review + ' (' + response.data.time_taken.toFixed( 2 ) + 's)' ) );
				} else {
					currentRow.find( '.openai-review .auto-review-result' ).text( 'Error ' + response.data.status + ' : ' + response.data.error );
				}
				currentRow.find( '.openai-review .auto-review-result' ).append( ' <a href="#" class="retry-auto-review">Retry</a>' );
			}
		).fail(
			function( xhr, msg ) {
				/* eslint no-console: ["error", { allow: ["error"] }] */
				console.error( data );
				msg = 'An error has occurred';
				if ( xhr.responseText ) {
					msg += ': ' + xhr.responseText;
				}
				msg += '. Please, take a screenshot of the output in the browser console, send it to the developers, and reload the page to see if it works.';
				$gp.notices.error( msg );
			}
		);
	}
} );
