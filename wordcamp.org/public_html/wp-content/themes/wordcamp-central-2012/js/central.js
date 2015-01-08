var WordCampCentral = ( function( $ ) {

	// templateOptions is copied from Core in order to avoid an extra HTTP request just to get wp.template
	var ajaxURL,
		templateOptions = {
			evaluate:    /<#([\s\S]+?)#>/g,
			interpolate: /\{\{\{([\s\S]+?)\}\}\}/g,
			escape:      /\{\{([^\}]+?)\}\}(?!\})/g
		};

	/**
	 * Initialization that runs as soon as this file has loaded
	 */
	function immediateInit( options ) {
		ajaxURL = options.ajaxURL;

		toggleNavigation();
		populateLatestTweets();
	}

	/**
	 * Initialization that runs when the document has fully loaded
	 */
	function documentReadyInit() {
	}

	/**
	 * Toggle the navigation menu for small screens.
	 */
	function toggleNavigation() {
		var container, button, menu;

		container = document.getElementById( 'access' );
		if ( ! container ) {
			return;
		}

		button = container.getElementsByTagName( 'button' )[0];
		if ( 'undefined' === typeof button ) {
			return;
		}

		menu = container.getElementsByTagName( 'ul' )[0];

		// Hide menu toggle button if menu is empty and return early.
		if ( 'undefined' === typeof menu ) {
			button.style.display = 'none';
			return;
		}

		if ( -1 === menu.className.indexOf( 'nav-menu' ) ) {
			menu.className += ' nav-menu';
		}

		button.onclick = function() {
			if ( -1 !== container.className.indexOf( 'toggled' ) ) {
				container.className = container.className.replace( ' toggled', '' );
			} else {
				container.className += ' toggled';
			}
		};
	}

	/**
	 * Fetch the latest tweets and inject them into the DOM
	 */
	function populateLatestTweets() {
		$.getJSON(
			ajaxURL,
			{ action: 'get_latest_wordcamp_tweets' },
			function( response ) {
				var index, tweets,
					spinner         = $( '#wc-tweets-spinner' ),
					error           = $( '#wc-tweets-error' ),
					tweetsContainer = $( '#wc-tweets-container' ),
					tweetTemplate   = _.template( $( '#tmpl-wc-tweet' ).html(), null, templateOptions );

				// Check for success
				if ( response.hasOwnProperty( 'data' ) && response.data.hasOwnProperty( 'tweets' ) ) {
					tweets = response.data.tweets;
				} else {
					spinner.addClass(  'hidden' );
					error.removeClass( 'hidden' );
					error.removeAttr(  'hidden' );
					return;
				}

				// Populate and reveal the container
				for ( index in tweets ) {
					if ( tweets.hasOwnProperty( index ) ) {
						tweetsContainer.append( tweetTemplate( { 'tweet': tweets[ index ] } ) );
					}
				}

				spinner.addClass( 'hidden' );
				tweetsContainer.removeClass( 'transparent' );
			}
		);
	}

	return {
		immediateInit:     immediateInit,
		documentReadyInit: documentReadyInit
	};
} )( jQuery );

WordCampCentral.immediateInit( wordcampCentralOptions );
jQuery( document ).ready( WordCampCentral.documentReadyInit );
