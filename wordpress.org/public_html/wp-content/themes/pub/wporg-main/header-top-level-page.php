<?php
/**
 * The Header template for top-level pages.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\MainTheme;

get_template_part( 'header', 'wporg' );

global $post;
$graph_tags = custom_open_graph_tags();

// Use the OpenGraph description by default, but allow overriding it.
switch ( $post->page_template ) {
	case 'page-hosting.php':
		$description = 'Get web hosting from some of the best providers';
		break;

	default:
		$description = $graph_tags['og:description'] ?? false;
}

?>

<div id="page" class="site">
	<div id="content" class="site-content row gutters">
		<header id="masthead" class="site-header home col-12" role="banner">
			<div class="site-branding">
				<h1 class="site-title">
					<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
						<?php echo get_the_title(); ?>
					</a>
				</h1>

				<?php if ( $description ) : ?>
					<p class="site-description">
						<?php echo esc_html( $description ); ?>
					</p>
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
