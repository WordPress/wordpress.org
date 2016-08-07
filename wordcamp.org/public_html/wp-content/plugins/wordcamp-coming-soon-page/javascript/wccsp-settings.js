jQuery( document ).ready( function( $ ) {

	var WCCSP_Settings = {

		/**
		 * Constructor
		 */
		__construct: function () {
			$( '#wccsp-select-image' ).click( WCCSP_Settings.uploader );
		},

		/**
		 * Injects the media modal and assigns the chosen attachment ID to corresponding input field
		 *
		 * @param object event
		 * @returns {boolean}
		 */
		uploader : function( event ) {
			var frame = wp.media( {
				title		: 'Select Image',
				multiple	: false,
				library		: { type : 'image' },
				button		: { text : 'Select Image' }
			} );

			frame.on( 'close', function() {
				var attachments = frame.state().get( 'selection' ).toJSON();
				$( '#wccsp_image_id' ).val( parseInt( attachments[0].id ) );
				$( '#wccsp_image_preview' ).text( 'You have chosen a new image. The preview will be updated after you click on the Save Changes button.' );
			} );

			frame.open();
			return false;
		}
	}; // end WCCSP_Settings

	WCCSP_Settings.__construct();
} );