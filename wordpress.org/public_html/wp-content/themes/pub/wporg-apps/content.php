<?php
/**
 * @package wpmobileapps
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class('col-1-2'); ?>>
	<div class="entry-container">

		<?php
			// Add a "Latest Post" message to the first post of the blog home. Make sure this also works when Infinite Scroll is enabled.
			if( is_home() && ! ( class_exists( 'The_Neverending_Home_Page' ) && The_Neverending_Home_Page::got_infinity() ) ) {
				global $wp_query;

				if( $wp_query->current_post == 0 ) {
					echo '<div class="latest-post">' . __( 'Latest Post' ) . '</div>';
				}
			}
		?>
		<header class="entry-header">
			<h1 class="entry-title"><a href="<?php the_permalink(); ?>" rel="bookmark"><?php the_title(); ?></a></h1>

			<?php if ( 'post' == get_post_type() ) : ?>
			<div class="entry-meta">
				<?php wpmobileapps_posted_on(); ?>
			</div><!-- .entry-meta -->
			<?php endif; ?>
		</header><!-- .entry-header -->

		<?php if ( is_home() || is_archive() || is_search() ) : // Display excerpts on the blog home, archives and search pages ?>
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
		<?php else : ?>
		<div class="entry-content">
			<?php the_content(); ?>
			<?php
				wp_link_pages( array(
					'before' => '<div class="page-links">' . __( 'Pages:', 'wpmobileapps' ),
					'after'  => '</div>',
				) );
			?>
		</div><!-- .entry-summary -->
		<?php endif; ?>

		<footer class="entry-meta">
			<?php if ( 'post' == get_post_type() ) : // Hide category and tag text for pages on Search ?>
				<?php
					/* translators: used between list items, there is a space after the comma */
					$categories_list = get_the_category_list( __( ', ', 'wpmobileapps' ) );
					if ( $categories_list && wpmobileapps_categorized_blog() ) :
				?>
				<span class="cat-links">
					<?php printf( __( 'Posted in %1$s', 'wpmobileapps' ), $categories_list ); ?>
				</span>
				<?php endif; // End if categories ?>

			<?php endif; // End if 'post' == get_post_type() ?>

			<?php edit_post_link( __( 'Edit', 'wpmobileapps' ), '<span class="edit-link">', '</span>' ); ?>
		</footer><!-- .entry-meta -->

	</div><!-- .entry-container -->
</article><!-- #post-## -->
