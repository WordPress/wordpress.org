<?php
/**
 * The template part for displaying content
 *
 * @package WordPressdotorg\Theme;
 */

namespace WordPressdotorg\Theme;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php if ( is_sticky() && is_home() && ! is_paged() ) : ?>
			<span class="sticky-post"><?php esc_html_e( 'Featured', 'wporg' ); ?></span>
		<?php
		endif;

		if ( is_single() ) : // phpcs:ignore Generic.WhiteSpace.ScopeIndent.IncorrectExact
			the_title( '<h2 class="entry-title">', '</h2>' );
		else :
			the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
		endif;
		?>
	</header><!-- .entry-header -->

	<div class="entry-meta"><?php entry_meta(); ?></div>

	<div class="entry-content">
		<?php
			the_content( sprintf(
				/* translators: %s: Name of current post */
				__( 'Continue reading<span class="screen-reader-text"> "%s"</span> &rarr;', 'wporg' ),
				get_the_title()
			) );

			wp_link_pages( array(
				'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', 'wporg' ) . '</span>',
				'after'       => '</div>',
				'link_before' => '<span>',
				'link_after'  => '</span>',
				'pagelink'    => '<span class="screen-reader-text">' . __( 'Page', 'wporg' ) . ' </span>%',
				'separator'   => '<span class="screen-reader-text">, </span>',
			) );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<?php
		edit_post_link(
			sprintf(
				/* translators: %s: Name of current post */
				__( 'Edit<span class="screen-reader-text"> "%s"</span>', 'wporg' ),
				get_the_title()
			),
			'<span class="edit-link">',
			'</span>'
		);
		?>
	</footer><!-- .entry-footer -->
</article><!-- #post-## -->
