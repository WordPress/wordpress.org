( function( $ ){
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
			removeNoSuggestionsMessage( $container );
		} );
	}

	function maybeFetchTranslationMemorySuggestions() {
		var $container = $gp.editor.current.find( '.suggestions__translation-memory' );
		if ( !$container.length ) {
			return;
		}

		if ( $container.hasClass( 'initialized' ) || $container.hasClass( 'fetching' ) ) {
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
		maybeFetchExternalSuggestions( gpTranslationSuggestions.get_external_translations.get_openai_translations, window.WPORG_TRANSLATION_MEMORY_OPENAI_API_URL );
	}

	/**
	 * Gets the suggestions from the DeepL API.
	 *
	 * @return {void}
	 **/
	function maybeFetchDeeplSuggestions() {
		maybeFetchExternalSuggestions( gpTranslationSuggestions.get_external_translations.get_deepl_translations, window.WPORG_TRANSLATION_MEMORY_DEEPL_API_URL );
	}

	/**
	 * Gets the suggestions from an external service.
	 *
	 * @param getExternalSuggestions
	 * @param apiUrl
	 */
	function maybeFetchExternalSuggestions( getExternalSuggestions, apiUrl ) {
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

		fetchSuggestions( $container, apiUrl, originalId, translationId, nonce );
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
