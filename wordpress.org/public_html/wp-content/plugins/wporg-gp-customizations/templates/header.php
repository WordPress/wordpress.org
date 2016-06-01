<?php
global $pagetitle;
$pagetitle = gp_title();
require WPORGPATH . 'header.php';
?>
<script type="text/javascript">document.body.className = document.body.className.replace('no-js','js');</script>

<div id="headline">
	<div class="wrapper">
		<h2><a href="<?php echo home_url( '/' ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
		<?php
		wp_nav_menu( array(
			'theme_location' => 'wporg_header_subsite_nav',
			'fallback_cb'    => '__return_false'
		) );
		?>
	</div>
	<div class="wrapper">
		<ul class="translate-meta-nav">
			<?php
			if ( is_user_logged_in() ) {
				$user = wp_get_current_user();
				printf(
					'<li>logged in as %s</li>',
					'<a href="' . esc_url( gp_url_profile( $user->user_login ) ) . '">@' . esc_html( $user->user_nicename ) . '</a>'
				);
				?>
				<li><a href="<?php echo esc_url( gp_url( '/settings' ) ); ?>">Settings</a></li>
				<li><a href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a></li>
				<?php
			} else {
				?>
				<li><a href="<?php echo esc_url( wp_login_url() ); ?>"><strong>Log in</strong></a></li>
				<?php
			}
			?>
			</ul>
	</div>
</div>

<div class="gp-content">

<div id="gp-js-message"></div>

		<?php if (gp_notice('error')): ?>
			<div class="error">
				<?php echo gp_notice( 'error' ); //TODO: run kses on notices ?>
			</div>
		<?php endif; ?>
		<?php if (gp_notice()): ?>
			<div class="notice">
				<?php echo gp_notice(); ?>
			</div>
		<?php endif; ?>
		<?php echo gp_breadcrumb(); ?>
		<?php do_action( 'gp_after_notices' ); ?>

