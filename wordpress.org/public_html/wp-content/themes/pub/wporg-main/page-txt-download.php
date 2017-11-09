<?php
/**
 * Template for displaying a downloads page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

the_post();
get_header( 'page' ); ?>

	<main id="main" class="site-main col-12" role="main">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<aside class="col-3 alignright">
					<?php
					the_widget( __NAMESPACE__ . '\WPORG_Widget_Download' );

					printf( '<div class="widget %s">', 'widget_links' );
					the_widget( 'WP_Widget_Links', array(), array(
						'before_title' => '<h4>',
						'after_title'  => '</h4>' . '<p>' . __( 'For help with installing or using WordPress, consult our documentation in your language.', 'wporg' ) . '</p>',
					) );
					echo '</div>';
					?>
				</aside>

				<?php
				the_content();

				wp_link_pages( array(
					'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'wporg' ),
					'after'  => '</div>',
				) );
				?>
			</div><!-- .entry-content -->

			<footer class="entry-footer">
				<?php
				edit_post_link(
					sprintf(
						/* translators: %s: Name of current post */
						esc_html__( 'Edit %s', 'wporg' ),
						the_title( '<span class="screen-reader-text">"', '"</span>', false )
					),
					'<span class="edit-link">',
					'</span>'
				);
				?>
			</footer><!-- .entry-footer -->
		</article><!-- #post-## -->
	</main><!-- #main -->

	<?php
get_footer();
