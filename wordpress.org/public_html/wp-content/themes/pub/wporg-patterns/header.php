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

\WordPressdotorg\skip_to( '#content' );

get_template_part( 'header', 'wporg' );
?>
<div id="page" class="site">
	<div id="content" class="site-content">
		<header id="masthead" class="site-header <?php echo is_home() ? 'home' : ''; ?>" role="banner">
			<div class="site-branding">
				<?php if ( is_home() ) : ?>
					<div>
						<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Patterns', 'Site title', 'wporg-patterns' ); ?></a></h1>

						<p class="site-description"><?php esc_html_e( 'Add a beautifully designed, ready to go layout to any WordPress site with a simple copy/paste.', 'wporg-patterns' ); ?></p>
						<?php get_search_form(); ?>

						<a class="site-link" href="<?php echo esc_url( home_url( '/favorites/' ) ); ?>"><?php esc_html_e( 'Favorites', 'wporg-patterns' ); ?></a>
						<a class="site-link" href="<?php echo esc_url( home_url( '/new-pattern/' ) ); ?>"><?php esc_html_e( 'Create a new pattern', 'wporg-patterns' ); ?></a>
						<?php if ( is_user_logged_in() ) : ?>
							<a class="site-link" href="<?php echo esc_url( home_url( '/my-patterns/' ) ); ?>"><?php esc_html_e( 'My patterns', 'wporg-patterns' ); ?></a>
						<?php endif; ?>
					</div>
				<?php else : ?>
					<div>
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
							<?php esc_html_e( 'All Patterns', 'wporg-patterns' ); ?>
						</a>
						<span class="sep">/</span>
						<span id="breadcrumb-part" class="is-current-page">
							<?php
							if ( is_singular( POST_TYPE ) ) {
								esc_html_e( 'Pattern Details', 'wporg-patterns' );
							} else if ( is_singular() ) {
								the_title();
							} else if ( is_search() ) {
								printf(
									/* translators: Search query. */
									esc_html__( 'Search: %s', 'wporg-patterns' ),
									get_search_query()
								);
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

						<?php
						wp_nav_menu( array(
							'theme_location' => 'primary',
							'menu_id'        => 'primary-menu',
						) );
						?>
						
					</nav><!-- #site-navigation -->
					<?php get_search_form(); ?>
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
