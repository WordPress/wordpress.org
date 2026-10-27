/* global $gp, $gp_editor_options, wp */
/**
 * Inline action buttons in the translation editor.
 *
 * For validators on a translation set, three buttons (Approve / Reject / Fuzzy)
 * appear in a new column on each row. Clicking a button POSTs to the same
 * set-status endpoint the Meta panel buttons use, then swaps the row HTML
 * with the server's response. No editor expansion, no advance to the next row.
 */
( function( $ ) {
	'use strict';

	$( function() {
		if ( typeof $gp === 'undefined' || ! $gp.editor || ! $gp.editor.table ) {
			return;
		}

		// Add a matching column header to the table's thead so the column
		// count lines up with the new <td class="inline-actions"> emitted
		// per row. Core GlotPress renders the thead inline in
		// gp-templates/translations.php with no extension point, so we
		// inject the <th> from JS — gated by this script being enqueued,
		// which already requires the inline-actions feature to be enabled.
		var $thead_tr = $gp.editor.table.find( 'thead tr' );
		if ( $thead_tr.length && ! $thead_tr.find( '.gp-column-inline-actions' ).length ) {
			// Use .text() to safely inject the translated label without
			// risking HTML interpretation of the translation string.
			$thead_tr.append(
				$( '<th class="gp-column-inline-actions"></th>' )
					.text( wp.i18n.__( 'Actions', 'glotpress' ) )
			);
		}

		$gp.editor.table.on( 'click', '.inline-action', function( event ) {
			event.preventDefault();

			var $btn           = $( this );
			var translation_id = $btn.data( 'translation-id' );
			var status         = $btn.data( 'status' );
			var nonce          = $btn.data( 'nonce' );
			var $preview       = $btn.closest( 'tr.preview' );
			var preview_id     = $preview.attr( 'id' );

			if ( ! preview_id || ! translation_id || ! status || ! nonce ) {
				return;
			}

			// Row pair: tr.preview#preview-X-Y and tr.editor#editor-X-Y.
			var row_key  = preview_id.replace( /^preview-/, '' );
			var $editor  = $( '#editor-' + row_key );
			var $row_set = $preview.add( $editor );

			$btn.prop( 'disabled', true );

			var status_name;
			switch ( status ) {
				case 'current':
					status_name = wp.i18n._x( 'current', 'Single Status', 'glotpress' );
					break;
				case 'rejected':
					status_name = wp.i18n._x( 'rejected', 'Single Status', 'glotpress' );
					break;
				case 'fuzzy':
					status_name = wp.i18n._x( 'fuzzy', 'Single Status', 'glotpress' );
					break;
				default:
					status_name = status;
			}

			$gp.notices.notice(
				wp.i18n.sprintf(
					/* translators: %s: Status name. */
					wp.i18n.__( 'Setting status to &#8220;%s&#8221;&hellip;', 'glotpress' ),
					status_name
				)
			);

			$.ajax( {
				type: 'POST',
				url:  $gp_editor_options.set_status_url,
				data: {
					translation_id:  translation_id,
					status:          status,
					_gp_route_nonce: nonce
				},
				success: function( response ) {
					$gp.notices.success( wp.i18n.__( 'Status set!', 'glotpress' ) );

					// If $gp.editor.current points at the editor row we're
					// about to remove, clear it so later GP code doesn't
					// operate on a detached element.
					if ( $gp.editor.current && $editor.length
					     && $gp.editor.current[0] === $editor[0] ) {
						$gp.editor.current = null;
					}

					// Replace the row pair with the server-rendered markup.
					// $row_set.first() is the preview <tr>; inserting the
					// response before it and removing the original pair
					// gives a clean in-place swap whether the response is
					// one <tr> or two.
					$row_set.first().before( response );
					$row_set.remove();
				},
				error: function( xhr ) {
					$btn.prop( 'disabled', false );
					var msg = xhr.responseText
						? wp.i18n.sprintf(
							/* translators: %s: Error message. */
							wp.i18n.__( 'Error: %s', 'glotpress' ),
							xhr.responseText
						)
						: wp.i18n.__( 'Error setting the status!', 'glotpress' );
					$gp.notices.error( msg );
				}
			} );
		} );
	} );

} )( jQuery );
