/**
 * Discarding a reply or note: at once when there's nothing in it, and otherwise asking next to the button, rather than
 * at the top of the window, a long way from it, as core does. Escape discards it too, as its button does.
 *
 * Core's discard buttons, the editor's and the drafts' Discard links, open core's "Discard this draft?" dialog and set
 * up its Yes as they're clicked; this runs right after, in the same click, before the window shows anything. For an
 * empty reply or note, it clicks Yes itself, with the dialog never showing, so core discards it as it would.
 *
 * @param {jQuery} $ jQuery.
 */
( function ( $ ) {
	/**
	 * The page's class while a dialog is answered for the agent, which site.css hides it by.
	 *
	 * @type {string}
	 */
	const SILENT = 'wporgsite-discarding';

	/**
	 * The editor's discard button, which has no class of its own.
	 *
	 * @type {string}
	 */
	const TRASH = '.note-toolbar .note-btn:has( > .glyphicon-trash )';

	/**
	 * How far from the button the dialog is, and from the window's edges, in pixels.
	 *
	 * @type {number}
	 */
	const GAP = 8;

	/**
	 * The dialog core just opened to confirm discarding, if it did.
	 *
	 * @return {jQuery} Dialog, or none.
	 */
	function confirmDialog() {
		return $( '.modal:visible' ).has( '.discard-draft-confirm' ).last();
	}

	/**
	 * Whether the open reply or note has nothing in it: no text, nothing like an image, and no attachments.
	 *
	 * @return {boolean} Whether it's empty.
	 */
	function isEmpty() {
		const editable = $( '.conv-reply-block .note-editable' ).get( 0 );
		if ( ! editable ) {
			return false;
		}

		return (
			'' === editable.textContent.replace( /[\s\u200b\ufeff]+/g, '' ) &&
			! editable.querySelector( 'img, iframe, video, hr, table' ) &&
			! $( '.conv-reply-block .attachments-upload li' ).length
		);
	}

	/**
	 * Moves a dialog next to the button that opened it: above it, or below it where there's no room above, and within
	 * the window.
	 *
	 * @param {jQuery}      $modal Dialog.
	 * @param {HTMLElement} button Button.
	 */
	function placeNear( $modal, button ) {
		const dialog = $modal.find( '.modal-dialog' ).get( 0 );
		if ( ! dialog ) {
			return;
		}

		dialog.style.position = 'absolute';
		dialog.style.margin = '0';

		const anchor = button.getBoundingClientRect();
		const box = dialog.getBoundingClientRect();
		const clamp = ( value, max ) =>
			Math.max( GAP, Math.min( value, max - GAP ) );

		let top = anchor.top - box.height - GAP;
		if ( top < GAP ) {
			top = anchor.bottom + GAP;
		}

		dialog.style.top = clamp( top, window.innerHeight - box.height ) + 'px';
		dialog.style.left =
			clamp(
				anchor.left + anchor.width / 2 - box.width / 2,
				window.innerWidth - box.width
			) + 'px';
	}

	/**
	 * Escape discards the open reply or note, clicking its discard button: at once when it's empty, and otherwise
	 * asking, with Yes focused, so Enter confirms, and Escape again cancels, back to where the caret was. From the form,
	 * or with nothing in particular focused; not from other fields, nor while something else takes Escape, like a
	 * dialog, a menu, or a recipient field's list.
	 *
	 * @param {KeyboardEvent} event Event.
	 */
	function onKeydown( event ) {
		if (
			'Escape' !== event.key ||
			event.defaultPrevented ||
			event.repeat ||
			event.isComposing ||
			event.ctrlKey ||
			event.altKey ||
			event.metaKey ||
			event.shiftKey
		) {
			return;
		}

		const block = document.querySelector(
			'.conv-reply-block:not( .hidden )'
		);
		const target = event.target;
		if ( ! block || ! ( target instanceof window.Element ) ) {
			return;
		}

		const nowhere =
			target === document.body ||
			target.classList.contains( 'wporgsite-alt-column' );
		if (
			( ! nowhere && ! block.contains( target ) ) ||
			document.body.classList.contains( 'modal-open' ) ||
			document.querySelector(
				'.modal.in, .open > .dropdown-menu, .select2-container--open'
			)
		) {
			return;
		}

		const $button = $( block ).find( TRASH ).first();
		if ( ! $button.length ) {
			return;
		}

		event.preventDefault();

		// Where the caret is, to put it back if the agent cancels.
		const editable = target.closest( '.note-editable' );
		const $note = editable
			? $( editable ).closest( '.note-editor' ).prev()
			: $();
		const keep =
			'function' === typeof $.fn.summernote && $note.data( 'summernote' );
		if ( keep ) {
			$note.summernote( 'editor.saveRange' );
		}

		$button.trigger( 'click' );

		const $modal = confirmDialog();
		if (
			! keep ||
			! $modal.length ||
			document.documentElement.classList.contains( SILENT )
		) {
			return;
		}

		let confirmed = false;
		$modal.find( '.discard-draft-confirm' ).one( 'click', () => {
			confirmed = true;
		} );
		$modal.one( 'hidden.bs.modal', () => {
			if ( ! confirmed && ! block.classList.contains( 'hidden' ) ) {
				$note.summernote( 'editor.restoreRange' );
			}
		} );
	}

	// After the other scripts', like the Saved Replies menu's, which take Escape while their menus are open.
	document.addEventListener( 'keydown', onKeydown );

	// The editor's discard button, and the drafts' Discard links.
	$( document ).on( 'click', TRASH + ', .discard-draft-trigger', function () {
		const $modal = confirmDialog();
		if ( ! $modal.length ) {
			return;
		}

		const fromEditor = ! $( this ).hasClass( 'discard-draft-trigger' );
		if ( fromEditor && isEmpty() ) {
			// The button goes with the form, from under the pointer, which would leave its tooltip behind.
			if ( 'function' === typeof $.fn.tooltip ) {
				$( this ).tooltip( 'hide' );
			}

			// Hidden until core closes it: a reply's draft is discarded on the server first.
			document.documentElement.classList.add( SILENT );
			$modal.one( 'hidden.bs.modal', () =>
				document.documentElement.classList.remove( SILENT )
			);
			$modal.find( '.discard-draft-confirm' ).first().trigger( 'click' );
			return;
		}

		placeNear( $modal, this );
	} );
} )( jQuery );
