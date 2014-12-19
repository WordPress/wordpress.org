/**
 * Explanations JS.
 */

( function( $ ) {

	//
	// Explanations AJAX handlers.
	//

	var statusLabel   = $( '#status-label' ),
		createLink    = $( '#create-expl' ),
		unPublishLink = $( '#unpublish-expl' ),
		rowActions    = $( '#expl-row-actions' );

	/**
	 * AJAX handler for creating and associating a new explanation post.
	 *
	 * @param {object} event Event object.
	 */
	function createExplanation( event ) {
		event.preventDefault();

		wp.ajax.send( 'new_explanation', {
			success: createExplSuccess,
			error:   createExplError,
			data:    {
				nonce:   $( this ).data( 'nonce' ),
				post_id: $( this ).data( 'id' )
			}
		} );
	}

	/**
	 * Success callback for creating a new explanation via AJAX.
	 *
	 * @param {object} data Data response object.
	 */
	function createExplSuccess( data ) {
		createLink.hide();
		rowActions.html( '<a href="post.php?post=' + data.post_id + '&action=edit">' + wporg.editContentLabel + '</a>' );
		statusLabel.text( wporg.statusLabel.draft );
	}

	/**
	 * Error callback for creating a new explanation via AJAX.
	 *
	 * @param {object} data Data response object.
	 */
	function createExplError( data ) {}

	/**
	 * Handler for un-publishing an existing Explanation.
	 *
	 * @param {object} event Event object.
	 */
	function unPublishExplantaion( event ) {
		event.preventDefault();

		wp.ajax.send( 'un_publish', {
			success: unPublishSuccess,
			error:   unPublishError,
			data:    {
				nonce:   $( this ).data( 'nonce' ),
				post_id: $( this ).data( 'id' )
			}
		} );
	}

	/**
	 * Success callback for un-publishing an explanation via AJAX.
	 *
	 * @param {object} data Data response object.
	 */
	function unPublishSuccess( data ) {
		if ( statusLabel.hasClass( 'pending' ) || statusLabel.hasClass( 'publish' ) ) {
			statusLabel.removeClass( 'pending publish' ).text( wporg.statusLabel.draft );
		}
		unPublishLink.hide();
	}

	/**
	 * Error callback for un-publishing an explanation via AJAX.
	 *
	 * @param {object} data Data response object.
	 */
	function unPublishError( data ) {}

	// Events.
	$( '#create-expl' ).on( 'click', createExplanation );
	$( '#unpublish-expl' ).on( 'click', unPublishExplantaion );

} )( jQuery );
