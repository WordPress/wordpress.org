/* global $gp, $gp_comment_feedback_settings, document, tb_show */
( function( $, $gp ) {
	$( document ).ready(
		function() {
			var rowIds = [];
			var translationIds = [];
			var originalIds = [];
			var modalFeedbackForm =
			'<div id="reject-feedback-form" style="display:none;">' +
			'<form>' +
			'<h3>Reason</h3>' +
			getReasonList() +
			'<div class="modal-comment">' +
					'<label>Comment </label>' +
					'<textarea name="modal_feedback_comment"></textarea>' +
			'</div>' +
			'<button id="modal-reject-btn" class="modal-btn gp-btn-style">Reject</button>' +
			'</form>' +
			'</div>';

			$( 'body' ).append( modalFeedbackForm );

			// Remove click event added to <summary> by wporg-gp-customizations plugin
			$( $gp.editor.table ).off( 'click', 'summary' );

			$( '#bulk-actions-toolbar-top .button, #bulk-actions-toolbar .button' ).click( function( e ) {
				rowIds = $( 'input:checked', $( 'table#translations th.checkbox' ) ).map( function() {
					var selectedRow = $( this ).parents( 'tr.preview' );
					if ( ! selectedRow.hasClass( 'untranslated' ) ) {
						return selectedRow.attr( 'row' );
					}
					$( this ).prop( 'checked', false );
					return null;
				} ).get();

				rowIds.forEach( function( rowId ) {
					var originalId = $gp.editor.original_id_from_row_id( rowId );
					var translationId = $gp.editor.translation_id_from_row_id( rowId );

					if ( originalId && translationId ) {
						originalIds.push( originalId );
						translationIds.push( translationId );
					}
				} );

				if ( $( 'select[name="bulk[action]"]' ).val() === 'reject' ) {
					e.preventDefault();
					e.stopImmediatePropagation();
					if ( ! translationIds.length ) {
						$( 'form.filters-toolbar.bulk-actions, form#bulk-actions-toolbar-top' ).submit();
						return;
					}

					// eslint-disable-next-line no-undef
					tb_show( 'Reject with Feedback', '#TB_inline?inlineId=reject-feedback-form' );
				}
			} );

			$( 'body' ).on( 'click', '#modal-reject-btn', function( e ) {
				var comment = '';
				var commentReason = [];
				var commentData = {};
				var form = $( this ).closest( 'form' );

				form.find( 'input[name="modal_feedback_reason"]:checked' ).each(
					function() {
						commentReason.push( this.value );
					}
				);

				comment = form.find( 'textarea[name="modal_feedback_comment"]' ).val();

				if ( ( ! comment.trim().length && ! commentReason.length ) || ( ! translationIds.length || ! originalIds.length ) ) {
					$( 'form.filters-toolbar.bulk-actions, form#bulk-actions-toolbar-top' ).submit();
				}

				commentData.locale_slug = $gp_comment_feedback_settings.locale_slug;
				commentData.reason = commentReason;
				commentData.comment = comment;
				commentData.original_id = originalIds;
				commentData.translation_id = translationIds;
				commentData.is_bulk_reject = true;
				commentWithFeedback( commentData, false, 'rejected' );
				e.preventDefault();
			} );

			$( '.tooltip' ).tooltip( {
				tooltipClass: 'hoverTooltip',
			} );
		}
	);

	$gp.editor.hooks.set_status_current = function() {
		setStatus( $( this ), 'current' );
	};

	$gp.editor.hooks.set_status_fuzzy = function() {
		setStatus( $( this ), 'fuzzy' );
	};

	$gp.editor.hooks.set_status_rejected = function() {
		setStatus( $( this ), 'rejected' );
	};

	function setStatus( that, status ) {
		var button = $( that );
		var feedbackData = {};
		var commentReason = [];
		var comment = '';
		var div = button.closest( 'div.meta' );

		div.find( 'input[name="feedback_reason"]:checked' ).each(
			function() {
				commentReason.push( this.value );
			}
		);

		comment = div.find( 'textarea[name="feedback_comment"]' ).val();

		if ( ! comment.trim().length && ! commentReason.length ) {
			$gp.editor.set_status( button, status );
			return;
		}

		feedbackData.locale_slug = $gp_comment_feedback_settings.locale_slug;
		feedbackData.reason = commentReason;
		feedbackData.comment = comment;
		feedbackData.original_id = [ $gp.editor.current.original_id ];
		feedbackData.translation_id = [ $gp.editor.current.translation_id ];

		commentWithFeedback( feedbackData, button, status );
	}

	function commentWithFeedback( feedbackData, button, status ) {
		var data = {};
		var div = {};
		if ( button ) {
			div = button.closest( 'div.meta' );
		}

		data = {
			action: 'comment_with_feedback',
			data: feedbackData,

			_ajax_nonce: $gp_comment_feedback_settings.nonce,
		};

		$.ajax(
			{
				type: 'POST',

				url: $gp_comment_feedback_settings.url,
				data: data,
			}
		).done(
			function() {
				if ( feedbackData.is_bulk_reject ) {
					$( 'form.filters-toolbar.bulk-actions, form#bulk-actions-toolbar-top' ).submit();
				} else {
					$gp.editor.set_status( button, status );
					div.find( 'input[name="feedback_reason"]' ).prop( 'checked', false );
					div.find( 'textarea[name="feedback_comment"]' ).val( '' );
				}
			}
		).fail(
			function( xhr, msg ) {
				msg = 'An error has occurred';
				if ( xhr.responseText ) {
					msg += ': ' + xhr.responseText;
				}
				msg += '. Please, take a screenshot, send it to the developers, and reload the page to see if it still worked.';
				$gp.notices.error( msg );
			}
		);
	}

	function getReasonList( ) {
		var commentReasons = $gp_comment_feedback_settings.comment_reasons;
		var commentList = '';
		var prefix = '';
		var suffix = '';
		var inputName = '';

		// eslint-disable-next-line vars-on-top
		for ( var reason in commentReasons ) {
			prefix = '<div class="modal-item"><label class="tooltip" title="' + commentReasons[ reason ].explanation + '">';
			suffix = '</label> <span class="tooltip dashicons dashicons-info" title="' + commentReasons[ reason ].explanation + '"></span></div>';
			inputName = 'modal_feedback_reason';
			commentList += prefix + '<input type="checkbox" name="' + inputName + '" value="' + reason + '" /> ' + commentReasons[ reason ].name + suffix;
		}
		return commentList;
	}
}( jQuery, $gp )
);
