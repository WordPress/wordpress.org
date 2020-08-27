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
    'blocks'    => __( 'Block-Enabled Plugins', 'wporg-plugins' ),
	'featured'  => __( 'Featured Plugins', 'wporg-plugins' ),
	'beta'      => __( 'Beta Plugins', 'wporg-plugins' ),
	'favorites' => __( 'My Favorites', 'wporg-plugins' ),
	'popular'   => __( 'Popular Plugins', 'wporg-plugins' ),
);

$widget_args = array(
	'before_title' => '<h2 class="widget-title">',
	'after_title'  => '</h2>',
);

get_header();
?>

	<main id="main" class="site-main" role="main">

		<?php
		foreach ( $sections as $browse => $section_title ) :
			// Only logged in users can have favorites.
			if ( 'favorites' === $browse && ! is_user_logged_in() ) {
				continue;
			}

			$section_args = array(
				'post_type'      => 'plugin',
				'posts_per_page' => 4,
				'browse'         => $browse,
				'post_status'    => 'publish',
			);

			if ( 'popular' === $browse ) {
				$section_args['meta_key'] = '_active_installs';
				$section_args['orderby']  = 'meta_value_num';
				unset( $section_args['browse'] );
			} else if ( 'blocks' === $browse ) {
				$section_args['orderby'] = 'rand';
			}

			$section_query = new \WP_Query( $section_args );

			// If the user doesn't have any favorites, omit the section.
			if ( 'favorites' === $browse && ! $section_query->have_posts() ) {
				continue;
			}
			?>

			<section class="plugin-section">
				<header class="section-header">
					<h2 class="section-title"><?php echo esc_html( $section_title ); ?></h2>
					<a class="section-link" href="<?php echo esc_url( home_url( "browse/$browse/" ) ); ?>">
						<?php
						printf(
							/* translators: %s: Section title as an accessibility text for screen readers. */
							esc_html_x( 'See all %s', 'plugins', 'wporg-plugins' ),
							'<span class="screen-reader-text">' . esc_html( $section_title ) . '</span>'
						);
						?>
					</a>
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
			'title' => __( 'Add Your Plugin', 'wporg-plugins' ),
			'text'  => sprintf(
				/* translators: URL to Developers page. */
				__( 'The WordPress Plugin Directory is the largest directory of free and open source WordPress plugins. Find out how to <a href="%s">host your plugin</a> on WordPress.org.', 'wporg-plugins' ),
				esc_url( home_url( 'developers' ) )
			),
		), $widget_args );

		the_widget( 'WP_Widget_Text', array(
			'title' => __( 'Create a Plugin', 'wporg-plugins' ),
			'text'  => sprintf(
				/* translators: URL to Developer Handbook. */
				__( 'Building a plugin has never been easier. Read through the <a href="%s">Plugin Developer Handbook</a> to learn all about WordPress plugin development.', 'wporg-plugins' ),
				esc_url( 'https://developer.wordpress.org/plugins/' )
			),
		), $widget_args );

		the_widget( 'WP_Widget_Text', array(
			'title' => __( 'Stay Up-to-Date', 'wporg-plugins' ),
			'text'  => sprintf(
				/* translators: URL to make/plugins site. */
				__( 'Plugin development is constantly changing with each new WordPress release. Keep up with the latest changes by following the <a href="%s">Plugin Review Team&#8217;s blog</a>.', 'wporg-plugins' ),
				esc_url( 'https://make.wordpress.org/plugins/' )
			),
		), $widget_args );
		?>
	</aside><!-- #secondary -->

<?php
get_footer();
