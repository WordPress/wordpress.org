<?php
/**
 * Template file for the Upload page.
 *
 * @package wporg-themes
 */

send_frame_options_header();

// Search engines don't need to index the upload form. Should help prevent users uploading themes they didn't create.
add_filter( 'wporg_noindex_request', '__return_true' );

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
				<h1 class="entry-title"><?php _e( 'Submit Your Theme or Theme Update to the Directory', 'wporg-themes' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php esc_html_e( 'Your theme will be submitted for review to be distributed on the official WordPress.org Theme Directory.', 'wporg-themes' ); ?></p>
				<h2><?php esc_html_e( 'Read the requirements before updating a theme', 'wporg-themes' ); ?></h2>
				<p><?php printf(
					/* translators: 1: Link to WordPress.org; 2: Link to the Theme Handbook Required Review items. */
					__( 'In order to have your theme hosted on <a href="%1$s">WordPress.org</a>, your code is required to comply with all the <a href="%2$s">requirements on the Themes Team handbook page</a>.', 'wporg-themes' ),
					esc_url( 'https://wordpress.org/' ),
					esc_url( 'https://make.wordpress.org/themes/handbook/review/required/' )
				); ?></p>
				<div style="height: 20px;" aria-hidden="true" class="wp-block-spacer"></div>
				<?php the_content(); ?>
				<div style="height: 20px;" aria-hidden="true" class="wp-block-spacer"></div>
				<h2><?php esc_html_e( 'How to upload a theme update', 'wporg-themes' ); ?></h2>
				<p><?php printf(
					/* translators: 1: style.css; 2: themename.zip */
					__( 'If you are uploading a theme update, simply increase the version inside of %1$s and upload the %2$s file again, just like you do with a new theme.', 'wporg-themes' ),
					'<code>style.css</code>',
					'<code>theme-name.zip</code>'
				); ?></p>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
