<?php wporg_get_global_header(); ?>

<div id="headline">
	<div class="wrapper">
		<h2><a href="<?php bbp_forums_url(); ?>">Forums</a></h2>
		<p class="login">
			<?php if ( is_user_logged_in() ) : ?>
				<?php echo sprintf( esc_html__( 'Howdy, %s', 'wporg' ), '<a href="' . bbp_get_user_profile_url( bbp_get_current_user_id() ) . '">' . bbp_get_current_user_name() . '</a>' ); ?>

				<small>(
				<?php if ( bbp_is_user_keymaster() ) : ?>
					<a href="<?php echo esc_url( admin_url() ); ?>"><?php esc_html_e( 'Admin', 'wporg' ); ?></a> |
				<?php endif; ?>
					<a href="<?php bbp_logout_url(); ?>"><?php esc_html_e( 'Sign Out', 'wporg' ); ?></a>
				)</small><br>
			<?php else : ?>
				
			<?php endif; ?>
		</p>
	</div>
</div>
