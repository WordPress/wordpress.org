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
			} );

			frame.open();
			return false;
		}
	}; // end WCCSP_Settings

	WCCSP_Settings.__construct();
} );