<?php wporg_get_global_header(); ?>

<div id="headline">
	<div class="wrapper">
		<h2><a href="<?php bbp_forums_url(); ?>"><?php _e( 'Forums', 'wporg-forums' ); ?></a></h2>
		<p class="login">
			<?php if ( is_user_logged_in() ) : ?>
				<?php echo sprintf( __( 'Howdy, %s', 'wporg-forums' ), '<a href="' . bbp_get_user_profile_url( bbp_get_current_user_id() ) . '">' . bbp_get_current_user_name() . '</a>' ); ?>

				<small>(
				<?php if ( bbp_is_user_keymaster() ) : ?>
					<a href="<?php echo esc_url( admin_url() ); ?>"><?php _e( 'Admin', 'wporg-forums' ); ?></a> |
				<?php endif; ?>
					<a href="<?php bbp_logout_url(); ?>"><?php _e( 'Sign Out', 'wporg-forums' ); ?></a>
				)</small><br>
			<?php else : ?>
				<?php $current_url = esc_url_raw( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] ); ?>
				<a href="https://wordpress.org/support/bb-login.php?redirect_to=<?php echo urlencode( $current_url ); ?>"><?php _e( 'Login', 'wporg-forums' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
