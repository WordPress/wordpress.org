<?php
/**
 * Template Name: Full-width
 */

get_header(); ?>

<div id="primary" class="content-area">
	<div id="content" class="site-content" role="main">

		<header class="page-header">
			<h1 class="page-title">
				<?php the_title(); ?>

				<span class="controls">
					<?php do_action( 'breathe_view_controls' ); ?>
				</span>
			</h1>

			<span class="entry-actions">
				<?php do_action( 'breathe_post_actions' ); ?>
			</span>

			<?php do_action( 'breathe_header_entry_meta' ); ?>
		</header><!-- .page-header -->

		<?php while ( have_posts() ) : the_post(); ?>

			<?php get_template_part( 'content', 'page' ); ?>

			<aside>
				<?php
				// If comments are open or we have at least one comment, load up the comment template
				if ( comments_open() || '0' != get_comments_number() )
					comments_template();
				?>
			</aside>

			<?php breathe_content_nav( 'nav-below' ); ?>

		<?php endwhile; // end of the loop. ?>

	</div><!-- #content -->
</div><!-- #primary -->

<?php get_footer(); ?>
