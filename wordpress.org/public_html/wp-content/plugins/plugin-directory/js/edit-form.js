/*
 * JS for the Plugin Admin screens.
 */

( function( $, wp, pluginDirectory ) {
	var PluginEdit = {

		ready: function() {
			$( '#submitdiv' ).on( 'click', '.set-plugin-status', PluginEdit.setPluginStatus );

			_.each( $( '#post-body' ).find( '.comments-box' ), PluginEdit.loadComments );

			$( '#add-new-comment' ).on( 'click', 'a.button', PluginEdit.prepareCommentForm );
			$( '#the-comment-list' ).on( 'click', '.reply a', PluginEdit.addCommentTypeField );

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

			$( '#add-support-rep-toggle' ).on( 'click', PluginEdit.toggleSupportRepForm );

			$( '#the-support-rep-list' ).wpList({
				alt: false,
				confirm: function( element, settings, action ) {
					if ( 'support-rep' === settings.what && 'delete' === action ) {
						return confirm( pluginDirectory.removeSupportRepAYS );
					}
					return true;
				},
				addAfter: PluginEdit.supportRepRequestAfter,
				delAfter: PluginEdit.supportRepRequestAfter
			}).on( 'wpListAddEnd', function() {
				$( 'input[name="add_support_rep"]', '#add-support-rep' ).val( '' ).focus();
			} );

			$( '#contact-author' ).appendTo( '#plugin-review .inside' );
		},

		setPluginStatus: function() {
			if ( 'approved' === $(this).val() ) {
				return confirm( pluginDirectory.approvePluginAYS );
			} else if ( 'rejected' === $(this).val() ) {
				return confirm( pluginDirectory.rejectPluginAYS );
			} else {
				return true;
			}
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

					if ( 0 === location.hash.indexOf( '#comment-' ) ) {
						$( document.body ).animate( { scrollTop: $( location.hash ).offset().top - 100 }, 100 );
					}
				}
			} );
		},

		prepareCommentForm: function( event ) {
			event.preventDefault();

			window.commentReply && commentReply.addcomment( $( '#post_ID' ).val() );

			PluginEdit.addCommentTypeField( event );
		},

		addCommentTypeField: function( event ) {
			if ( 0 === $( '#replyrow' ).find( '.comment-reply input[name="comment_type"]' ).length ) {
				// Add a field with the custom comment type.
				$( '#replyrow' ).find( '.comment-reply' ).append( $( '<input/>' ).attr({
					type: 'hidden',
					name: 'comment_type',
					value: $( '.comments-box' ).data( 'comment-type' )
				}) );
			}
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
		},

		toggleSupportRepForm: function( event ) {
			var $form = $( '#add-support-rep' );

			// Show/hide form.
			$form.toggleClass( 'wp-hidden-children' );

			// Focus on the input field, and on enter add the support rep, don't save post.
			$( 'input[name="add_support_rep"]', $form ).focus().on( 'keydown', function( event ) {
				if ( 13 === event.which ) {
					event.preventDefault();
					$( '#add-support-rep-submit', $form ).click();
				}
			} );
		},

		supportRepRequestAfter: function( response, data ) {
			if ( data.parsed.errors ) {
				$( '#support-rep-error' ).html( data.parsed.responses[0].errors[0].message ).show();
			} else {
				$( '#support-rep-error' ).empty().hide();
			}
		}

	};

	$( PluginEdit.ready );
} )( window.jQuery, window.wp, window.pluginDirectory );
