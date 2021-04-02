<?php
/**
 * The Header template for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

namespace WordPressdotorg\Pattern_Directory\Theme;

use const WordPressdotorg\Pattern_Directory\Pattern_Post_Type\POST_TYPE;

$GLOBALS['pagetitle'] = wp_get_document_title();
global $wporg_global_header_options;
if ( ! isset( $wporg_global_header_options['in_wrapper'] ) ) {
	$wporg_global_header_options['in_wrapper'] = '';
}
$wporg_global_header_options['in_wrapper'] .= '<a class="skip-link screen-reader-text" href="#content">' . esc_html__( 'Skip to content', 'wporg-patterns' ) . '</a>';

get_template_part( 'header', 'wporg' );
?>
<div id="page" class="site">
	<div id="content" class="site-content">
		<header id="masthead" class="site-header <?php echo is_home() ? 'home' : ''; ?>" role="banner">
			<div class="site-branding">
				<?php if ( is_home() ) : ?>
					<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Pattern Directory', 'Site title', 'wporg-patterns' ); ?></a></h1>

					<p class="site-description"><?php esc_html_e( 'Add a beautifully designed, ready to go layout to any WordPress site with a simple copy/paste.', 'wporg-patterns' ); ?></p>

					<div class="site-callout">
						<?php /* Logged in actions are different */ ?>
						<h2><?php esc_html_e( 'Create and share patterns', 'wporg-patterns' ); ?></h2>
						<p><?php esc_html_e( 'Build your own patterns and share them with the WordPress world.', 'wporg-patterns' ); ?></p>
						<p><a href="<?php echo esc_url( home_url( '/create' ) ); ?>"><?php esc_html_e( 'Learn more about patterns.', 'wporg-patterns' ); ?></a></p>
					</div>
				<?php else : ?>
					<div>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
							<?php esc_html_e( 'All Patterns', 'wporg-patterns' ); ?>
						</a>
						<span class="sep">/</span>
						<span class="is-current-page">
							<?php
							if ( is_singular( POST_TYPE ) ) {
								esc_html_e( 'Pattern Details', 'wporg-patterns' );
							} else if ( is_singular() ) {
								the_title();
							} else {
								the_archive_title();
							}
							?>
						</span>
					</div>

					<nav id="site-navigation" class="main-navigation" role="navigation">
						<button
							class="menu-toggle dashicons dashicons-arrow-down-alt2"
							aria-controls="primary-menu"
							aria-expanded="false"
							aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-patterns' ); ?>"
						>
						</button>

						<div id="primary-menu" class="menu">
							<?php
							wp_nav_menu( array(
								'theme_location' => 'primary',
								'menu_id'        => 'primary-menu',
							) );
							?>
						</div>
					</nav><!-- #site-navigation -->
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
