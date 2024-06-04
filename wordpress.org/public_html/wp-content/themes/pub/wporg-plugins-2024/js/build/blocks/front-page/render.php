<?php
namespace WordPressdotorg\Theme\Plugins_2024\FrontPage;

use WordPressdotorg\Plugin_Directory\Template;
use WP_Query;

global $wp_query;

$sections = array(
	'blocks'    => __( 'Block-Enabled plugins', 'wporg-plugins' ),
	'featured'  => __( 'Featured plugins', 'wporg-plugins' ),
	'beta'      => __( 'Beta plugins', 'wporg-plugins' ),
	'favorites' => __( 'My favorites', 'wporg-plugins' ),
	'popular'   => __( 'Popular plugins', 'wporg-plugins' ),
);

$widget_args = array(
	'before_title' => '<h2 class="widget-title">',
	'after_title'  => '</h2>',
);

echo do_blocks( '<!-- wp:wporg/filter-bar /--><!-- wp:wporg/category-navigation /-->' );

?>

<div id="main" class="site-main alignwide" role="main">

	<?php
	foreach ( $sections as $browse => $section_title ) :
		// Only logged in users can have favorites.
		if ( 'favorites' === $browse && ! is_user_logged_in() ) {
			continue;
		}

		$section_args = array(
			'post_type'      => 'plugin',
			'post_status'    => 'publish',
			'posts_per_page' => 4,
			'browse'         => $browse,
		);

		if ( 'popular' === $browse ) {
			$section_args['meta_key'] = '_active_installs';
			$section_args['orderby']  = 'meta_value_num';
			unset( $section_args['browse'] );
		} else if ( 'blocks' === $browse ) {
			$section_args['orderby'] = 'rand';
			$section_args['meta_query'] = [
				[
					'key'     => '_active_installs',
					'value'   => 200,
					'type'    => 'numeric',
					'compare' => '>=',
				],
				[
					'key'     => 'tested',
					'value'   => Template::get_current_major_wp_version() - 0.2,
					'compare' => '>=',
				],
			];
		}

		$section_query = new WP_Query( $section_args );

		// If the user doesn't have any favorites, omit the section.
		if ( 'favorites' === $browse && ! $section_query->have_posts() ) {
			continue;
		}

		// Overwrite the global query with the section query.
		$wp_query = $section_query;

		$safe_title = esc_html( $section_title );
		$title = do_blocks ( <<<BLOCKS
			<!-- wp:heading {"level":2,"style":{"typography":{"fontStyle":"normal","fontWeight":"600"}},"fontSize":"heading-5","fontFamily":"inter"} -->
				<h2 class="wp-block-heading has-inter-font-family has-heading-5-font-size" style="font-style:normal;font-weight:600">$safe_title</h2>
			<!-- /wp:heading -->
		BLOCKS
		);

		?>

		<section class="plugin-section">
			<header class="section-header">
				<?php echo $title; ?>
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
			echo do_blocks( <<<BLOCKS
			<!-- wp:query {"tagName":"div","className":"plugin-cards"} -->
				<div class="wp-block-query plugin-cards">
					<!-- wp:post-template {"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"48%"}} -->
						<!-- wp:wporg/plugin-card /-->
					<!-- /wp:post-template -->
				</div>
			<!-- /wp:query -->
			BLOCKS
			);

			// Reset the global $wp_query
			wp_reset_query();
			?>
		</section>

	<?php endforeach; ?>

</div><!-- #main -->

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