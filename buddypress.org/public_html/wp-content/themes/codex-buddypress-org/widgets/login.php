<?php
class BPOrg_Login_Widget extends WP_Widget {
	function bporg_login_widget() {
		parent::WP_Widget( false, $name = __( "Login Form", 'bporg' ) );
	}

	function widget( $args, $instance ) {
		global $bp;

	    extract( $args );

		if ( is_user_logged_in() ) : ?>

			<?php
				echo $before_widget;
				echo $before_title . __( 'Logged In As', 'bp-follow' ) . $after_title; ?>

			<?php do_action( 'bp_before_sidebar_me' ) ?>

			<div id="sidebar-me">
				<a href="<?php echo bp_loggedin_user_domain() ?>">
					<?php bp_loggedin_user_avatar( 'type=thumb&width=40&height=40' ) ?>
				</a>

				<h4><?php echo bp_core_get_userlink( bp_loggedin_user_id() ) ?></h4>
				<a class="button logout" href="<?php echo wp_logout_url( bp_get_root_domain() ) ?>"><?php _e( 'Log Out', 'buddypress' ) ?></a>

				<?php do_action( 'bp_sidebar_me' ) ?>
			</div>

			<?php do_action( 'bp_after_sidebar_me' ) ?>

			<?php if ( function_exists( 'bp_message_get_notices' ) ) : ?>
				<?php bp_message_get_notices(); /* Site wide notices to all users */ ?>
			<?php endif; ?>

		<?php else : ?>

			<?php do_action( 'bp_before_sidebar_login_form' ) ?>

			<?php
				echo $before_widget;
				echo $before_title . __( 'Log In', 'bp-follow' ) . $after_title; ?>

			<p id="login-text">
				<?php _e( 'To start connecting please log in first.', 'buddypress' ) ?>
				<?php if ( bp_get_signup_allowed() ) : ?>
					<?php printf( __( ' You can also <a href="%s" title="Create an account">create an account</a>.', 'buddypress' ), site_url( BP_REGISTER_SLUG . '/' ) ) ?>
				<?php endif; ?>
			</p>

			<form name="login-form" id="sidebar-login-form" class="standard-form" action="<?php echo site_url( 'wp-login.php', 'login_post' ) ?>" method="post">
				<label><?php _e( 'Username', 'buddypress' ) ?><br />
				<input type="text" name="log" id="sidebar-user-login" class="input" value="<?php echo attribute_escape(stripslashes($user_login)); ?>" /></label>

				<label><?php _e( 'Password', 'buddypress' ) ?><br />
				<input type="password" name="pwd" id="sidebar-user-pass" class="input" value="" /></label>

				<p class="forgetmenot"><label><input name="rememberme" type="checkbox" id="sidebar-rememberme" value="forever" /> <?php _e( 'Remember Me', 'buddypress' ) ?></label></p>

				<?php do_action( 'bp_sidebar_login_form' ) ?>
				<input type="submit" name="wp-submit" id="sidebar-wp-submit" value="<?php esc_attr_e('Log In'); ?>" tabindex="100" />
				<input type="hidden" name="testcookie" value="1" />
			</form>

			<?php do_action( 'bp_after_sidebar_login_form' ) ?>

		<?php endif; ?>

		<?php echo $after_widget; ?>

	<?php
	}
}
add_action( 'widgets_init', create_function( '', 'return register_widget("BPOrg_Login_Widget");' ) );
