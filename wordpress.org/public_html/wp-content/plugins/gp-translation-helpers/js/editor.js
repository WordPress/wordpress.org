/* global document, $gp, $gp_translation_helpers_editor, wpApiSettings, $gp_comment_feedback_settings, $gp_editor_options, fetch, TextDecoderStream, URL, URLSearchParams, window, translationHelpersCache, add_amount_to_others_tab */
/* eslint camelcase: "off" */
jQuery( function( $ ) {
	/**
	 * Stores (caches) the content of the translation helpers, to avoid making the query another time.
	 *
	 * @type {Object}
	 */
	// eslint-disable-next-line prefer-const
	window.translationHelpersCache = {};

	let focusedRowId = '';
	// When a user clicks on a sidebar tab, the visible tab and div changes.
	$gp.editor.table.on( 'click', '.sidebar-tabs li', function() {
		const tab = $( this );
		const tabId = tab.attr( 'data-tab' );
		const divId = tabId.replace( 'tab', 'div' );
		const originalId = tabId.replace( /[^\d-]/g, '' ).replace( /^-+/g, '' );
		change_visible_tab( tab );
		change_visible_div( divId, originalId );
	} );

	// When a new translation row is opened (with double click, clicking in the "Details" button,
	// or with the hotkeys), the translation textarea is focused, so the tabs (header tabs and
	// divs with the content) for the right sidebar are updated.
	$gp.editor.table.on( 'focus', 'tr.editor textarea.foreign-text', function() {
		const tr = $( this ).closest( 'tr.editor' );
		const rowId = tr.attr( 'row' );
		const translation_status = tr.find( '.panel-header' ).find( 'span' ).html();
		const nextEditor = $gp.editor.current.nextAll( 'tr.editor' ).first();
		const chatgpt_review_status = JSON.parse( window.localStorage.getItem( 'translate-details-state' ) );
		const chatgpt_review_enabled = ( chatgpt_review_status && 'open' === chatgpt_review_status[ 'details-chatgpt' ] ) || ! chatgpt_review_status;

		if ( focusedRowId === rowId ) {
			return;
		}
		focusedRowId = rowId;
		loadTabsAndDivs( tr );

		if ( nextEditor.length ) {
			cacheTranslationHelpersForARow( nextEditor );
		}

		if ( chatgpt_review_enabled && $gp_comment_feedback_settings.openai_key && $gp_editor_options.can_approve && ( 'waiting' === translation_status || 'fuzzy' === translation_status ) ) {
			fetchOpenAIReviewResponse( rowId, tr, false );
		} else {
			tr.find( '.details-chatgpt, .openai-review' ).hide();
		}
	} );

	$gp.editor.table.on( 'click', 'a.retry-auto-review', function( event ) {
		const tr = $( this ).closest( 'tr.editor' );
		const rowId = tr.attr( 'row' );
		event.preventDefault();
		tr.find( '.openai-review .auto-review-result' ).html( '' );
		tr.find( '.openai-review .suggestions__loading-indicator' ).show();
		fetchOpenAIReviewResponse( rowId, tr, true );
	} );

	$( 'details.details-chatgpt' ).on( 'toggle', function() {
		const tr = $( this ).closest( 'tr.editor' );
		const rowId = tr.attr( 'row' );
		if ( $( this ).prop( 'open' ) ) {
			tr.find( '.openai-review' ).show();
			if ( tr.find( '.openai-review .auto-review-result' ).children().length ) {
				return;
			}
			tr.find( '.openai-review .auto-review-result' ).html( '' );
			tr.find( '.openai-review .suggestions__loading-indicator' ).show();
			fetchOpenAIReviewResponse( rowId, tr, true );
		} else {
			tr.find( '.openai-review' ).hide();
		}
	} );

	// Shows/hides the reply form for a comment in the discussion.
	$gp.editor.table.on( 'click', 'a.comment-reply-link', function( event ) {
		const commentId = $( this ).attr( 'data-commentid' );
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
		const data = {
			action: 'create_shadow_post',
			data: formdata,
			_ajax_nonce: wpApiSettings.nonce,
		};

		$.ajax(
			{
				type: 'POST',
				url: wpApiSettings.admin_ajax_url,
				data,
			},
		).done(
			function( response ) {
				formdata.post = response.data;
				submitComment( formdata );
			},
		);
	}

	// Sends the new comment or the reply to an existing comment.
	$gp.editor.table.on( 'submit', '.meta.discussion .comment-form', function( e ) {
		const $commentform = $( e.target );
		const postId = $commentform.attr( 'id' ).split( '-' )[ 1 ];
		const divDiscussion = $commentform.closest( '.meta.discussion' );
		const rowId = divDiscussion.attr( 'data-row-id' );
		const requestUrl = $gp_translation_helpers_editor.translation_helper_url + rowId + '?nohc';

		const submitComment = function( formdata ) {
			$.ajax( {
				url: wpApiSettings.root + 'wp/v2/comments',
				method: 'POST',
				beforeSend( xhr ) {
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

		const formdata = {
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
		const textToCopy = $( this ).closest( 'li' ).find( 'a' ).text();
		const textareaToPaste = $( this ).closest( '.editor' ).find( 'textarea.foreign-text' );
		let selectionStart = textareaToPaste.get( 0 ).selectionStart;
		let selectionEnd = textareaToPaste.get( 0 ).selectionEnd;
		const textToCopyLength = textToCopy.length;
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
	const previewRows = $gp.editor.table.find( 'tr.preview' );
	if ( 1 === previewRows.length ) {
		$( 'tr.preview td' ).trigger( 'dblclick' );
	}

	$( document ).ready( function() {
		// Gets the translation helpers for the first row and caches them.
		const firstEditor = $( '#translations' ).find( 'tr.editor' ).first();
		cacheTranslationHelpersForARow( firstEditor );
	} );

	/**
	 * Hides all tabs and show one of them, the last clicked.
	 *
	 * @param {Object} tab The selected tab.
	 */
	function change_visible_tab( tab ) {
		const tabId = tab.attr( 'data-tab' );
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
		$( '#sidebar-div-others-' + originalId ).hide();
		$( '#' + tabId ).show();
	}

	/**
	 * Adds a button to each translation from another locales.
	 *
	 * @param {string} sidebarDiv The div where we add the buttons.
	 */
	function add_copy_button( sidebarDiv ) {
		const lis = $( sidebarDiv + ' .other-locales li' );
		lis.each( function() {
			let html = $( this ).html();
			html += '<button class="sidebar-other-locales button is-small copy-suggestion"> Copy </button>';
			$( this ).html( html );
		} );
	}

	/**
	 * Adds the amount of elements to the "Others" tab.
	 *
	 * @param {string} sidebarTab The tab where we add the amount of elements.
	 * @param {Object} data       The object where we have the data to add.
	 * @param {number} originalId The id of the original string to translate.
	 */
	if ( typeof window.add_amount_to_others_tab === 'undefined' ) {
		add_amount_to_others_tab = function( sidebarTab, data, originalId ) {
			let elements = 0;
			if ( data[ 'helper-history-' + originalId ] !== undefined ) {
				elements += data[ 'helper-history-' + originalId ].count;
			}
			if ( data[ 'helper-other-locales-' + originalId ] !== undefined ) {
				elements += data[ 'helper-other-locales-' + originalId ].count;
			}
			$( '[data-tab="' + sidebarTab + '"]' ).html( 'Others&nbsp;(' + elements + ')' );
		};
	}

	/**
	 * Loads the content in the tabs (header tab and content) for the opened row.
	 *
	 * @param {Object} element The element that triggers the action.
	 */
	function loadTabsAndDivs( element ) {
		const rowId = element.closest( 'tr.editor' ).attr( 'id' ).substring( 7 );
		const requestUrl = $gp_translation_helpers_editor.translation_helper_url + rowId + '?nohc';
		if ( translationHelpersCache[ rowId ] !== undefined ) {
			updateDataInTabs( translationHelpersCache[ rowId ], rowId );
		} else {
			$.getJSON( requestUrl, function( data ) {
				translationHelpersCache[ rowId ] = data;
				updateDataInTabs( data, rowId );
			} );
		}
	}

	/**
	 * Updates the content of the tabs and divs.
	 *
	 * @param {Object} data       The content to update the tabs and divs.
	 * @param {number} originalId The id of the original string to translate.
	 *
	 * @return {void}
	 */
	function updateDataInTabs( data, originalId ) {
		if ( data[ 'helper-translation-discussion-' + originalId ] !== undefined ) {
			$( '[data-tab="sidebar-tab-discussion-' + originalId + '"]' ).html( 'Discussion&nbsp;(' + data[ 'helper-translation-discussion-' + originalId ].count + ')' );
			$( '#sidebar-div-discussion-' + originalId ).html( data[ 'helper-translation-discussion-' + originalId ].content );
		}
		if ( data[ 'helper-history-' + originalId ] !== undefined ) {
			$( '#summary-history-' + originalId ).html( 'History&nbsp;(' + data[ 'helper-history-' + originalId ].count + ')' );
			$( '#sidebar-div-others-history-content-' + originalId ).html( data[ 'helper-history-' + originalId ].content );
			add_amount_to_others_tab( 'sidebar-tab-others-' + originalId, data, originalId );
		} else {
			$( '#sidebar-div-others-history-content-' + originalId ).html( '' );
		}
		if ( data[ 'helper-other-locales-' + originalId ] !== undefined ) {
			$( '#summary-other-locales-' + originalId ).html( 'Other&nbsp;locales&nbsp;(' + data[ 'helper-other-locales-' + originalId ].count + ')' );
			$( '#sidebar-div-others-other-locales-content-' + originalId ).html( data[ 'helper-other-locales-' + originalId ].content );
			add_copy_button( '#sidebar-div-others-other-locales-content-' + originalId );
			add_amount_to_others_tab( 'sidebar-tab-others-' + originalId, data, originalId );
		} else {
			$( '#sidebar-div-others-other-locales-content-' + originalId ).html( '' );
		}
	}

	/**
	 * Caches the translation helpers for a row.
	 *
	 * @param {Object} editor The editor row.
	 *
	 * @return {void}
	 */
	function cacheTranslationHelpersForARow( editor ) {
		const rowId = editor.attr( 'row' );
		const requestUrl = $gp_translation_helpers_editor.translation_helper_url + rowId + '?nohc';
		if ( ! rowId ) {
			return;
		}

		if ( translationHelpersCache[ rowId ] === undefined ) {
			// Store a string with a space to avoid making the same request another time.
			translationHelpersCache[ rowId ] = ' ';
			$.getJSON( requestUrl, function( data ) {
				translationHelpersCache[ rowId ] = data;
				updateDataInTabs( data, rowId );
			} );
		}
	}

	function EventStreamParser( onParse ) {
		// npm eventsource-parser
		// MIT License
		// Copyright (c) 2023 Espen Hovlandsdal <espen@hovlandsdal.com>

		// Processing state
		let isFirstChunk;
		let buffer;
		let startingPosition;
		let startingFieldLength;

		// Event state
		let eventId;
		let eventName;
		let data;

		reset();
		return { feed, reset };

		function reset() {
			isFirstChunk = true;
			buffer = '';
			startingPosition = 0;
			startingFieldLength = -1;

			eventId = undefined;
			eventName = undefined;
			data = '';
		}

		function feed( chunk ) {
			buffer = buffer ? buffer + chunk : chunk;

			// Strip any UTF8 byte order mark (BOM) at the start of the stream.
			// Note that we do not strip any non - UTF8 BOM, as eventsource streams are
			// always decoded as UTF8 as per the specification.
			if ( isFirstChunk && hasBom( buffer ) ) {
				buffer = buffer.slice( 3 );
			}

			isFirstChunk = false;

			// Set up chunk-specific processing state
			const length = buffer.length;
			let position = 0;
			let discardTrailingNewline = false;

			// Read the current buffer byte by byte
			while ( position < length ) {
				// EventSource allows for carriage return + line feed, which means we
				// need to ignore a linefeed character if the previous character was a
				// carriage return
				// @todo refactor to reduce nesting, consider checking previous byte?
				// @todo but consider multiple chunks etc
				if ( discardTrailingNewline ) {
					if ( buffer[ position ] === '\n' ) {
						++position;
					}
					discardTrailingNewline = false;
				}

				let lineLength = -1;
				let fieldLength = startingFieldLength;
				let character;

				for ( let index = startingPosition; lineLength < 0 && index < length; ++index ) {
					character = buffer[ index ];
					if ( character === ':' && fieldLength < 0 ) {
						fieldLength = index - position;
					} else if ( character === '\r' ) {
						discardTrailingNewline = true;
						lineLength = index - position;
					} else if ( character === '\n' ) {
						lineLength = index - position;
					}
				}

				if ( lineLength < 0 ) {
					startingPosition = length - position;
					startingFieldLength = fieldLength;
					break;
				} else {
					startingPosition = 0;
					startingFieldLength = -1;
				}

				parseEventStreamLine( buffer, position, fieldLength, lineLength );

				position += lineLength + 1;
			}

			if ( position === length ) {
				// If we consumed the entire buffer to read the event, reset the buffer
				buffer = '';
			} else if ( position > 0 ) {
				// If there are bytes left to process, set the buffer to the unprocessed
				// portion of the buffer only
				buffer = buffer.slice( position );
			}
		}

		function parseEventStreamLine(
			lineBuffer,
			index,
			fieldLength,
			lineLength,
		) {
			if ( lineLength === 0 ) {
				// We reached the last line of this event
				if ( data.length > 0 ) {
					onParse( {
						type: 'event',
						id: eventId,
						event: eventName || undefined,
						data: data.slice( 0, -1 ), // remove trailing newline
					} );

					data = '';
					eventId = undefined;
				}
				eventName = undefined;
				return;
			}

			const noValue = fieldLength < 0;
			const field = lineBuffer.slice( index, index + ( noValue ? lineLength : fieldLength ) );
			let step = 0;

			if ( noValue ) {
				step = lineLength;
			} else if ( lineBuffer[ index + fieldLength + 1 ] === ' ' ) {
				step = fieldLength + 2;
			} else {
				step = fieldLength + 1;
			}

			const position = index + step;
			const valueLength = lineLength - step;
			const value = lineBuffer.slice( position, position + valueLength ).toString();

			if ( field === 'data' ) {
				data += value ? `${ value }\n` : '\n';
			} else if ( field === 'event' ) {
				eventName = value;
			} else if ( field === 'id' && ! value.includes( '\u0000' ) ) {
				eventId = value;
			} else if ( field === 'retry' ) {
				const retry = parseInt( value, 10 );
				if ( ! Number.isNaN( retry ) ) {
					onParse( { type: 'reconnect-interval', value: retry } );
				}
			}
		}
		function hasBom( b ) {
			return [ 239, 187, 191 ].every( ( charCode, index ) => b.charCodeAt( index ) === charCode );
		}
	}

	async function invokeChatGPT( prompt, response_span ) {
		const request = {
			model: 'gpt-3.5-turbo',
			messages: prompt,
			temperature: parseFloat( $gp_comment_feedback_settings.openai_temperature ),
			stream: true,
		};
		let result = '';
		const parser = EventStreamParser( function( event ) {
			if ( event.type === 'event' ) {
				if ( event.data !== '[DONE]' ) {
					result += JSON.parse( event.data ).choices[ 0 ].delta.content || '';
					response_span.text( result );
				}
			} else if ( event.type === 'invalid_request_error' ) {
				response_span.text( event.value );
			} else if ( event.type === 'reconnect-interval' ) {
				// console.log( 'We should set reconnect interval to %d milliseconds', event.value );
			}
		} );

		const response = await fetch(
			'https://api.openai.com/v1/chat/completions',
			{
				headers: {
					'Content-Type': 'application/json',
					Authorization: `Bearer ${ $gp_comment_feedback_settings.openai_key }`,
				},
				method: 'POST',
				body: JSON.stringify( request ),
			},
		);
		const reader = response.body.pipeThrough( new TextDecoderStream() ).getReader();

		while ( true ) {
			const { value, done } = await reader.read();
			if ( done ) {
				break;
			}
			parser.feed( value );
		}
	}

	/**
	 * Generate a GitHub URL that creates an issue with a prepopulated body when clicked.
	 *
	 * @param {string} original        The original string.
	 * @param {string} translation     The translation reviewed by ChatGPT.
	 * @param {string} projectUrl      The URL of the project or permalink.
	 * @param {string} chatGPTResponse The response from ChatGPT.
	 */
	function generateGithubIssueURL( original, translation, projectUrl, chatGPTResponse ) {
		const githubBaseUrl = 'https://github.com/GlotPress/gp-translation-helpers/issues/new?title=' + encodeURIComponent( 'ChatGPT Review Feedback' ) + '&labels=chatgpt-review&body=';
		let issueUrlParam = '';

		issueUrlParam += '### Original\n\n' + original + '\n\n';
		issueUrlParam += '### Translation\n\n' + translation + '\n\n';
		issueUrlParam += '### Project or Permalink URL\n\n' + projectUrl + '\n\n';
		issueUrlParam += '### ChatGPT response received with current prompt at the time\n\n' + chatGPTResponse + '\n\n';
		issueUrlParam += '### What\'s bad about the review\n\n';
		issueUrlParam += '### Idea for a better prompt (optional)\n\n';
		return githubBaseUrl + encodeURIComponent( issueUrlParam );
	}

	/**
	 * Fetch translation review from OpenAI.
	 *
	 * @param {string}  rowId      The row-id attribute of the current row.
	 * @param {string}  currentRow The current row.
	 * @param {boolean} isRetry    This is a retry.
	 */
	function fetchOpenAIReviewResponse( rowId, currentRow, isRetry ) {
		const messages = [];
		const original_str = currentRow.find( '.original' );
		let glossary_prompt = '';
		let githubIssueUrl = '';
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
			messages.push( {
				role: 'system',
				content: 'You are required to follow these rules, ' + glossary_prompt + 'for words found in the English text you are translating.',
			} );
		}
		messages.push( {
			role: 'system',
			content: ( isRetry ? 'Are you sure that ' : '' ) + 'For the english text  "' + currentRow.find( '.original-raw' ).text() + '", is "' + currentRow.find( '.foreign-text:first' ).val() + '" a correct translation in ' + $gp_comment_feedback_settings.language + '? Don\'t repeat the translation if it is correct and point out differences if there are any.',
		} );

		currentRow.find( '.openai-review .suggestions__loading-indicator' ).hide();
		currentRow.find( '.openai-review .auto-review-result' ).html( '<h4>Review by ChatGPT' ).append( '<span style="white-space: pre-line">' );
		invokeChatGPT( messages, currentRow.find( '.openai-review .auto-review-result span' ) ).then( () => {
			const ids = currentRow[ 0 ].id.split( /-/ );
			const permalink = new URL( window.location.href );
			permalink.searchParams = new URLSearchParams( { 'filters[status]': 'either', 'filters[original_id]': ids[ 1 ], 'filters[translation_id]': ids[ 2 ] } );
			currentRow.find( '.openai-review .auto-review-result' ).append( ' <a href="#" class="retry-auto-review">Retry</a>' );
			githubIssueUrl = generateGithubIssueURL( original_str.text(), currentRow.find( '.foreign-text:first' ).val(), permalink.toString(), $( '.auto-review-result span' ).text() );
			currentRow.find( '.openai-review .auto-review-result' ).append( ' <a href="' + githubIssueUrl + '">Give Feedback</a>' );
		} );
	}
} );
