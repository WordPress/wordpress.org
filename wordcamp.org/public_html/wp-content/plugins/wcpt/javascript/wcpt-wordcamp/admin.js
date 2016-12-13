window.wordCampPostType = window.wordCampPostType || {};

window.wordCampPostType.WcptWordCamp = ( function( $ ) {
	'use strict';

	var self = {};

	/**
	 * Initialize
	 */
	self.initialize = function() {
		var createSiteCheckbox = $( '#wcpt_create-site-in-network' ),
			$mentorUserName = $( '#wcpt_mentor_wordpress_org_user_name' );

		// Sponsor region
		createSiteCheckbox.change( self.toggleSponsorRegionRequired );
		createSiteCheckbox.trigger( 'change' );

		// Date fields
		$( '.date-field' ).datepicker( {
			dateFormat: 'yy-mm-dd',
			changeMonth: true,
			changeYear:  true
		} );

		// Mentor username picker
		if ( $mentorUserName.length && ! $mentorUserName.is( '[readonly]' ) ) {
			self.initializeMentorPicker( $mentorUserName );
		}
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

    /**
	 * Insert a Mentor picker after the Mentor username field.
	 *
     * @param $el jQuery object for the Mentor username field.
     */
	self.initializeMentorPicker = function( $el ) {
		if ( 'undefined' === typeof window.wordCampPostType.Mentors.data ) {
			return;
		}

		var data     = window.wordCampPostType.Mentors.data,
			l10n     = window.wordCampPostType.Mentors.l10n,
			$select  = $( '<select id="wcpt-mentor-picker"><option></option></select>' ),
			$wrapper = $( '<span class="description">' ),
			$label   = $( '<label>' ).text( l10n.selectLabel );

		$.each( data, function( key, value ) {
			var $option = $( '<option>' );

			$option.val(key)
				.data({
					name: value.name,
					email: value.email
				})
				.text( value.name );

			if ( $option.val() === $el.val() ) {
				$option.prop( 'selected', 'selected' );
			}

			$select.append( $option );
		});

		$wrapper.append( $label )
			.append( $select )
			.insertAfter( $el );

		// Bind events
		$select.on( 'change', function() {
			var $option = $( this ).find( 'option:selected' );
			self.updateMentor( $option );
		});
	};

    /**
	 * Update the Mentor fields with the data for the mentor chosen in the picker.
	 *
     * @param $option jQuery object for the selected option element.
     */
	self.updateMentor = function( $option ) {
		var l10n            = window.wordCampPostType.Mentors.l10n,
			$mentorUserName = $( '#wcpt_mentor_wordpress_org_user_name' ),
			$mentorName     = $( '#wcpt_mentor_name' ),
			$mentorEmail    = $( '#wcpt_mentor_e-mail_address' );

		// Confirm before changing Mentor field contents
		if ( $option.val() && confirm( l10n.confirm ) ) {
            $mentorUserName.val( $option.val() );
            $mentorName.val( $option.data('name') );
            $mentorEmail.val( $option.data('email') );
		}
	};

    /**
	 * Kick things off
     */
	$( document ).ready( function( $ ) {
		self.initialize();
	} );

	return self;

} ( jQuery ) );
