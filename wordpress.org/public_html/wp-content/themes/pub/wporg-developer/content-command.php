<?php namespace DevHub;
/**
 * @package wporg-developer
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php
			$children = get_children( array(
				'post_parent'    => get_the_ID(),
				'post_type'      => 'command',
				'posts_per_page' => 1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			) );
		?>
		<h2 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?><?php if ( $children ) : ?> <span>&#60;command&#62;<?php endif; ?></a></h2>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'wporg' ) ); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . __( 'Pages:', 'wporg' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-meta">
		<?php edit_post_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>
	</footer><!-- .entry-meta -->
</article><!-- #post-## -->
