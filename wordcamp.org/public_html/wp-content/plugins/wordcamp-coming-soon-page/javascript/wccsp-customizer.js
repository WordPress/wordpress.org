wp.customize.WordCamp = wp.customize.WordCamp || {};

wp.customize.WordCamp.ComingSoonPage = ( function( $, api ) {
	'use strict';

	var self = {
		sectionID : 'wccsp_live_preview',
		siteURL   : window.location.protocol + '//' + window.location.hostname,
	};

	self.previewPageURL = self.siteURL + '?wccsp-preview';

	/**
	 * Initialize
	 */
	self.initialize = function() {
		api.section( self.sectionID ).container.bind( 'expanded',  self.loadPreviewPage   );
		api.section( self.sectionID ).container.bind( 'collapsed', self.unloadPreviewPage );
	};

	/**
	 * Load the Coming Soon page when navigating to our section
	 *
	 * @param {object} event
	 */
	self.loadPreviewPage = function( event ) {
		if ( self.previewPageURL !== api.previewer.previewUrl.get() ) {
			api.previewer.previewUrl.set( self.previewPageURL );
		}
	};

	/**
	 * Unload the Coming Soon page when navigating away from our section
	 *
	 * @param {object} event
	 */
	self.unloadPreviewPage = function( event ) {
		if ( self.previewPageURL === api.previewer.previewUrl.get() ) {
			api.previewer.previewUrl.set( self.siteURL );
		}
	};

	api.bind( 'ready', self.initialize );
	return self;

} ( jQuery, wp.customize ) );
