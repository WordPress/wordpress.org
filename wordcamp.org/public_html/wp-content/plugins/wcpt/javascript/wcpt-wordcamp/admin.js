window.wordCampPostType = window.wordCampPostType || {};

window.wordCampPostType.WcptWordCamp = ( function( $ ) {
	'use strict';

	var self = {};

	/**
	 * Initialize
	 */
	self.initialize = function() {
		var createSiteCheckbox = $( '#wcpt_create-site-in-network' );

		createSiteCheckbox.change( self.toggleSponsorRegionRequired );
		createSiteCheckbox.trigger( 'change' );

		$( '.date-field' ).datepicker( {
			dateFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear:  true
		} );
	};

	/**
	 * Toggle whether the Sponsor Region field is required or not.
	 *
	 * \WordCamp_New_Site::maybe_create_new_site() requires it to be set to create a new site.
	 *
	 * @param {object} event
	 */
	self.toggleSponsorRegionRequired = function( event ) {
		var sponsorRegion = $( '#wcpt_multi-event_sponsor_region' );

		if ( $( this ).is( ':checked' ) ) {
			sponsorRegion.prop( 'required', true );
		} else {
			sponsorRegion.prop( 'required', false );
		}
	};

	$( document ).ready( function( $ ) {
		self.initialize();
	} );

	return self;

} ( jQuery ) );
