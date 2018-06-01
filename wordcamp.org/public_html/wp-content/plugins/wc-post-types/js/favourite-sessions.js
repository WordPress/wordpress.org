jQuery( document ).ready( function ( $ ) {
	var favSessionsUrlSlug = 'fav-sessions=';

	function getUrlParams() {
		var url       = decodeURIComponent( window.location.search ),
		    urlParams = {};

		url.replace( /[?&]+([^=&]+)=([^&]*)/gi, function( str, key, value ) {
			urlParams[ key ] = value;
		} );

		return urlParams;
	}

	var FavSessions = {
		favSessKey: 'favourite_sessions',
		useLocalStorage: 'local_storage',
		useUrlSessions:  'URL',

		get: function() {
			if ( this.primarySource == this.useLocalStorage ) {
				var favSessions = JSON.parse( localStorage.getItem( this.favSessKey ) );

				if ( ! favSessions ) {
					favSessions = {};
				}
			} else {
				favSessions = this.favSessionsFromUrl();
			}

			return favSessions;
		},

		toggleSession: function ( sessionId ) {
			if ( this.primarySource !== this.useLocalStorage ) {
				return;
			}

			var favSessions = this.get();

			if ( favSessions.hasOwnProperty( sessionId ) ) {
				delete favSessions[ sessionId ];
			} else {
				favSessions[ sessionId ] = true;
			}

			localStorage.setItem( this.favSessKey, JSON.stringify( favSessions ) );
		},

		getSessionsForLink: function() {
			var favSessions = this.get();

			return Object.keys( favSessions ).join();
		},

		favSessionsFromUrl: function() {
			var urlParams       = getUrlParams(),
			    urlSlugPosition = favSessionsUrlSlug.slice( 0, favSessionsUrlSlug.length - 1 ),
			    favSessionIds   = urlParams[ urlSlugPosition ].split( ',' ),
			    favSessions     = {};

			for ( var i = 0; i < favSessionIds.length; i++ ) {
				favSessions[ favSessionIds[ i ] ] = true;
			}

			return favSessions;
		},

		updateBasedOnLink: function() {
			favSessions = this.favSessionsFromUrl();

			localStorage.setItem( this.favSessKey, JSON.stringify( favSessions ) );

			return this.get();
		},
	};

	// Use local storage for session source for fetching & target for saving by default.
	FavSessions.primarySource = FavSessions.useLocalStorage;

	function switchCellAppearance( sessionId ) {
		// (Un)highlight schedule table cell in case a session is (un)marked as favourite.
		var sessionSelector = '[data-session-id=\'' + sessionId + '\']';
		var tdElements = document.querySelectorAll( sessionSelector );

		for ( var i = 0; i < tdElements.length; i ++ ) {
			tdElements[ i ].classList.toggle( 'wcb-favourite-session' );
		}
	}

	function switchEmailFavButton() {
		var favSessions = FavSessions.get();

		// Display share form only if there are any selected sessions.
		if ( Object.keys( favSessions ).length > 0 ) {
			$( '.show-email-form' ).show();
			$( '.email-form' ).show();
		} else {
			$( '.show-email-form' ).hide();
			$( '.email-form' ).addClass( 'fav-session-div-hide' ).removeClass( 'fav-session-div-show' );
			$( '.email-form' ).hide();
		}
	}

	function updateShareLink() {
		var favSessionIds   = FavSessions.getSessionsForLink(),
		    urlParams       = getUrlParams(),
		    baseURL         = window.location.href,
		    paramsPosition  = baseURL.indexOf( '?' ),
		    urlSlugPosition = favSessionsUrlSlug.slice( 0, favSessionsUrlSlug.length - 1 );

		if ( -1 !== paramsPosition ) {
			baseURL = baseURL.slice( 0, paramsPosition );
		}

		urlParams[ urlSlugPosition ] = favSessionIds;

		// Don't include empty URL parameter.
		if ( '' === favSessionIds ) {
			delete urlParams[ urlSlugPosition ];
		}

		var favSessionsLink = baseURL + '?' + $.param( urlParams );

		$( '#fav-sessions-link' ).text( favSessionsLink );
		$( '#fav-sessions-link' ).prop( 'href', favSessionsLink );
	}

	function switchSessionFavourite( sessionId ) {
		FavSessions.toggleSession( sessionId );
		switchCellAppearance( sessionId );
		switchEmailFavButton();
		updateShareLink();
	}

	function initFavouriteSessions() {
		var favSessions = FavSessions.get();

		if ( favSessions === {} ) {
			return;
		}

		/*
		 * The user has already saved some sessions in local storage, but is now
		 * loading a shared link. We need to determine whether they intend to overwrite
		 * their saved sessions with those in the link, or if they just want to view
		 * the link's sessions and then discard them, so that their saved sessions remain
		 * in tact.
		 */
		var currentUrl = window.location.href;

		if ( currentUrl.indexOf( favSessionsUrlSlug ) > -1 ) {
			var overwrite = confirm( favSessionsPhpObject.i18n.overwriteFavSessions );

			if ( true === overwrite ) {
				FavSessions.primarySource = FavSessions.useLocalStorage;
				favSessions               = FavSessions.updateBasedOnLink( currentUrl );

				$( '.fav-session-button' ).attr( 'title', '' );
				$( '.fav-session-button' ).fadeTo( 0, 1 );
			} else {
				FavSessions.primarySource = FavSessions.useUrlSessions;
				favSessions               = FavSessions.get();

				/*
				 * Deactivate interaction with favourite session buttons,
				 * since the use chose to not overwrite their saved sessions.
				 */
				$( '.fav-session-button' ).attr( 'title', favSessionsPhpObject.i18n.buttonDisabledNote );
				$( '.fav-session-button' ).fadeTo( 0, 0.5 );
				$( '.fav-session-button' ).css( 'color', '#e7e7e7' );
			}
		}

		if ( {} === favSessions ) {
			return;
		}

		// Highlight favourite sessions in table.
		var sessionIds = Object.keys( favSessions );

		for ( var i = 0; i < sessionIds.length; i ++ ) {
			var sessionId = sessionIds[ i ];

			if ( favSessions[ sessionId ] === true ) {
				switchCellAppearance( sessionId );
			}
		}

		switchEmailFavButton();
		updateShareLink();
	}

	function hideSpinnerShowResult( message ) {
		var fadeInDelay = 300;
		$( '.fav-session-email-wait-spinner' ).fadeOut( fadeInDelay );

		setTimeout( function () {
			$( '.fav-session-email-result' ).html( message );
			$( '.fav-session-email-result' ).fadeIn();
		}, fadeInDelay );
	}

	function hideFormShowSpinner() {
		var fadeInDelay = 300;

		$( '#fav-session-email-form' ).fadeOut( fadeInDelay );

		setTimeout( function () {
			$( '.fav-session-email-wait-spinner' ).fadeIn();
		}, fadeInDelay );
	}

	$( '.show-email-form' ).click( function ( event ) {
		event.preventDefault();

		// Slide the slider.
		$( '.email-form' ).toggleClass( 'fav-session-email-form-hide' ).toggleClass( 'fav-session-email-form-show' );

		// After the animation finishes, activate the form again and hide the previous result.
		setTimeout( function () {
			// Clear previous email result.
			$( '.fav-session-email-result' ).html( '' );

			// Show form div & clear email address.
			$( '#fav-session-email-form' ).show();
			$( '#fav-sessions-email-address' ).val( '' );
		}, 500 );

		return false;
	} );

	$( '.fav-session-button' ).click( function ( event ) {
		event.preventDefault();

		if ( FavSessions.primarySource == FavSessions.useLocalStorage ) {
			var elem      = $( this ),
			    sessionId = elem.parent().parent().data( 'session-id' );

			switchSessionFavourite( sessionId );
		} else {
			alert( favSessionsPhpObject.i18n.buttonDisabledAlert );
		}

		return false;
	} );

	$( '#fav-sessions-form' ).on( 'submit', function ( event ) {
		event.preventDefault();
		hideFormShowSpinner();
		var favSessions = FavSessions.get();
		favSessions = Object.keys( favSessions ).toString();

		// Get email from the input.
		var emailAddress = '';
		if ( $( '#fav-sessions-email-address' ) ) {
			emailAddress = $( '#fav-sessions-email-address' ).val();
		} else {
			return;
		}

		// Compile data object.
		var data = {
			'email-address': emailAddress,
			'session-list': favSessions,
		};

		$.ajax( {
			method: 'POST',
			url: favSessionsPhpObject.root + 'wc-post-types/v1/email-fav-sessions',
			data: data,
			success: function ( response ) {
				hideSpinnerShowResult( response.message );
			},
			fail: function ( response ) {
				hideSpinnerShowResult( response.message );
			},
			error: function ( jqXHR, textStatus, errorThrown ) {
				if ( textStatus === 'timeout' ) {
					hideSpinnerShowResult( favSessionsPhpObject.i18n.reqTimeOut );
				} else {
					hideSpinnerShowResult( favSessionsPhpObject.i18n.otherError );
				}
			},
			timeout: 5000,
		} );
	} );

	// In case email tab is hidden, use the next tab as default.
	var defaultTab = 'email';
	if ( 0 === $( '#fav-session-tab-email' ).length ) {
		defaultTab = 'print';
	}

	$( '#fav-session-tab-' + defaultTab ).show();
	$( '#fav-session-tab-' + defaultTab ).addClass( 'active' );
	$( '#fav-session-btn-' + defaultTab ).addClass( 'active' );

	$( '.fav-session-tablinks' ).click( function( event ) {
		var i, tabContent, tabLinks;

		tabContent = document.getElementsByClassName( 'fav-session-share-tabcontent' );

		for ( i = 0; i < tabContent.length; i++ ) {
			$( tabContent[ i ] ).hide();
		}

		tabLinks = document.getElementsByClassName( 'fav-session-tablinks' );

		$( tabLinks ).removeClass( 'active' );

		var element         = $( this );
		var idStartPosition = 'fav-session-btn-'.length;
		var tabName         = 'fav-session-tab-' + element.attr( 'id' ).substring( idStartPosition );

		$( document.getElementById( tabName ) ).show();
		$( event.currentTarget ).addClass( 'active' )
	});


	$( '#fav-session-print' ).click( function( event ) {
		$( '.wcpt-schedule td div' ).css( 'opacity', 0.3 );
		window.print();
		$( '.wcpt-schedule td div' ).css( 'opacity', 1 );
	});

	initFavouriteSessions();
} );
