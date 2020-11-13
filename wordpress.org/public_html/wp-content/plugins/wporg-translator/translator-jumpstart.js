(function( $ ) {
	$( function() {
		if ( ! $( '#translator-launcher' ).length || typeof communityTranslator !== 'object' ) {
			// translator Jumpstart not available, maybe interface is in English
			return false;
		}

		function loadTranslator() {
			$( '#translator-launcher .text' ).addClass( 'enabled' ).removeClass( 'disabled' );
			if ( communityTranslator.load() !== false ) {
				// was loaded successfully
				autoloadTranslator( true );
			}
		}

		function unloadTranslator() {
			$( '#translator-launcher .text' ).removeClass( 'enabled' ).addClass( 'disabled' );
			communityTranslator.unload();
			autoloadTranslator( false );
		}

		$( document.body ).on( 'click', '#translator-launcher', function() {

			if ( $( '#translator-launcher .text' ).hasClass( 'disabled' ) ) {
				loadTranslator();
			} else {
				unloadTranslator();
			}
			return false;
		} );

		// only show the button when the translator has been loaded
		runWhenTranslatorIsLoaded( function() {
			$( '#translator-launcher' ).show();
			if ( shouldAutoloadTranslator() ) {
				loadTranslator();
			}
		} );

		// because of the nature of wp_enqueue_script and the fact that we can only insert the translatorJumpstart at the bottom of the page, we have to wait until the object exists
		function runWhenTranslatorIsLoaded( callback ) {
			if ( 'undefined' === typeof window.translatorJumpstart ) {
				setTimeout( function() {
					runWhenTranslatorIsLoaded( callback );
				}, 100 );
				return;
			}
			callback();
		}

		function autoloadTranslator( enable ) {
			if ( enable ) {
				document.cookie = 'ct_en=1;path=/;domain=.wordpress.com';
			} else {
				document.cookie = 'ct_en=;expires=Sat,%201%20Jan%202000%2000:00:00%20GMT;path=/;domain=.wordpress.com';
			}
		}

		function shouldAutoloadTranslator( enable ) {
			return !! document.cookie.match( /ct_en=1/ );
		}
	} );
})( jQuery );

