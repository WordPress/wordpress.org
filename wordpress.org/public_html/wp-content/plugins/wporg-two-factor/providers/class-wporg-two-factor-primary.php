<?php

require_once TWO_FACTOR_DIR . 'providers/class.two-factor-totp.php';

class WPORG_Two_Factor_Primary extends Two_Factor_Totp {
	/**
	 * Ensures only one instance of this class exists in memory at any one time.
	 *
	 * @since 0.1-dev
	 */
	static function get_instance() {
		static $instance;
		$class = __CLASS__;
		if ( ! is_a( $instance, $class ) ) {
			$instance = new $class;
		}
		return $instance;
	}

	/**
	 * Returns the name of the provider.
	 */
	public function get_label() {
		return 'WordPress.org Primary 2FA Provider'; // Not translated as it's not displayed, this is purely for debugging & the parent plugin.
	}

	public function validate_authentication( $user, $code = '' ) {
		$key = get_user_meta( $user->ID, self::SECRET_META_KEY, true );

		if ( ! $code ) {
			$code = $_REQUEST['authcode'];
		}

		return $this->is_valid_authcode( $key, $code );
	}

	/**
	 * Prints the form that prompts the user to authenticate.
	 *
	 * @param WP_User $user WP_User object of the logged-in user.
	 */
	public function authentication_page( $user ) {
		require_once ABSPATH . '/wp-admin/includes/template.php';
		?>
		<p>
			<label for="authcode"><?php esc_html_e( 'Authentication Code:', 'wporg' ); ?></label>
			<input type="tel" name="authcode" id="authcode" class="input" value="" size="20" pattern="[0-9]*" />
		</p>
		<script type="text/javascript">
			setTimeout( function(){
				var d;
				try{
					d = document.getElementById( 'authcode' );
					d.value = '';
					d.focus();
				} catch(e){}
			}, 200);
		</script>
		<?php
		submit_button( __( 'Authenticate', 'wporg' ), 'primary', 'submit', false );
	}
}