<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPBBP
 */

namespace WordPressdotorg\Forums;

\WordPressdotorg\skip_to( '#content' );

echo do_blocks( '<!-- wp:wporg/global-header {"style":{"border":{"bottom":{"color":"var:preset|color|white-opacity-15","style":"solid","width":"1px"}}}} /-->' );

$is_forums_home = function_exists( 'bbp_is_forum_archive' ) && bbp_is_forum_archive();
$is_user_profile = function_exists( 'bbp_is_single_user' ) && bbp_is_single_user();

echo do_blocks( $is_forums_home
	? '<!-- wp:pattern {"slug":"wporg-support/local-nav-home"} /-->'
	: '<!-- wp:pattern {"slug":"wporg-support/local-nav"} /-->'
);

?>

<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-forums' ); ?></a>

	<div id="content" class="site-content">
		<?php if ( is_front_page() ) : ?>
			<header id="masthead" class="site-header <?php echo is_front_page() ? 'home' : ''; ?>" role="banner">
				<div class="site-branding">
					<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/forums/' ) ); ?>" rel="home"><?php _ex( 'WordPress Support', 'Site title', 'wporg-forums' ); ?></a></h1>

					<p class="site-description">
						<?php
						/* Translators: subhead */
						_e( 'We&#8217;ve got a variety of resources to help you get the most out of WordPress.', 'wporg-forums' );
						?>
					</p>
					<?php get_search_form(); ?>
				</div><!-- .site-branding -->
			</header><!-- #masthead -->
		<?php elseif ( $is_forums_home ) : ?>
			<?php echo do_blocks( '<!-- wp:pattern {"slug":"wporg-support/forums-homepage-header"} /-->' ); ?>
		<?php elseif ( ! $is_user_profile ) : ?>
			<?php echo do_blocks(
				'<!-- wp:group {"style":{"spacing":{"border":{"bottom":{"color":"var:preset|color|light-grey-1","style":"solid","width":"1px"}},"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
				<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space);border-bottom:1px solid var(--wp--preset--color--light-grey-1)">

					<!-- wp:pattern {"slug":"wporg-support/search-field"} /-->

				</div>
				<!-- /wp:group -->'
			); ?>
		<?php endif; ?>
