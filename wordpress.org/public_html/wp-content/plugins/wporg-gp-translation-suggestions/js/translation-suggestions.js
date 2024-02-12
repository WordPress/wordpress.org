/* global $gp */
( function( $ ){
	/**
	 * Stores (caches) the translation memory (TM) suggestions that has already been queried,
	 * to avoid making the query another time. The key is the originalId.
	 *
	 * @type {array}
	 */
	var TMSuggestionRequested = [];

	/**
	 * Stores (caches) the OpenAI suggestions that has already been queried,
	 * to avoid making the query another time. The key is the originalId.
	 *
	 * @type {array}
	 */
	var OpenAITMSuggestionRequested = [];

	/**
	 * Stores (caches) the DeepL suggestions that has already been queried,
	 * to avoid making the query another time. The key is the originalId.
	 *
	 * @type {array}
	 */
	var DeeplTMSuggestionRequested = [];

	/**
	 * Stores the external translations used.
	 * @type {object}
	 */
	var externalSuggestion = {};

	/**
	 * Stores (caches) the "Other Languages" suggestions that has already been queried,
	 * to avoid making the query another time. The key is the originalId.
	 *
	 * @type {array}
	 */
	var OtherLanguagesSuggestionRequested = [];

	/**
	 * Requests the suggestions from an external system, stores them in the cache and appended them to the container.
	 * If the suggestions are already in the cache, they are appended to the container.
	 *
	 * @param {object} $container    The container where the suggestions will be stored.
	 * @param {string} apiUrl        The URL of the API.
	 * @param {number} originalId    The ID of the original string.
	 * @param {number} translationId The ID of the translation string.
	 * @param {string} nonce         The nonce to use to make the request.
	 * @param {string} type          The type of suggestions to fetch: TM, OpenAI, DeepL, OL (Other Languages).
	 *
	 * @return {void}
	 */
	function fetchSuggestions( $container, apiUrl, originalId, translationId, nonce, type ) {
		var cachedSuggestion = getTheSuggestionFromTheCache( type, originalId );
		if ( cachedSuggestion ) {
			$container.removeClass( 'fetching' );
			$container.find( '.suggestions__loading-indicator' ).remove();
			if ( isThisTypeOfSuggestionInTheContainer( $container, type ) ) {
				return;
			}

			$container.append( cachedSuggestion );
			removeNoSuggestionsMessage( $container );
			copyTranslationMemoryToSidebarTab( $container );
			return;
		}
		// Store a string with a space to avoid making the same request another time.
		storeTheSuggestionInTheCache( type, originalId, ' ' );
		var xhr = $.ajax( {
			url: apiUrl,
			data: {
				'original': originalId,
				'translation': translationId,
				'nonce': nonce
			},
			dataType: 'json',
			cache: false,
		} );

		xhr.done( function( response ) {
			$container.find( '.suggestions__loading-indicator' ).remove();
			if ( response.success ) {
				$container.append( response.data );
				storeTheSuggestionInTheCache( type, originalId, response.data );
				removeNoSuggestionsMessage( $container );
				copyTranslationMemoryToSidebarTab( $container );
			} else {
				$container.append( $( '<span/>', { 'text': 'Error while loading suggestions.' } ) );
			}
			$container.addClass( 'initialized' );
		} );

		xhr.fail( function() {
			$container.find( '.suggestions__loading-indicator' ).remove();
			$container.append( $( '<span/>', { 'text': 'Error while loading suggestions.' } ) );
			$container.addClass( 'initialized' );
		} );

		xhr.always( function() {
			$container.removeClass( 'fetching' );
		} );
	}

	/**
	 * Gets the suggestions for the first row and stores them in the local cache.
	 *
	 * @return {void}
	 */
	function getSuggestionsForTheFirstRow() {
		var firstEditor = $( '#translations' ).find( '.editor' ).first();
		var row_id = firstEditor.closest( 'tr' ).attr( 'row' );
		if ( ! row_id ) {
			return;
		}
		firstEditor.row_id = row_id;
		firstEditor.original_id = $gp.editor.original_id_from_row_id( row_id );
		firstEditor.translation_id = $gp.editor.translation_id_from_row_id( row_id );
		maybeFetchTranslationMemorySuggestions( firstEditor );
		maybeFetchOpenAISuggestions( firstEditor );
		maybeFetchDeeplSuggestions( firstEditor );
		maybeFetchOtherLanguageSuggestions( firstEditor );
	}

	/**
	 * Gets a suggestion from the local cache.
	 *
	 * @param {string} type       The type of suggestions to fetch: TM, OpenAI, DeepL, OL (Other Languages).
	 * @param {number} originalId The ID of the original string.
	 *
	 * @return {string|boolean}  The suggestion if it is in the cache, false otherwise.
	 */
	function getTheSuggestionFromTheCache( type, originalId ) {

		switch ( type ) {
			case 'TM':
				if ( ! ( originalId in TMSuggestionRequested ) ) {
					return false;
				}
				return TMSuggestionRequested[ originalId ];
				break;
			case 'OpenAI':
				if ( ! ( originalId in OpenAITMSuggestionRequested ) ) {
					return false;
				}
				return OpenAITMSuggestionRequested[ originalId ];
				break;
			case 'DeepL':
				if ( ! ( originalId in DeeplTMSuggestionRequested ) ) {
					return false;
				}
				return DeeplTMSuggestionRequested[ originalId ];
				break;
			case 'OL':
				if ( ! ( originalId in OtherLanguagesSuggestionRequested ) ) {
					return false;
				}
				return OtherLanguagesSuggestionRequested[ originalId ];
				break;
		}
	}

	/**
	 * Stores the suggestion in the local cache (JavaScript variables).
	 *
	 * @param {string} type       The type of suggestions to fetch: TM, OpenAI, DeepL, OL (Other Languages).
	 * @param {number} originalId The ID of the original string.
	 * @param {string} suggestion The suggestion to store.
	 *
	 * @return {void}
	 */
	function storeTheSuggestionInTheCache( type, originalId, suggestion ) {
		switch (type) {
			case 'TM':
				TMSuggestionRequested[ originalId ] = suggestion;
				break;
			case 'OpenAI':
				OpenAITMSuggestionRequested[ originalId ] = suggestion;
				break;
			case 'DeepL':
				DeeplTMSuggestionRequested[ originalId ] = suggestion;
				break;
			case 'OL':
				OtherLanguagesSuggestionRequested[ originalId ] = suggestion;
				break;
		}
	}

	add_amount_to_others_tab = function ( sidebarTab, data, originalId ) {
		let elements = 0;
		if ( data?.['helper-history-' + originalId] ) {
			elements += data['helper-history-' + originalId].count;
		}
		if ( data?.['helper-other-locales-' + originalId] ) {
			elements += data['helper-other-locales-' + originalId].count;
		}
		var editor = $('[data-tab="' + sidebarTab + '"]').closest( '.editor' );
		var TMcontainer = editor.find( '.suggestions__translation-memory' );
		var elementsInTM = 0;
		if ( TMcontainer.length ) {
			elementsInTM += TMcontainer.find( '.translation-suggestion.with-tooltip.translation' ).length;
			elementsInTM += TMcontainer.find( '.translation-suggestion.with-tooltip.deepl' ).length;
			elementsInTM += TMcontainer.find( '.translation-suggestion.with-tooltip.openai' ).length;
		}
		elements += elementsInTM;
		$( '#summary-translation-memory-' + originalId ).html('Translation&nbsp;Memory&nbsp;(' + elementsInTM + ')');

		let content = 'Others&nbsp;(' + elements + ')';
		$('[data-tab="' + sidebarTab + '"]').html( content );
	}

	/**
	 * Copies the translation memory to the sidebar tab and adds the number of items in the TM to the tab.
	 *
	 * @param {object} $container The container where the suggestions are stored.
	 *
	 * @return {void}
	 */
	function copyTranslationMemoryToSidebarTab( $container ){
		var editor = $container.closest( '.editor' );
	    var divSidebarWithDiscussion = editor.find( '.meta.discussion' ).first();
		var divId = divSidebarWithDiscussion.attr( 'data-row-id' );
		var TMcontainer = editor.find( '.suggestions__translation-memory' );
		if ( !TMcontainer.length ) {
			return;
		}

		$( '#sidebar-div-others-translation-memory-content-' + divId ).html( TMcontainer.html() );
		add_amount_to_others_tab('sidebar-tab-others-' + divId, window.translationHelpersCache?.[ divId ], divId);
	}

	/**
	 * Adds the suggestion from the translation memory to the translation textarea if
	 * the suggestion has 100% of accuracy.
	 *
	 * @param {string} data The HTML response from the TM.
	 *
	 * @return {void}
	 **/
	function addSuggestionToTranslation( data ) {
		var suggestions = $( data ).find( '.translation-suggestion.with-tooltip.translation' );
		if ( ! suggestions.length ) {
			return;
		}
		for ( var i = 0; i < suggestions.length; i++ ) {
			var score = $( suggestions[i] ).find( '.translation-suggestion__score' );
			if ( score.length > 0 && score.text() === '100%' ) {
				var translation = $( suggestions[i] ).find( '.translation-suggestion__translation' );
				if ( translation.length > 0 ) {
					var activeTextarea = $gp.editor.current.find( '.textareas.active textarea' );
					if ( ! activeTextarea.length ) {
						return;
					}
					activeTextarea.val( translation.text() ).focus();
					break;
				}
			}
		}
	}

	/**
	 * Fetches the suggestions from the translation memory.
	 *
	 * @param {object} editor The editor object.
	 *
	 * @return {void}
	 */
	function maybeFetchTranslationMemorySuggestions( editor ) {
		var $container = editor.find( '.suggestions__translation-memory' );
		if ( !$container.length ) {
			return;
		}

		if ( $container.hasClass( 'initialized' ) || $container.hasClass( 'fetching' ) ) {
			return;
		}

		if ( !editor.find('translation-suggestion.with-tooltip.translation').first() ) {
			return;
		}

		$container.addClass( 'fetching' );

		var originalId = editor.original_id;
		var translationId = editor.translation_id;
		var nonce = $container.data( 'nonce' );

		fetchSuggestions( $container, window.WPORG_TRANSLATION_MEMORY_API_URL, originalId, translationId, nonce, 'TM' );
	}

	/**
	 * Gets the suggestions from the OpenAI API.
	 *
	 * @param {object} editor The editor object.
	 *
	 * @return {void}
	 **/
	function maybeFetchOpenAISuggestions( editor ) {
		maybeFetchExternalSuggestions( editor, 'OpenAI', gpTranslationSuggestions.get_external_translations.get_openai_translations, window.WPORG_TRANSLATION_MEMORY_OPENAI_API_URL );
	}

	/**
	 * Gets the suggestions from the DeepL API.
	 *
	 * @param {object} editor The editor object.
	 *
	 * @return {void}
	 **/
	function maybeFetchDeeplSuggestions( editor ) {
		maybeFetchExternalSuggestions( editor, 'DeepL', gpTranslationSuggestions.get_external_translations.get_deepl_translations, window.WPORG_TRANSLATION_MEMORY_DEEPL_API_URL );
	}

	/**
	 * Gets the suggestions from an external service.
	 *
	 * @param {object}  editor                 The editor.
	 * @param {string}  type                   The type of the external service: OpenAI or DeepL.
	 * @param {boolean} getExternalSuggestions Whether to get the suggestions from the external service.
	 * @param {string}  apiUrl                 The URL of the API.
	 *
	 * @return {void}
	 */
	function maybeFetchExternalSuggestions( editor, type, getExternalSuggestions, apiUrl ) {
		var $container = editor.find( '.suggestions__translation-memory' );
		if ( !$container.length ) {
			return;
		}
		if ( true !== getExternalSuggestions ) {
			return;
		}
		var originalId = editor.original_id;
		var translationId = editor.translation_id;
		var nonce = $container.data( 'nonce' );

		fetchSuggestions( $container, apiUrl, originalId, translationId, nonce, type );
	}

	/**
	 * Gets the suggestions from other languages.
	 *
	 * @param {object} editor The editor object.
	 *
	 * @return {void}
	 **/
	function maybeFetchOtherLanguageSuggestions( editor ) {
		var $container = editor.find( '.suggestions__other-languages' );
		if ( ! $container.length ) {
			return;
		}

		if ( $container.hasClass( 'initialized' ) || $container.hasClass( 'fetching' ) ) {
			return;
		}

		$container.addClass( 'fetching' );

		var originalId = editor.original_id;
		var translationId = editor.translation_id;
		var nonce = $container.data( 'nonce' );

		fetchSuggestions( $container, window.WPORG_OTHER_LANGUAGES_API_URL, originalId , translationId,  nonce, 'OL' );
	}

	/**
	 * Removes the "No suggestions" message if there are suggestions.
	 *
	 * This is needed because the suggestions are loaded asynchronously.
	 *
	 * @param {object} $container The container where the suggestions are stored.
	 *
	 * @return {void}
	 */
	function removeNoSuggestionsMessage( $container ) {
		var hasSuggestions = $container.find( '.translation-suggestion' ).length > 0;
		if ( hasSuggestions ) {
			$container.find( '.no-suggestions' ).hide();
		} else {
			removeNoSuggestionsDuplicateMessage( $container );
		}
	}

	/**
	 * Removes duplicate "No suggestions" messages.
	 *
	 * @param {object} $container The container where the suggestions are stored.
	 *
	 * @return {void}
	 */
	function removeNoSuggestionsDuplicateMessage( $container ) {
		var $html = $($container);
		var $paragraphs = $html.find('p');
		var uniqueParagraphs = [];

		$paragraphs.each(function() {
			var paragraphText = $(this).text().trim();

			if (uniqueParagraphs.indexOf(paragraphText) === -1) {
				uniqueParagraphs.push(paragraphText);
			} else {
				$(this).remove();
			}
		});
	}
	function copySuggestion( event ) {
		if ( 'A' === event.target.tagName ) {
			return;
		}

		var $el = $( this ).closest( '.translation-suggestion' );
		var $translation = $el.find( '.translation-suggestion__translation-raw');
		if ( ! $translation.length ) {
			return;
		}

		var $activeTextarea = $gp.editor.current.find( '.textareas.active textarea' );
		if ( ! $activeTextarea.length ) {
			return;
		}

		$activeTextarea.val( $translation.text() ).focus();

		// Trigger input event for autosize().
		var event = new Event( 'input' );
		$activeTextarea[0].dispatchEvent( event );
	}

	/**
	 * Checks if the suggestions of a certain type are in the container.
	 *
	 * @param {object} container The container.
	 * @param {string} type The type of the suggestions: OpenAI or DeepL.
	 *
	 * @return {boolean}
	 */
	function isThisTypeOfSuggestionInTheContainer( container, type ) {
		switch ( type ) {
			case 'TM':
				if ( container.find( '.translation-suggestion.with-tooltip.translation' ).length > 0 ) {
					return true;
				}
				break;
			case 'OpenAI':
				if ( container.find( '.translation-suggestion.with-tooltip.openai' ).length > 0 ) {
					return true;
				}
				break;
			case 'DeepL':
				if ( container.find( '.translation-suggestion.with-tooltip.deepl' ).length > 0 ) {
					return true;
				}
				break;
		}
		return false;
	}

	$gp.editor.show = ( function( original ) {
		return function() {
			original.apply( $gp.editor, arguments );
			maybeFetchTranslationMemorySuggestions( $gp.editor.current );
			maybeFetchOpenAISuggestions( $gp.editor.current );
			maybeFetchDeeplSuggestions( $gp.editor.current );
			maybeFetchOtherLanguageSuggestions( $gp.editor.current );
			var nextEditor = $gp.editor.current.nextAll('tr.editor' ).first();
			if ( nextEditor.length ) {
				var row_id = nextEditor.closest( 'tr' ).attr( 'row' );
				nextEditor.row_id = row_id;
				nextEditor.original_id = $gp.editor.original_id_from_row_id( row_id );
				nextEditor.translation_id = $gp.editor.translation_id_from_row_id( row_id );
				maybeFetchTranslationMemorySuggestions( nextEditor );
				maybeFetchOpenAISuggestions( nextEditor );
				maybeFetchDeeplSuggestions( nextEditor );
				maybeFetchOtherLanguageSuggestions( nextEditor );
			}
		};
	})( $gp.editor.show );

	$gp.editor.install_hooks = ( function( original ) {
		return function() {
			original();

			$( $gp.editor.table )
				.on( 'click', '.translation-suggestion', copySuggestion )
				.on( 'click', '.translation-suggestion', addSuggestion );
			$( document ).ready( function() {
				getSuggestionsForTheFirstRow();
			});
		};
	})( $gp.editor.install_hooks );

	/**
	 * Adds the suggestion to the translation in an array, and removes the previous suggestions, so
	 * we only store the last one in the database, using the saveExternalSuggestions() function.
	 *
	 * @return {void}
	 */
	function addSuggestion() {
		var $row = $( this );
		if ( ! $row ) {
			return;
		}
		externalSuggestion.suggestion_source = $row.data( 'suggestion-source' ) == 'translation' ? 'tm' : $row.data( 'suggestion-source' );
		externalSuggestion.translation = $row.find( '.translation-suggestion__translation' ).text();

	}

	//Prefilter ajax requests to add external translations used to the request.
	$.ajaxPrefilter( function ( options ) {
		const isSuggestionUsed = Object.keys( externalSuggestion ).length  > 0 ? true : false;

		if ( ! externalSuggestion || ! isSuggestionUsed ) {
			return;
		}
		if ( 'POST' === options.type && $gp_editor_options.url === options.url ) {
				options.data += '&externalTranslationSource=' + externalSuggestion.suggestion_source;
				options.data += '&externalTranslationUsed=' + externalSuggestion.translation;
				externalSuggestion = {};
		}
	});
})( jQuery );
