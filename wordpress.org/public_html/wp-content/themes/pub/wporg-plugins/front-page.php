<?php
/**
 * The front page template file.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

$sections = array(
	'popular ' => __( 'Popular Plugins', 'wporg-plugins' ),
	'trending' => __( 'Trending Plugins', 'wporg-plugins' ),
	'beta'     => __( 'Beta Plugins', 'wporg-plugins' ),
);

get_header();
?>

	<main id="main" class="site-main" role="main">

		<?php foreach ( $sections as $section_slug => $section_title ) : ?>

			<section class="plugin-section">
				<header class="section-header">
					<h1 class="section-title"><?php echo esc_html( $section_title ); ?></h1>
					<a class="section-link" href="<?php echo esc_url( home_url( $section_slug ) ); ?>"><?php _ex( 'See all', 'plugins', 'wporg-plugins' ); ?></a>
				</header>

				<?php
					while ( have_posts() ) :
						the_post();

						get_template_part( 'template-parts/plugin', 'index' );
					endwhile;
				?>

			</section>

		<?php endforeach; ?>

	</main><!-- #main -->

<?php
get_sidebar( 'front-page' );
get_footer();
