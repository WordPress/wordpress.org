<?php wporg_get_global_header(); ?>

<div id="headline">
	<div class="wrapper">
		<h2><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php _e( 'Forums', 'wporg-forums' ); ?></a></h2>
		<p class="login">
			<?php if ( is_user_logged_in() ) : ?>
				<?php echo sprintf( __( 'Howdy, %s', 'wporg-forums' ), '<a href="' . esc_url( bbp_get_user_profile_url( bbp_get_current_user_id() ) ) . '">' . bbp_get_current_user_name() . '</a>' ); ?>

				<small>(
				<?php if ( bbp_is_user_keymaster() ) : ?>
					<a href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Admin', 'wporg-forums' ); ?></a> |
				<?php endif; ?>
					<a href="<?php echo esc_url( wp_logout_url() ); ?>"><?php _e( 'Log Out', 'wporg-forums' ); ?></a>
				)</small><br>
			<?php else : ?>
				<?php $current_url = esc_url_raw( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ); ?>
				<a href="<?php echo esc_url( wp_login_url( $current_url ) ); ?>"><?php _e( 'Log In', 'wporg-forums' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
