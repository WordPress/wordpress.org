jQuery(document).ready(function($){
	$('#login form').on( 'focus', 'input.error', function() {
		$(this).removeClass( 'error' );
	} );

	$('#login form').on( 'blur', '#user_login, #user_email', function() {
		var $this = $(this);
		if ( ! $this.val() ) {
			$this.parents('p').nextUntil( 'p', 'div.message.error').remove();
			return;
		}

		var rest_url = wporg_registration.rest_url +
			( this.id == 'user_login' ? '/username-available/' : '/email-in-use/' ) +
			$this.val();

		$.get( rest_url, function(datas) {
			$this.parents('p').nextUntil( 'p', 'div.message.error').remove();
			$this.removeClass("good");
			if ( ! datas.available ) {
				$this.addClass("error");
				$this.parents('p').after(
					"<div class='message error'><p>" +
					( datas.avatar ? datas.avatar : '' ) +
					datas.error +
					"</p></div>"
				);
			} else {
				$this.addClass("good");
			}
		} );
	} );

});
