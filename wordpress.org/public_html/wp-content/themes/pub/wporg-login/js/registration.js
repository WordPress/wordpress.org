(function( $ ) {
		$( function() {
			var $loginForm = $( '#login form' );

			$loginForm.on( 'focus', 'input.error', function() {
				$( this ).removeClass( 'error' );
			} );

			$loginForm.on( 'blur', '#user_login, #user_email', function() {
				var $this = $( this );

				if ( ! $this.val() ) {
					$this.parents( 'p' ).nextUntil( 'p', 'div.message.error' ).remove();
					return;
				}

				var rest_url = wporg_registration.rest_url +
					( this.id == 'user_login' ? '/username-available/' : '/email-in-use/' ) +
					encodeURIComponent( $this.val() );

				$.get( rest_url, function( datas ) {
					$this.closest( 'p' ).nextUntil( 'p', 'div.message' ).remove();
					$this.removeClass( 'good' );

					if ( ! datas.available ) {
						$this.addClass( 'error' );
						$this.closest( 'p' ).after(
							'<div class="message error' + ( datas.avatar ? ' with-avatar' : '' ) +  '"><p>' +
							( datas.avatar ? datas.avatar : '' ) + '<span>' +
							datas.error +
							'</span></p></div>'
						);
						$this.closest( 'p' ).next('div.message.error').find( '.resend' ).data( 'account', $this.val() );
					} else {
						$this.addClass( 'good' );
					}
				} );
			} );

			$loginForm.on( 'click', '.resend', function( e ) {
				var $this = $(this),
					account = $this.data('account');

				e.preventDefault();

				$.post(
					wporg_registration.rest_url + '/resend-confirmation-email',
					{
						account: account,
					},
					function( datas ) {
						const content = `<div class="message info"><p><span>${ datas }</span></p></div>`;
						const $message = $this.closest( 'div.message' );
					
						// On the pending-create page, we offer to resend the email.
						// In that case, the resend button exists within the message div, so we replace it.
						if ( $message.length) {
							$message.replaceWith( content );
						} else {
							$this.before( content );
						}
					}
				);
			});

			$loginForm.on( 'click', '.change-email', function( e ) {
				e.preventDefault();

				$(this).remove();
				$loginForm.find( '.login-email' ).removeClass( 'hidden' ).find( 'input' ).addClass( 'error' );
				$loginForm.find( '.login-submit' ).removeClass( 'hidden' );
			});

			// Apply the input validation after initial blur, to avoid showing as invalid during initial edits.
			$loginForm.on( 'blur', 'input[data-pattern-after-blur]', function() {
				var $this = $( this );
				if ( $this.val() && $this.data( 'pattern-after-blur' ) ) {
					$this.prop( 'pattern', $this.data( 'pattern-after-blur' ) );
					$this.data( 'pattern-after-blur', false );
				}
			} );

			// If the form has data in it upon load, immediately trigger the validation.
			if ( $loginForm.find('#user_login').val() ) {
				$loginForm.find('#user_login').blur();
			}
		} );
} )( jQuery );
