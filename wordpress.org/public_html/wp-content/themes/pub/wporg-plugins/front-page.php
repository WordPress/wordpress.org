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
	'featured' => __( 'Featured Plugins', 'wporg-plugins' ),
	'popular'  => __( 'Popular Plugins', 'wporg-plugins' ),
	'beta'     => __( 'Beta Plugins', 'wporg-plugins' ),
);

$widget_args = array(
	'before_title' => '<h4 class="widget-title">',
	'after_title'  => '</h4>',
);

get_header();
?>

	<main id="main" class="site-main" role="main">

		<?php foreach ( $sections as $browse => $section_title ) :
			$section_args = array(
				'post_type'      => 'plugin',
				'posts_per_page' => 4,
				'browse'         => $browse,
			);

			if ( 'popular' === $browse ) {
				$section_args['meta_key'] = '_active_installs';
				$section_args['orderby']  = 'meta_value_num';
				unset( $section_args['browse'] );
			}

			$section_query = new \WP_Query( $section_args );
			?>

			<section class="plugin-section">
				<header class="section-header">
					<h1 class="section-title"><?php echo esc_html( $section_title ); ?></h1>
					<a class="section-link" href="<?php echo esc_url( home_url( "browse/$browse/" ) ); ?>"><?php _ex( 'See all', 'plugins', 'wporg-plugins' ); ?></a>
				</header>

				<?php
				while ( $section_query->have_posts() ) :
					$section_query->the_post();

					get_template_part( 'template-parts/plugin', 'index' );
				endwhile;
				?>
			</section>

		<?php endforeach; ?>

	</main><!-- #main -->

	<aside id="secondary" class="widget-area" role="complementary">
		<?php
		the_widget( 'WP_Widget_Text', array(
			'title' => 'Plugin Authors',
			'text'  => sprintf( __( 'The WordPress Plugin Directory is the largest directory of free and open source WordPress plugins. Interested in hosting your plugin on WordPress.org? <a href="%s">Find out more about how.</a>', 'wporg-plugins' ), esc_url( home_url( 'developers' ) ) ),
		), $widget_args );

		the_widget( 'WP_Widget_Text', array(
			'title' => 'Create Your Plugin',
			'text'  => sprintf( __( 'Interested in building your own plugin? The <a href="%s">Plugin Developer Handbook</a> walks through the steps required with creating a WordPress plugin from scratch and those required with publishing your plugin in the directory.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/' ) ),
		), $widget_args );

		the_widget( 'WP_Widget_Text', array(
			'title' => 'Plugin Reviewers',
			'text'  => 'Shields up. I recommend we transfer power to phasers and arm the photon torpedoes. Something strange on the detector circuit. The weapons must have disrupted our communicators.',
		), $widget_args );
		?>
	</aside><!-- #secondary -->
	<?php
get_footer();
