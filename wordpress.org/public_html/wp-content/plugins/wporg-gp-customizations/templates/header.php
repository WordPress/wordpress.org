<?php
global $pagetitle;
$pagetitle = gp_title();
require WPORGPATH . 'header.php';
?>
<script type="text/javascript">document.body.className = document.body.className.replace('no-js','js');</script>

<header id="masthead" class="site-header <?php echo wporg_gp_is_index() ? 'home' : ''; ?>" role="banner">
	<div class="site-branding">
		<?php if ( wporg_gp_is_index() ) : ?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></h1>
			<p class="site-description">
				Contribute to WordPress core, themes, and plugins by translating them into your language.<br>
				Select your locale below to get started.
			</p>
		<?php else : ?>
			<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a></p>

			<nav id="site-navigation" class="navigation-main clear" role="navigation">
				<button type="button" class="menu-toggle dashicons dashicons-arrow-down-alt2"></button>
				<?php
				wp_nav_menu( [
					'theme_location' => 'wporg_header_subsite_nav',
					'fallback_cb' => false,
				] );
				?>
			</nav>
		<?php endif; ?>
	</div>
</header>

<div class="gp-content">

	<div id="gp-js-message"></div>

	<?php
	if ( gp_notice( 'error' ) ) :
		?>
		<div class="error">
			<?php echo gp_notice( 'error' ); //TODO: run kses on notices ?>
		</div>
		<?php
	endif;

	if ( gp_notice() ) :
		?>
		<div class="notice">
			<?php echo gp_notice(); ?>
		</div>
		<?php
	endif;

	echo gp_breadcrumb();

	do_action( 'gp_after_notices' );

