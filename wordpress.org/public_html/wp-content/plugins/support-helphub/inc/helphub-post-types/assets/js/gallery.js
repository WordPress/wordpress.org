/* globals HelphubGallery */
jQuery( document ).ready( function( $ ) {

	// Uploading files
	var HelphubGalleryFrame;
	var $GalleryContainer  = $( '#helphub_images_container' );
	var $ImageGalleryIds  = $( '#helphub_image_gallery' );
	var $GalleryImages     = $GalleryContainer.find( 'ul.product_images' );
	var $GalleryUl         = $GalleryContainer.find( 'ul li.image' );

	jQuery( '.add_helphub_images' ).on( 'click', 'a', function( event ) {

		var AttachmentIds  = $ImageGalleryIds.val();

		event.preventDefault();

		// If the media frame already exists, reopen it.
		if ( HelphubGalleryFrame ) {
			HelphubGalleryFrame.open();
			return;
		}

		// Create the media frame.
		HelphubGalleryFrame = wp.media.frames.downloadable_file = wp.media({

			// Set the title of the modal.
			title: HelphubGallery.gallery_title,
			button: {
				text: HelphubGallery.gallery_button
			},
			multiple: true
		});

		// When an image is selected, run a callback.
		HelphubGalleryFrame.on( 'select', function() {

			var selection = HelphubGalleryFrame.state().get( 'selection' );

			selection.map( function( attachment ) {

				attachment = attachment.toJSON();

				if ( attachment.id ) {
					AttachmentIds = AttachmentIds ? AttachmentIds + ',' + attachment.id : attachment.id;

					$GalleryImages.append( '<li class="image" data-attachment_id="' + attachment.id + '">' +
								'<img src="' + attachment.sizes.thumbnail.url + '" />' +
									'<ul class="actions">' +
										'<li><a href="#" class="delete" title="' + HelphubGallery.delete_image + '">&times;</a></li>' +
									'</ul>' +
								'</li>' );
				}

			} );

			$ImageGalleryIds.val( AttachmentIds );
		});

		// Finally, open the modal.
		HelphubGalleryFrame.open();
	});

	// Image ordering
	$GalleryImages.sortable({
		items: 'li.image',
		cursor: 'move',
		scrollSensitivity: 40,
		forcePlaceholderSize: true,
		forceHelperSize: false,
		helper: 'clone',
		opacity: 0.65,
		placeholder: 'helphub-metabox-sortable-placeholder',
		start: function( event, ui ) {
			ui.item.css( 'background-color', '#f6f6f6' );
		},
		stop: function( event, ui ) {
			ui.item.removeAttr( 'style' );
		},
		update: function() {
			var AttachmentIds = '';
			$GalleryContainer.find( 'ul li.image' ).css( 'cursor', 'default' ).each( function() {
				var AttachmentId = jQuery( this ).attr( 'data-attachment_id' );
				AttachmentIds = AttachmentIds + AttachmentId + ',';
			});
			$ImageGalleryIds.val( AttachmentIds );
		}
	});

	// Remove images
	$GalleryContainer.on( 'click', 'a.delete', function() {
        var AttachmentIds = '';

		$( this ).closest( 'li.image' ).remove();

		$GalleryUl.css( 'cursor', 'default' ).each( function() {
			var AttachmentId = jQuery( this ).attr( 'data-attachment_id' );
			AttachmentIds = AttachmentIds + AttachmentId + ',';
		});

		$ImageGalleryIds.val( AttachmentIds );

		return false;
	} );
} );
