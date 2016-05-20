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

$widget_args = array(
	'before_title' => '<h4 class="widget-title">',
	'after_title'  => '</h4>',
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

	<aside id="secondary" class="widget-area" role="complementary">
		<?php
			the_widget( 'WP_Widget_Text', array(
				'title' => __( 'Plugin Authors', 'wporg-plugins' ),
				'text'  => __( 'Now what are the possibilities of warp drive? Cmdr Riker\'s nervous system has been invaded by an unknown microorganism. The organisms fuse to the nerve, intertwining at the molecular level. That\'s why the transporter\'s biofilters couldn\'t extract it.', 'wporg-plugins' ),
			), $widget_args );

			the_widget( 'WP_Widget_Text', array(
				'title' => __( 'Plugin Reviewers', 'wporg-plugins' ),
				'text'  => __( 'Shields up. I recommend we transfer power to phasers and arm the photon torpedoes. Something strange on the detector circuit. The weapons must have disrupted our communicators.', 'wporg-plugins' ),
			), $widget_args );

			the_widget( 'WP_Widget_Text', array(
				'title' => __( 'Plugin Handbook', 'wporg-plugins' ),
				'text'  => __( 'Communication is not possible. The shuttle has no power. Using the gravitational pull of a star to slingshot back in time? We are going to Starbase Montgomery for Engineering consultations prompted by minor read-out anomalies.', 'wporg-plugins' ),
			), $widget_args );
		?>
	</aside><!-- #secondary -->
<?php
get_footer();
