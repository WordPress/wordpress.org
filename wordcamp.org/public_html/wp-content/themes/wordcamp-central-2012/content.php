<?php
/**
 * Template for displaying posts and fallback for all post formats.
 */
?>
		<h2 class="entry-title">
			<a href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'twentyten' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark">
				<?php if ( get_the_title() == '' ) : echo 'Post ' . get_the_ID(); else : the_title(); endif; ?>
			</a>
		</h2>

		<div class="entry-meta">
			Posted by <?php the_author_posts_link(); ?> on <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_date(); ?></a> with <?php comments_popup_link( 'No replies yet', '1 reply', '% replies', 'comments-link', 'Comments are off for this post');?>
		</div><!-- .entry-meta -->

		<?php echo get_avatar( get_the_author_meta('ID'), 60 ); ?>


<?php if ( is_archive() || is_search() ) : // Only display excerpts for archives and search. ?>
		<div class="entry-summary">
			<?php the_excerpt(); ?>
		</div><!-- .entry-summary -->
<?php else : ?>
		<div class="entry-content">
			<?php the_content( __( 'Continue reading <span class="meta-nav">&rarr;</span>', 'twentyten' ) ); ?>
			<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) ); ?>
		</div><!-- .entry-content -->
<?php endif; ?>

		<div class="entry-utility">
			<?php if ( count( get_the_category() ) ) : ?>
				<span class="cat-links">
					<?php printf( __( '<span class="%1$s">Categories</span> %2$s', 'twentyten' ), 'entry-utility-prep entry-utility-prep-cat-links', get_the_category_list( ', ' ) ); ?>
				</span>
				<span class="meta-sep">|</span>
			<?php endif; ?>
			<?php
				$tags_list = get_the_tag_list( '', ', ' );
				if ( $tags_list ):
			?>
				<span class="tag-links">
					<?php printf( __( '<span class="%1$s">Tags</span> %2$s', 'twentyten' ), 'entry-utility-prep entry-utility-prep-tag-links', $tags_list ); ?>
				</span>
				<span class="meta-sep">|</span>
			<?php endif; ?>
			<?php comments_popup_link('No replies yet', '1 reply', '% replies', 'comments-link', 'Comments are off for this post' ); ?>
			<?php edit_post_link( __( 'Edit', 'twentyten' ), '<span class="meta-sep">|</span> <span class="edit-link">', '</span>' ); ?>
		</div><!-- .entry-utility -->
