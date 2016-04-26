wp.customize.CampTixHtmlBadgesCustomizer = ( function( $, api ) {
	'use strict';

	var self = {
		sectionID    : 'camptix_html_badges',
		cssSettingID : 'cbg_badge_css',
		siteURL      : window.location.protocol + '//' + window.location.hostname,
		cmEditor     : null
	};

	self.badgesPageURL = self.siteURL + '?camptix-badges';

	$.extend( self, cbgHtmlCustomizerData );
	window.cbgHtmlCustomizerData = null;

	/**
	 * Initialize
	 */
	self.initialize = function() {
		api.section( self.sectionID ).container.bind( 'expanded',  self.loadBadgesPage     );
		api.section( self.sectionID ).container.bind( 'collapsed', self.unloadBadgesPage   );
		api.section( self.sectionID ).container.bind( 'expanded',  self.setupCodeMirror    );
		api.section( self.sectionID ).container.bind( 'expanded',  self.showBrowserWarning );

		$( '#customize-control-cbg_print_badges' ).find( 'input[type=button]' ).click( self.printBadges );
		$( '#customize-control-cbg_reset_css'    ).find( 'input[type=button]' ).click( self.resetCSS    );
	};

	/**
	 * Load the Badges pages when navigating to our Section
	 *
	 * @param {object} event
	 */
	self.loadBadgesPage = function( event ) {
		if ( self.badgesPageURL !== api.previewer.previewUrl.get() ) {
			api.previewer.previewUrl.set( self.badgesPageURL );
		}
	};

	/**
	 * Unload the Badges page when navigating away from our Section
	 *
	 * @param {object} event
	 */
	self.unloadBadgesPage = function( event ) {
		if ( self.badgesPageURL === api.previewer.previewUrl.get() ) {
			api.previewer.previewUrl.set( self.siteURL );
		}
	};

	/**
	 * Replace the plain textarea with a nice CSS editor
	 *
	 * @param {object} event
	 */
	self.setupCodeMirror = function( event ) {
		if ( self.cmEditor !== null ) {
			return;
		}

		self.cmEditor = CodeMirror.fromTextArea(
			$( '#customize-control-cbg_badge_css' ).find( 'textarea' ).get(0),
			{
				tabSize        : 2,
				indentWithTabs : true,
				lineWrapping   : true
			}
		);

		self.cmEditor.setSize( null, 'auto' );

		self.cmEditor.on( 'change', function() {
			api( self.cssSettingID ).set( self.cmEditor.getValue() );
		} );
	};

	/**
	 * Show the browser warning to non-Gecko based browsers
	 *
	 * @param {object} event
	 */
	self.showBrowserWarning = function( event ) {
		// Rendering engine string must include the "/" to prevent matching things like "like Gecko" in Chrome/Safari
		if ( navigator.userAgent.toLowerCase().indexOf( 'gecko/' ) === -1 ) {
			$( '#cbg-firefox-recommended' ).removeClass( 'hidden' );
		}
	};

	/**
	 * Print the badges in the Previewer frame
	 *
	 * @param {object} event
	 */
	self.printBadges = function( event ) {
		window.frames[0].print();
	};

	/**
	 * Reset to the default CSS
	 *
	 * @param {object} event
	 */
	self.resetCSS = function( event ) {
		api( self.cssSettingID ).set( self.defaultCSS );
		self.cmEditor.setValue(       self.defaultCSS );
	};

	api.bind( 'ready', self.initialize );
	return self;

} ( jQuery, wp.customize ) );
