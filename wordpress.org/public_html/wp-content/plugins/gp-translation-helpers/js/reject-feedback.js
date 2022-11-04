/* global $gp, $gp_comment_feedback_settings, document, tb_show, console */
( function( $, $gp ) {
	$( document ).ready(
		function() {
			var rowIds = [];
			var translationIds = [];
			var originalIds = [];
			var modalFeedbackForm =
			'<div id="reject-feedback-form" style="display:none;">' +
			'<form>' +
			'<h3>Type (Optional)</h3>' +
			getReasonList() +
			'<div class="modal-comment">' +
					'<h3><label for="modal_feedback_comment">Comment (Optional)</label></h3>' +
					'<textarea id="modal_feedback_comment" name="modal_feedback_comment"></textarea>' +
			'</div>' +
			'<button id="modal-reject-btn" class="modal-btn gp-btn-style">Reject without Feedback</button>' +
			'<button id="modal-requestchanges-btn" class="modal-btn gp-btn-style" style="display: none;" class="modal-btn">Request changes</button>' +
			'</form>' +
			'</div>';

			$( 'body' ).append( modalFeedbackForm );

			// Remove click event added to <summary> by wporg-gp-customizations plugin
			$( $gp.editor.table ).off( 'click', 'summary' );

			$( '#bulk-actions-toolbar-top .button, #bulk-actions-toolbar-bottom .button' ).click(
				function( e ) {
					rowIds = $( 'input:checked', $( 'table#translations th.checkbox' ) ).map(
						function() {
							var selectedRow = $( this ).parents( 'tr.preview' );
							if ( ! selectedRow.hasClass( 'untranslated' ) ) {
								return selectedRow.attr( 'row' );
							}
							$( this ).prop( 'checked', false );
							return null;
						}
					).get();

					rowIds.forEach(
						function( rowId ) {
							var originalId = $gp.editor.original_id_from_row_id( rowId );
							var translationId = $gp.editor.translation_id_from_row_id( rowId );

							if ( originalId && translationId ) {
								originalIds.push( originalId );
								translationIds.push( translationId );
							}
						}
					);

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
				}
			);

			/**
			 * Changes the value for the rejected status in the top toolbar to "changes requested"
			 *
			 * @param {Object} thisObj The object that dispatches this call.
			 */
			function updateBulkRejectStatus( thisObj ) {
				var form = thisObj.closest( 'form' );
				var commentText = form.find( 'textarea[name="modal_feedback_comment"]' ).val();
				var numberOfCheckedReasons = form.find( 'input[name="modal_feedback_reason"]:checked' ).length;
				if ( commentText || numberOfCheckedReasons ) {
					$( 'form#bulk-actions-toolbar-top  option[value="reject"]' ).attr( 'value', 'changesrequested' ).text( 'Changes requested' );
				}
			}

			$( 'body' ).on(
				'click',
				'#modal-reject-btn, #modal-requestchanges-btn',
				function( e ) {
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
					updateBulkRejectStatus( $( this ) );
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
				}
			);

			$( '.feedback-reason-list' ).on(
				'click',
				function( e ) {
					toggleButtons( $( this ), e );
				}
			);
			$( '.feedback-comment' ).on(
				'input',
				function( e ) {
					toggleButtons( $( this ), e );
				}
			);

			/**
			 * Hide and show one of each two buttons in the individual rejection: "Reject" and "Request changes".
			 *
			 * If the user has checked some reason or has entered some text in the textarea,
			 * this function hides the "Reject" button and shows the "Request changes" one.
			 * Otherwise, does the opposite.
			 *
			 * @param {Object}         thisObj The object that dispatches this call.
			 * @param {document#event} event   The event.
			 */
			function toggleButtons( thisObj, event ) {
				var form = thisObj.closest( 'form' );
				var commentText = form.find( 'textarea[name="feedback_comment"]' ).val();
				var div = thisObj.closest( '.meta' );
				var rejectButton = $( '.reject', div );
				var changesRequestedtButton = $( '.changesrequested', div );
				var numberOfCheckedReasons = form.find( 'input[name="feedback_reason"]:checked' ).length;

				if ( commentText.trim() !== '' || numberOfCheckedReasons ) {
					rejectButton.hide();
					changesRequestedtButton.show();
				} else {
					rejectButton.show();
					changesRequestedtButton.hide();
				}
				event.stopImmediatePropagation();
			}

			$( '.modal-item' ).on(
				'click',
				function( e ) {
					toggleModalButtons( $( this ), e );
				}
			);
			$( 'textarea[name="modal_feedback_comment"]' ).on(
				'input',
				function( e ) {
					toggleModalButtons( $( this ), e );
				}
			);

			/**
			 * Hide and show one of each two buttons in the reject modal: "Reject" and "Request changes".
			 *
			 * In the modal, if the user has checked some reason or has entered some text in the textarea,
			 * this function hides the "Reject" button and shows the "Request changes" one.
			 * Otherwise, does the opposite.
			 *
			 * @param {Object}         thisObj The object that dispatches this call.
			 * @param {document#event} event   The event.
			 */
			function toggleModalButtons( thisObj, event ) {
				var form = thisObj.closest( 'form' );
				var commentText = form.find( 'textarea[name="modal_feedback_comment"]' ).val();
				var div = thisObj.closest( '#TB_ajaxContent' );
				var rejectButton = $( '#modal-reject-btn', div );
				var changesRequestedtButton = $( '#modal-requestchanges-btn', div );
				var numberOfCheckedReasons = form.find( 'input[name="modal_feedback_reason"]:checked' ).length;

				if ( commentText.trim() !== '' || numberOfCheckedReasons ) {
					rejectButton.hide();
					changesRequestedtButton.show();
				} else {
					rejectButton.show();
					changesRequestedtButton.hide();
				}
				event.stopImmediatePropagation();
			}

			$( '.tooltip' ).tooltip(
				{
					tooltipClass: 'hoverTooltip',
				}
			);

			$( 'input[name="feedback_reason"][value="glossary"]' ).change(
				function() {
					var glossaryWords = $( this ).closest( 'tr' ).find( '.original .glossary-word' ).get().map( function( word ) {
						return word.innerText;
					} );
					if ( $( this ).is( ':checked' ) && glossaryWords.length ) {
						// eslint-disable-next-line vars-on-top
						var glossaryList = document.createElement( 'ul' );
						glossaryList.innerHTML = '<h6>Glossary Words</h6>';
						$( glossaryList ).attr( 'id', 'glossary-item-list' );
						glossaryWords.forEach(
							function( item ) {
								var li = document.createElement( 'li' );
								var checkbox = $( '<input />', { type: 'checkbox', class: 'glossary-word-item', value: item } );
								$( '<label></label>' ).html( checkbox ).append( item ).appendTo( li );
								glossaryList.appendChild( li );
							}
						);
						$( this ).closest( 'ul' ).after( glossaryList );
					} else {
						$( '#glossary-item-list' ).remove();
						$( this ).closest( '.feedback-reason-list' ).siblings( '.feedback-comment' ).find( 'textarea' ).val( '' );
					}
				}
			);

			$( 'body' ).on(
				'change',
				'input.glossary-word-item', function( ) {
					var textArea = $( this ).closest( 'ul' ).next().find( 'textarea' );
					if ( $( this ).closest( 'ul' ).find( 'input:checked' ).length === 0 ) {
						textArea.val( '' );
						return;
					}
					// eslint-disable-next-line vars-on-top
					var message = 'There is a problem with ' + ( $( this ).closest( 'ul' ).find( 'input:checked' ).length === 1 ? 'the glossary term' : 'the following glossary terms' ) + ': ' + $( this ).closest( 'ul' ).find( 'input:checked' ).get().map( function( word ) {
						return word.defaultValue;
					} ).join( ', ' );

					textArea.val( message );
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

	$gp.editor.hooks.set_status_changesrequested = function() {
		setStatus( $( this ), 'changesrequested' );
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

		if ( ( comment === undefined && ! commentReason.length ) || ( ! comment.trim().length && ! commentReason.length ) ) {
			$gp.editor.set_status( button, status );
			return;
		}

		feedbackData.locale_slug = $gp_comment_feedback_settings.locale_slug;
		feedbackData.reason = commentReason;
		feedbackData.comment = comment;
		feedbackData.original_id = [ $gp.editor.current.original_id ];
		feedbackData.translation_id = [ $gp.editor.current.translation_id ];
		feedbackData.translation_status = status;

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
				/* eslint no-console: ["error", { allow: ["error"] }] */
				console.error( data );
				msg = 'An error has occurred';
				if ( xhr.responseText ) {
					msg += ': ' + xhr.responseText;
				}
				msg += '. Please, take a screenshot of the output in the browser console, send it to the developers, and reload the page to see if it works.';
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
