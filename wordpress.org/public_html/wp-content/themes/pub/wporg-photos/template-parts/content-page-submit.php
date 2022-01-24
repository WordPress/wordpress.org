<?php
/**
 * Template part for displaying page content in page.php.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<p>
			<?php printf(
				/* translators: 1: URL to guidelines page, 2: URL to license page, 3: URL to FAQ page */
				__( 'Before you submit your CC0-licensed photo, we ask you to review our <a href="%1$s">Guidelines</a>, <a href="%2$s">License</a>, and read the <a href="%3$s">Frequently Asked Questions</a>.', 'wporg-photos' ),
				home_url( '/guidelines/' ),
				home_url( '/license/' ),
				home_url( '/faq/' ),

			); ?>
		</p>

		<?php the_content(); ?>

		<?php if ( WordPressdotorg\Photo_Directory\Uploads::user_can_upload() ) { ?>
		<p>
			<?php printf( __( 'Once submitted, your photo will be reviewed to ensure it complies with <a href="%s">all the guidelines</a>.', 'wporg-photos' ), home_url( '/guidelines/' ) ); ?>
		</p>
		<?php } ?>

		<?php
		wp_link_pages( array(
			'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'wporg-photos' ),
			'after'  => '</div>',
		) );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php
		edit_post_link(
			sprintf(
				/* translators: %s: Name of current post */
				esc_html__( 'Edit %s', 'wporg-photos' ),
				the_title( '<span class="screen-reader-text">"', '"</span>', false )
			),
			'<span class="edit-link">',
			'</span>'
		);
		?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
