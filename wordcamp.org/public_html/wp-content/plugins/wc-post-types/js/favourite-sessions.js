jQuery( document ).ready( function ( $ ) {
	var FavSessions = {
		favSessKey: 'favourite_sessions',

		get: function () {
			var favSessions = JSON.parse( localStorage.getItem( this.favSessKey ) );

			if ( ! favSessions ) {
				favSessions = {};
			}

			return favSessions;
		},

		toggleSession: function ( sessionId ) {
			var favSessions = this.get();

			if ( favSessions.hasOwnProperty( sessionId ) ) {
				delete favSessions[ sessionId ];
			} else {
				favSessions[ sessionId ] = true;
			}

			localStorage.setItem( this.favSessKey, JSON.stringify( favSessions ) );
		},
	};

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

	function switchSessionFavourite( sessionId ) {
		FavSessions.toggleSession( sessionId );
		switchCellAppearance( sessionId );
		switchEmailFavButton();
	}

	function initFavouriteSessions() {
		var favSessions = FavSessions.get();

		if ( favSessions === {} ) {
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

		var elem = $( this );
		var sessionId = elem.parent().parent().data( 'session-id' );
		switchSessionFavourite( sessionId );

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
