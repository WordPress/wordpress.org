/* globals HelphubAdmin */
jQuery( document ).ready( function( $ ) {

	// Instantiates the variable that holds the media library frame.
	var GalleryDataFrame;

	// Runs when the image button is clicked.
	jQuery( '.postbox' ).on( 'click', '.helphub-upload', function( event ) {

        // Store button object.
        var $button = $( this ),
			Title,
			Button,
			Library;

		// Prevents the default action from occuring.
		event.preventDefault();

		// If the frame already exists, re-open it.
		if ( GalleryDataFrame ) {
			GalleryDataFrame.open();
			return;
		}

		Title = $button.data( 'title' ) ? $button.data( 'title' ) : HelphubAdmin.default_title;
		Button = $button.data( 'button' ) ? $button.data( 'button' ) : HelphubAdmin.default_button;
		Library = $button.data( 'library' ) ? $button.data( 'library' ) : '';

		// Sets up the media library frame.
		GalleryDataFrame = wp.media.frames.gallery_data_frame = wp.media({
			title: Title,
			button: { text: Button },
			library: { type: Library }
		});

		// Runs when an image is selected.
		GalleryDataFrame.on( 'select', function() {

			// Grabs the attachment selection and creates a JSON representation of the model.
			var MediaAttachment = GalleryDataFrame.state().get( 'selection' ).first().toJSON();

			// Sends the attachment URL to our custom image input field.
			$button.prev( 'input.helphub-upload-field' ).val( MediaAttachment.url );

		});

		// Opens the media library frame.
		GalleryDataFrame.open();
	});

	if ( $( 'input[type="date"]' ).hasClass( 'helphub-meta-date' ) ) {
		$( '.helphub-meta-date' ).datepicker({
			changeMonth: true,
			changeYear: true,
			formatDate: 'MM, dd, yy'
		});
	} // Bust cache.
});
