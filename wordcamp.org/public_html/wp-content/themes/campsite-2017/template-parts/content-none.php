<?php
/**
 * Template part for displaying a message that posts cannot be found
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

?>

<section class="no-results not-found">
	<header class="page-header">
		<h1 class="page-title"><?php esc_html_e( 'Nothing Found', 'wordcamporg' ); ?></h1>
	</header>

	<div class="page-content">
		<?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>
			<p>
				<?php printf(
					wp_kses_data( __( 'Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'wordcamporg' ) ),
					esc_url( admin_url( 'post-new.php' ) )
				); ?>
			</p>

		<?php else : ?>
			<p>
				<?php if ( is_search() ) : ?>
					<?php esc_html_e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'wordcamporg' ); ?>
				<?php else : ?>
					<?php esc_html_e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'wordcamporg' ); ?>
				<?php endif; ?>
			</p>

			<?php get_search_form(); ?>

		<?php endif; ?>
	</div>
</section>
