( function( $ ){
	var $html = $( 'html' );
	var $document = $( document );


	// Open the modal for translation help.
	function openModal( $modal ) {
		var $closeButton = $modal.find( '.wporg-translate-modal__close' );

		$html.addClass( 'modal-open' );
		$modal.addClass( 'wporg-translate-modal--open' );
		$closeButton.focus();

		$document.on( 'keydown.modal', function( event ) {
			if ( 27 !== event.which ) { // ESC key.
				return;
			}

			$modal.removeClass( 'wporg-translate-modal--open' );
			$html.removeClass( 'modal-open' );
			$document.off( 'keydown.modal' );
		} );

		$closeButton.one( 'click', function() {
			$modal.removeClass( 'wporg-translate-modal--open' );
			$html.removeClass( 'modal-open' );
			$document.off( 'keydown.modal' );
		} );
	}

	function openDiscussionModal() {
		var $modal = $gp.editor.current.find( '.feedback-modal__start-discussion' );
		openModal( $modal );
	}

	function submitDiscussion() {

	}

	function submitRejection() {

	}

	$gp.editor.hooks.set_status_rejected = function() {
		var $modal = $gp.editor.current.find( '.feedback-modal__reject-with-feedback' );
		openModal( $modal );

		return false;
	};

	$gp.editor.install_hooks = ( function( original ) {
		return function() {
			original.apply( $gp.editor, arguments );

			$( $gp.editor.table )
				.on( 'click', 'button.button__start-discussion', openDiscussionModal )
				.on( 'click', '.feedback-modal__reject-with-feedback button.feedback__action-submit', submitRejection )
				.on( 'click', '.feedback-modal__start-discussion button.feedback__action-submit', submitDiscussion );
		}
	})( $gp.editor.install_hooks );
})( jQuery );
