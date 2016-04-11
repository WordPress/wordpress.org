( function( wp, $ ) {
	'use strict';

	if ( ! wp || ! wp.customize ) {
		return;
	}

	var api = wp.customize;

	/**
	 * The Clone Another WordCamp panel
	 */
	api.panelConstructor.wcscPanel = api.Panel.extend( {
		/**
		 * Initialize the panel after it's loaded
		 *
		 * Ideally, the Previewer would be set to the requested site ID during the initial PHP request, rather than
		 * loading the host site in the Previewer, and then refreshing it to use the requested site. That became a
		 * rabbit hole, though, so it's done this way instead.
		 */
		ready : function() {
			var urlParams = getUrlParams( window.location.href );

			if ( urlParams.hasOwnProperty( 'wcsc_source_site_id' ) ) {
				this.expand();
				api( 'wcsc_source_site_id' ).set( urlParams.wcsc_source_site_id );
			}
		}
	} );

	/**
	 * Custom control representing a site that can be previewed/imported
	 */
	api.controlConstructor.wcscSite = api.Control.extend( {
		/**
		 * Initialize the control after it's loaded
		 */
		ready : function() {
			this.container.on( 'click', '.wcscSite', this.previewSite );
		},

		/**
		 * Preview the selected site
		 *
		 * If the site is using a different theme, then reload the entire Customizer with the theme URL parameter
		 * set, so that the Theme Switcher will handle previewing the new theme for us. Otherwise just set the ID
		 * to refresh the Previewer with the current theme and the new site's CSS, etc.
		 *
		 * @param {object} event
		 */
		previewSite : function( event ) {
			var previewUrl       = $( this ).data( 'preview-url' ),
				previewUrlParams = getUrlParams( previewUrl );

			if ( api( 'wcsc_source_site_id' ).get() == previewUrlParams.wcsc_source_site_id ) {
				return;
			}

			if ( api.settings.theme.stylesheet === previewUrlParams.theme ) {
				api( 'wcsc_source_site_id' ).set( previewUrlParams.wcsc_source_site_id );
			} else {
				window.parent.location = previewUrl;
			}
		}
	} );

	/**
	 * Parse the URL parameters
	 *
	 * Based on https://stackoverflow.com/a/2880929/450127
	 *
	 * @param {string} url
	 *
	 * @returns {object}
	 */
	function getUrlParams( url ) {
		var match, questionMarkIndex, query,
			urlParams = {},
			pl        = /\+/g,  // Regex for replacing addition symbol with a space
			search    = /([^&=]+)=?([^&]*)/g,
			decode    = function ( s ) {
				return decodeURIComponent( s.replace( pl, " " ) );
			};

		questionMarkIndex = url.indexOf( '?' );

		if ( -1 === questionMarkIndex ) {
			return urlParams;
		} else {
			query = url.substring( questionMarkIndex + 1 );
		}

		while ( match = search.exec( query ) ) {
			urlParams[ decode( match[ 1 ] ) ] = decode( match[ 2 ] );
		}

		return urlParams;
	}
} )( window.wp, jQuery );
