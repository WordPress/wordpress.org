( function( $ ){
	/**
	 * Stores the originalId of the translations for which OpenAI has already been queried,
	 * to avoid making the query another time.
	 *
	 * @type {array}
	 */
	var OpenAITMSuggestionRequested = [];

	/**
	 * Stores the originalId of the translations for which DeepL has already been queried,
	 * to avoid making the query another time.
	 *
	 * @type {array}
	 */
	var DeeplTMSuggestionRequested = [];

	function fetchSuggestions( $container, apiUrl, originalId, translationId, nonce ) {
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
				removeNoSuggestionsMessage( $container );
				copyTranslationMemoryToSidebarTab();
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
	 * Copies the translation memory to the sidebar tab and adds the number of items in the TM to the tab.
	 *
	 * @return {void}
	 */
	function copyTranslationMemoryToSidebarTab(){
	    var divSidebarWithTM = $gp.editor.current.find( '.meta.translation-memory' ).first();
		var divId = divSidebarWithTM.attr( 'data-row-id' );
		var TMcontainer = $gp.editor.current.find( '.suggestions__translation-memory' );
		if ( !TMcontainer.length ) {
			return;
		}

		var itemsInTM = TMcontainer.find( '.translation-suggestion.with-tooltip.translation' ).length;
		itemsInTM += TMcontainer.find( '.translation-suggestion.with-tooltip.deepl' ).length;
		itemsInTM += TMcontainer.find( '.translation-suggestion.with-tooltip.openai' ).length;
		$( '[data-tab="sidebar-tab-translation-memory-' + divId + '"]' ).html( 'TM&nbsp;(' + itemsInTM + ')' );
		$( '#sidebar-div-translation-memory-' + divId ).html( TMcontainer.html() );
	}

	/**
	 * Add the suggestion from the translation memory to the translation textarea if
	 * the suggestion has 100% of accuracy.
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

	function maybeFetchTranslationMemorySuggestions() {
		var $container = $gp.editor.current.find( '.suggestions__translation-memory' );
		if ( !$container.length ) {
			return;
		}

		if ( $container.hasClass( 'initialized' ) || $container.hasClass( 'fetching' ) ) {
			return;
		}

		if ( !$gp.editor.current.find('translation-suggestion.with-tooltip.translation').first() ) {
			return;
		}

		$container.addClass( 'fetching' );

		var originalId = $gp.editor.current.original_id;
		var translationId = $gp.editor.current.translation_id;
		var nonce = $container.data( 'nonce' );

		fetchSuggestions( $container, window.WPORG_TRANSLATION_MEMORY_API_URL, originalId, translationId, nonce );
	}

	/**
	 * Gets the suggestions from the OpenAI API.
	 *
	 * @return {void}
	 **/
	function maybeFetchOpenAISuggestions() {
		maybeFetchExternalSuggestions( 'OpenAI', gpTranslationSuggestions.get_external_translations.get_openai_translations, window.WPORG_TRANSLATION_MEMORY_OPENAI_API_URL );
	}

	/**
	 * Gets the suggestions from the DeepL API.
	 *
	 * @return {void}
	 **/
	function maybeFetchDeeplSuggestions() {
		maybeFetchExternalSuggestions( 'DeepL', gpTranslationSuggestions.get_external_translations.get_deepl_translations, window.WPORG_TRANSLATION_MEMORY_DEEPL_API_URL );
	}

	/**
	 * Gets the suggestions from an external service.
	 *
	 * @param type					 The type of the external service: OpenAI or DeepL.
	 * @param getExternalSuggestions Whether to get the suggestions from the external service.
	 * @param apiUrl				 The URL of the API.
	 *
	 * @return {void}
	 */
	function maybeFetchExternalSuggestions( type, getExternalSuggestions, apiUrl ) {
		var $container = $gp.editor.current.find( '.suggestions__translation-memory' );
		if ( !$container.length ) {
			return;
		}
		if ( true !== getExternalSuggestions ) {
			return;
		}
		var originalId = $gp.editor.current.original_id;
		var translationId = $gp.editor.current.translation_id;
		var nonce = $container.data( 'nonce' );

		if( true === wasRequestMade( type, originalId ) ) {
			return;
		}

		fetchSuggestions( $container, apiUrl, originalId, translationId, nonce );
	}

	/**
	 * Checks if the request was already made for this originalId and type.
	 *
	 * @param type		  The type of the external service: OpenAI or DeepL.
	 * @param originalId  The original ID.
	 *
	 * @returns {boolean} Whether the request was already made.
	 */
	function wasRequestMade( type, originalId ) {
		if ('OpenAI' === type) {
			if ( originalId in OpenAITMSuggestionRequested ) {
				return true;
			} else {
				OpenAITMSuggestionRequested[originalId] = true;
			}
		}
		if ('DeepL' === type) {
			if ( originalId in DeeplTMSuggestionRequested ) {
				return true;
			} else {
				DeeplTMSuggestionRequested[originalId] = true;
			}
		}
		return false;
	}

	function maybeFetchOtherLanguageSuggestions() {
		var $container = $gp.editor.current.find( '.suggestions__other-languages' );
		if ( ! $container.length ) {
			return;
		}

		if ( $container.hasClass( 'initialized' ) || $container.hasClass( 'fetching' ) ) {
			return;
		}

		$container.addClass( 'fetching' );

		var originalId = $gp.editor.current.original_id;
		var translationId = $gp.editor.current.translation_id;
		var nonce = $container.data( 'nonce' );

		fetchSuggestions( $container, window.WPORG_OTHER_LANGUAGES_API_URL, originalId , translationId,  nonce );
	}

	/**
	 * Removes the "No suggestions" message if there are suggestions.
	 *
	 * This is needed because the suggestions are loaded asynchronously.
	 *
	 * @param $container
	 */
	function removeNoSuggestionsMessage( $container ) {
		var hasSuggestions = $container.find( '.translation-suggestion' ).length > 0;
		if ( hasSuggestions ) {
			$container.find( '.no-suggestions' ).hide();
		} else {
			$container = removeNoSuggestionsDuplicateMessage( $container );
		}
	}

	/**
	 * Removes duplicate "No suggestions" messages.
	 *
	 * @param $container
	 * @returns {*|jQuery}
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

		return $html.prop('outerHTML');
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

	$gp.editor.show = ( function( original ) {
		return function() {
			original.apply( $gp.editor, arguments );
			maybeFetchTranslationMemorySuggestions();
			maybeFetchOpenAISuggestions();
			maybeFetchDeeplSuggestions();
			maybeFetchOtherLanguageSuggestions();
		}
	})( $gp.editor.show );

	$gp.editor.install_hooks = ( function( original ) {
		return function() {
			original();

			$( $gp.editor.table )
				.on( 'click', '.translation-suggestion', copySuggestion );
		}
	})( $gp.editor.install_hooks );

})( jQuery );
