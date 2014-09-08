<?php
/**
 * Single post template.
 */

get_header(); ?>

		<div id="container">
			<a href="<?php echo home_url( '/news/' ); ?>" class="wc-back-link"><span class="arrow">&larr;</span> Back to WordCamp News</a>

			<div id="content" role="main">

				<?php if ( have_posts() ) : ?>
					<?php while ( have_posts() ) : the_post(); ?>

								<div class="entry-meta">
									<ul>	
										<li class="wc-single-avatar"><?php echo get_avatar( get_the_author_meta('ID'), 140 ); ?></li>
										<li class="wc-single-author"><strong>Posted by</strong> <?php the_author_posts_link(); ?></li>
										<li class="wc-single-date"><strong>Posted on</strong> <?php the_date(); ?></li>						
										<li class="wc-single-cats"><strong>Categories</strong> <?php echo get_the_category_list(', '); ?></li>											
										<?php if ( has_tag() ) : ?>
											<li><strong>Tags</strong> <?php the_tags(' '); ?></li>	
										<?php endif; ?>
										<li><?php comments_popup_link('No replies yet', '1 reply', '% replies', 'comments-link', 'Comments are off for this post' ); ?></li>
										<li class="wc-single-search"><strong>Search</strong> <?php get_search_form(); ?></li>	

									</ul>					
								</div><!-- .entry-meta -->

								<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
									<h1 class="entry-title"><?php the_title(); ?></h1>

									<div class="entry-content">
										<?php the_content(); ?>
										<?php wp_link_pages( array( 'before' => '<div class="page-link">' . __( 'Pages:', 'twentyten' ), 'after' => '</div>' ) ); ?>
									</div><!-- .entry-content -->

								</div><!-- #post-## -->

								<?php comments_template( '', true ); ?>

					<?php endwhile; // have_posts ?>
				<?php endif; // have_posts ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>
