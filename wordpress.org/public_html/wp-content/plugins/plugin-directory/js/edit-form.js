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

			$( '.plugin-upload-zip' ).click( PluginEdit.uploadZip );
			$( '.plugin-upload-zip' ).parents('form').submit( PluginEdit.uploadZipDisable );
		},

		setPluginStatus: function() {
			var $this = $(this),
				status = $this.val();

			if ( 'new' == status ) {
				jQuery('#assigned_reviewer').val(0);

			} else if ( 'pending' == status && $this.hasClass('pending-and-assign') ) {
				jQuery('#assigned_reviewer').val( userSettings.uid );

			} else if ( 'approved' === status ) {
				return confirm( pluginDirectory.approvePluginAYS );

			} else if ( 'rejected' === status ) {
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

			wp.ajax.post( 'get-notes', data ).always( function( response ) {
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
		},

		uploadZip: function( e ) {
			e.preventDefault();

			var $this = $(this),
				$container = $this.parents('label'),
				post_ID = $( '#post_ID' ).val(),
				$file = $container.find('input[type="file"]' ),
				file_input = $file.get(0),
				restEndpoint = 'plugins/v1/upload/' + post_ID;

			if ( ! file_input.files.length ) {
				alert( "Select a file first." );
				return;
			}

			$this.prop( 'disabled', true );
			$this.text( 'Uploading...' );
			$container.find( '.notice' ).remove();

			var data = new FormData()
			data.append( $file.prop( 'name' ), file_input.files[0] );
			data.append( 'admin', true );

			wp.apiRequest( {
				path: restEndpoint,
				type: 'POST',
				data: data,
				processData: false,
				contentType: false,
			} )
			.done( function( response, statusText ) {
				var successHtml = response?.responseJSON?.message || statusText;

				$container.append( '<div class="notice notice-success"><p>' + successHtml + '</p></div>' );

				$('ul.plugin-zip-files').append(
					'<li>' + new Date().toLocaleString() + ' ' + file_input.files[0].name + '</li>'
				);

				$file.val( '' );
			} )
			.fail( function( response, statusText ) {
				var errorHtml = response?.responseJSON?.message || statusText;

				$container.append( '<div class="notice notice-error"><p>' + errorHtml + '</p></div>' );
			} )
			.always( function() {
				$this.text( 'Upload' ).prop( 'disabled', false );
			} );
		},
	
		// Disable any file input fields, to prevent the browser sending it.
		uploadZipDisable: function() {
			$(this).find('input[type="file"]').prop( 'disabled', true );
		}

	};

	$( PluginEdit.ready );
} )( window.jQuery, window.wp, window.pluginDirectory );
