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
					$this.val();

				$.get( rest_url, function( datas ) {
					$this.parents( 'p' ).nextUntil( 'p', 'div.message.error' ).remove();
					$this.removeClass( 'good' );

					if ( ! datas.available ) {
						$this.addClass( 'error' );
						$this.parents( 'p' ).after(
							'<div class="message error' + ( datas.avatar ? ' with-avatar' : '' ) +  '"><p>' +
							( datas.avatar ? datas.avatar : '' ) + '<span>' +
							datas.error +
							'</span></p></div>'
						);
					} else {
						$this.addClass( 'good' );
					}
				} );
			} );
		} );
} )( jQuery );
