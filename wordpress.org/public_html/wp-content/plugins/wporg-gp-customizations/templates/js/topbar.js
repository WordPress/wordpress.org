/* global $gp */
/**
 * Translation Editor Top Bar
 *
 * Renders a fixed top bar mirroring the open editor row's status-action
 * buttons (Approve / Reject / Fuzzy / Changes requested). The bar is only
 * enqueued for users with `approve` capability on the translation set, so
 * no role check is needed in JS — if this file ran, the user is a validator.
 *
 * Clicks on top-bar buttons forward to the original buttons inside the row,
 * which reuses the unchanged core GlotPress click handler.
 */
( function( $ ) {
	'use strict';

	var $topbar          = null;
	var $topbarButtons   = null;
	var userClosedTopbar = false;

	/**
	 * Populate the top bar from the currently open editor row and show it.
	 */
	function showTopbar() {
		if ( userClosedTopbar ) {
			return;
		}
		if ( ! $gp.editor.current || ! $gp.editor.current.length ) {
			return;
		}

		var $statusButtons = $gp.editor.current.find(
			'button.approve, button.reject, button.fuzzy, button.changesrequested'
		);

		$topbarButtons.empty();

		if ( ! $statusButtons.length ) {
			$topbar.hide();
			return;
		}

		$statusButtons.each( function() {
			var $original = $( this );
			var $clone    = $original.clone( true, true );

			// Avoid duplicate IDs in the DOM if the originals ever gain one.
			$clone.removeAttr( 'id' );

			// Delegated click handlers in core GlotPress editor.js are attached
			// to $gp.editor.table, which does not contain the clone. Forward
			// the click to the original button so the unchanged core handler
			// runs against the right element (this, data-nonce, etc.).
			$clone.on( 'click', function( event ) {
				event.preventDefault();
				if ( $original.prop( 'disabled' ) ) {
					return;
				}
				$original.trigger( 'click' );
			} );

			$topbarButtons.append( $clone );
		} );

		$topbar.show();
	}

	/**
	 * Empty the bar and hide it. Called when an editor row is closed.
	 */
	function hideTopbar() {
		$topbarButtons.empty();
		$topbar.hide();
	}

	/**
	 * Handler for the close (×) button. Hides the bar until the next page reload.
	 */
	function closeTopbar() {
		userClosedTopbar = true;
		hideTopbar();
	}

	$( function() {
		$topbar        = $( '#translation-editor-topbar' );
		$topbarButtons = $( '#translation-editor-topbar__buttons' );

		if ( ! $topbar.length || typeof $gp === 'undefined' || ! $gp.editor ) {
			return;
		}

		$topbar.find( '.translation-editor-topbar__close' ).on( 'click', closeTopbar );

		$gp.editor.show = ( function( original ) {
			return function() {
				original.apply( $gp.editor, arguments );
				showTopbar();
			};
		} )( $gp.editor.show );

		$gp.editor.hide = ( function( original ) {
			return function() {
				original.apply( $gp.editor, arguments );
				hideTopbar();
			};
		} )( $gp.editor.hide );
	} );

} )( jQuery );
