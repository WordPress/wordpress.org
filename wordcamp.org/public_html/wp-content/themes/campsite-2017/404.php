<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link    https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<section class="error-404 not-found">
				<header class="page-header">
					<h1 class="page-title">
						<?php esc_html_e( 'Oops! That page can&rsquo;t be found.', 'wordcamporg' ); ?>
					</h1>
				</header>

				<div class="page-content">
					<p>
						<?php esc_html_e( 'It looks like nothing was found at this location. Maybe try one of the links below or a search?', 'wordcamporg' ); ?>
					</p>

					<?php

					get_search_form();
					the_widget( 'WP_Widget_Recent_Posts' );

					if ( categorized_blog() ) : ?>
						<div class="widget widget_categories">
							<h2 class="widget-title">
								<?php esc_html_e( 'Most Used Categories', 'wordcamporg' ); ?>
							</h2>

							<ul>
								<?php wp_list_categories( array(
									'orderby'    => 'count',
									'order'      => 'DESC',
									'show_count' => 1,
									'title_li'   => '',
									'number'     => 10,
								) ); ?>
							</ul>
						</div>
					<?php endif;

					/* translators: %1$s: smiley */
					$archive_content = '<p>' . sprintf( esc_html__( 'Try looking in the monthly archives. %1$s', 'wordcamporg' ), convert_smilies( ':)' ) ) . '</p>';

					the_widget( 'WP_Widget_Archives', 'dropdown=1', "after_title=</h2>$archive_content" );
					the_widget( 'WP_Widget_Tag_Cloud' );

					?>

				</div>
			</section>

		</main>
	</div>

<?php get_footer();
