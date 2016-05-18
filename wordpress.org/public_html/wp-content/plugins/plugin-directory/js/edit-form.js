/*
 * JS for the Plugin Admin screens.
 */

( function( $, wp, pluginDirectory ) {
	var PluginEdit = {
		$testedWith: {},
		$pluginStatus: {},

		ready: function() {
			PluginEdit.$testedWith   = $( '#tested-with-select' );
			PluginEdit.$pluginStatus = $( '#plugin-status-select' );

			$( '#submitdiv' )
				.on( 'click', '.edit-tested-with',     PluginEdit.editTestedWith )
				.on( 'click', '.edit-plugin-status',   PluginEdit.editPluginStatus )
				.on( 'click', '.save-tested-with',     PluginEdit.updateTestedWith )
				.on( 'click', '.save-plugin-status',   PluginEdit.updatePluginStatus )
				.on( 'click', '.cancel-tested-with',   PluginEdit.cancelTestedWith )
				.on( 'click', '.cancel-plugin-status', PluginEdit.cancelPluginStatus );

			_.each( $( '#post-body' ).find( '.comments-box' ), PluginEdit.loadComments );

			$( '#add-new-comment' ).on( 'click', 'a.button', PluginEdit.prepareCommentForm );

			$( '#add-committer-toggle' ).on( 'click', PluginEdit.toggleCommitterForm );

			$( '#the-committer-list' ).wpList({
				alt: false,
				confirm: function( element, settings, action ) {
					if ( 'committer' === settings.what && 'delete' === action ) {
						return confirm( pluginDirectory.removeCommitterAYS );
					}
					return true;
				},
				addAfter: PluginEdit.committerRequestAfter,
				delAfter: PluginEdit.committerRequestAfter
			}).on( 'wpListAddEnd', function() {
				$( 'input[name="add_committer"]', '#add-committer' ).val( '' ).focus();
			} );

			$( '#contact-author' ).appendTo( '#plugin-review .inside' );
		},

		editTestedWith: function() {
			if ( PluginEdit.$testedWith.is( ':hidden' ) ) {
				PluginEdit.$testedWith.slideDown( 'fast', function() {
					$( 'select', PluginEdit.$testedWith ).focus();
				} );
				$( this ).hide();
			}
		},

		editPluginStatus: function() {
			if ( PluginEdit.$pluginStatus.is( ':hidden' ) ) {
				PluginEdit.$pluginStatus.slideDown( 'fast', function() {
					$( 'select', PluginEdit.$pluginStatus ).focus();
				} );
				$( this ).hide();
			}
		},

		updateTestedWith: function() {
			PluginEdit.$testedWith.slideUp( 'fast' ).siblings( 'button.edit-tested-with' ).show().focus();
			$( '#tested-with-display' ).text( $( 'option:selected', PluginEdit.$testedWith ).text() );
		},

		updatePluginStatus: function() {
			PluginEdit.$pluginStatus.slideUp( 'fast' ).siblings( 'button.edit-plugin-status' ).show().focus();
			$( '#plugin-status-display' ).text( $( 'option:selected', PluginEdit.$pluginStatus ).text() );
		},

		cancelTestedWith: function() {
			$( '#tested-with' ).val( $( '#hidden-tested-with' ).val() );
			PluginEdit.updateTestedWith();
		},

		cancelPluginStatus: function() {
			$( '#post-status' ).val( $( '#hidden-post-status' ).val() );
			PluginEdit.updatePluginStatus( event );
		},

		loadComments: function ( element ) {
			var $commentsList = $( element ),
				data = {
					_ajax_nonce:  $( '#add_comment_nonce' ).val(),
					p:            $( '#post_ID' ).val(),
					mode:         'single',
					start:        0,
					number:       20,
					comment_type: $commentsList.data( 'comment-type' )
				};

			wp.ajax.post( 'get-comments', data ).always( function( response ) {
				response = wpAjax.parseAjaxResponse( response );

				if ( 'object' == typeof response && response.responses[0] ) {
					$commentsList.append( response.responses[0].data ).show();

					$( 'a[className*=\':\']' ).unbind();
				}
			} );
		},

		prepareCommentForm: function( event ) {
			event.preventDefault();

			window.commentReply && commentReply.addcomment( $( '#post_ID' ).val() );

			// Add a field with the custom comment type.
			$( '#replyrow' ).find( '.comment-reply' ).append( $( '<input/>' ).attr({
				type: 'hidden',
				name: 'comment_type',
				value: $( '.comments-box' ).data( 'comment-type' )
			}) );
		},

		toggleCommitterForm: function( event ) {
			var $form = $( '#add-committer' );

			// Show/hide form.
			$form.toggleClass( 'wp-hidden-children' );

			// Focus on the input field, and on enter add the committer, don't save post.
			$( 'input[name="add_committer"]', $form ).focus().on( 'keydown', function( event ) {
				if ( 13 === event.which ) {
					event.preventDefault();
					$( '#add-committer-submit', $form ).click();
				}
			} );
		},

		committerRequestAfter: function( response, data ) {
			if ( data.parsed.errors ) {
				$( '#committer-error' ).html( data.parsed.responses[0].errors[0].message ).show();
			} else {
				$( '#committer-error' ).empty().hide();
			}
		}
	};

	$( PluginEdit.ready );
} )( window.jQuery, window.wp, window.pluginDirectory );
