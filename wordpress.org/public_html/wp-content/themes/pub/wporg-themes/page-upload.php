<?php
/**
 * Template file for the Upload page.
 *
 * @package wporg-themes
 */

send_frame_options_header();

// Search engines don't need to index the upload form. Should help prevent users uploading themes they didn't create.
if ( ! function_exists( 'wporg_meta_robots' ) ) {
   	function wporg_meta_robots() {
		return 'noindex';
	}
}

// Use the translated post title.
add_filter( 'single_post_title', function( $title ) {
	if ( 'Submit Your Theme or Theme Update to the Directory' === $title ) {
		$title = __( 'Submit Your Theme or Theme Update to the Directory', 'wporg-themes' );
	}

	return $title;
}, 1 );

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
