<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package wpmobileapps
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<div class="entry-header-container">
			<h1 class="entry-title"><?php the_title(); ?></h1>
			<?php
				$post_subtitle = get_post_meta( $post->ID, 'post_subtitle', true );
				if ( $post_subtitle ) : ?>
				<h3 class="entry-subtitle"><?php echo esc_html( $post_subtitle ); ?></h3>
				<?php endif; ?>
		</div><!-- .entry-header-container -->
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php the_content(); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . __( 'Pages:', 'wpmobileapps' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->
	<?php edit_post_link( __( 'Edit', 'wpmobileapps' ), '<footer class="entry-meta"><span class="edit-link">', '</span></footer>' ); ?>
</article><!-- #post-## -->
