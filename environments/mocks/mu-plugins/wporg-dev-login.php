<?php
/**
 * Plugin Name: Dev Login Helper
 * Description: Adds a one-click login button to the login screen for local development.
 */

add_action( 'login_footer', function () {
	?>
	<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			var submit = document.getElementById( 'wp-submit' );
			if ( ! submit ) return;

			var btn = document.createElement( 'button' );
			btn.type = 'button';
			btn.className = submit.className;
			btn.style.marginTop = '8px';
			btn.style.width = '100%';
			btn.textContent = 'Login with admin/password';
			submit.parentNode.appendChild( btn );

			btn.addEventListener( 'click', function () {
				document.getElementById( 'user_login' ).value = 'admin';
				document.getElementById( 'user_pass' ).value = 'password';
				submit.form.submit();
			} );
		} );
	</script>
	<?php
} );
