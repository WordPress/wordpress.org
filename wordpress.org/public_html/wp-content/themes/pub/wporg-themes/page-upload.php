<?php
/**
 * Template file for the Upload page.
 *
 * @package wporg-themes
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'wrap' ); ?>>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'Submit Your Theme or Theme Update to the Directory', 'wporg-themes' ); ?></h2>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php esc_html_e( 'Your theme will be submitted for review to be distributed on the official WordPress.org Theme Directory.', 'wporg-themes' ); ?></p>
				<?php the_content(); // do_shortcode( '[wporg-themes-upload]' ); ?>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
