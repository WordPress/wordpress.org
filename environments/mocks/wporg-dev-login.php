<?php
/**
 * Plugin Name: Dev Login Helper
 * Description: Adds a one-click login button to the login screen for local development.
 */

add_action( 'login_footer', function () {
	?>
	<style>
		#dev-login-btn {
			display: block;
			width: 100%;
			margin-top: 16px;
			padding: 8px;
			background: #2271b1;
			color: #fff;
			border: none;
			border-radius: 3px;
			font-size: 14px;
			cursor: pointer;
		}
		#dev-login-btn:hover {
			background: #135e96;
		}
	</style>
	<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var form = document.getElementById( 'loginform' );
			if ( ! form ) return;

			var btn = document.createElement( 'button' );
			btn.id = 'dev-login-btn';
			btn.type = 'button';
			btn.textContent = 'Login with admin/password';
			form.parentNode.insertBefore( btn, form.nextSibling );

			btn.addEventListener( 'click', function () {
				document.getElementById( 'user_login' ).value = 'admin';
				document.getElementById( 'user_pass' ).value = 'password';
				form.submit();
			} );
		} );
	</script>
	<?php
} );
