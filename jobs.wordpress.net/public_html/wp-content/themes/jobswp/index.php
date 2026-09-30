<?php
/**
 * The main template file.
 *
 * @package jobswp
 */

get_header(); ?>

	<main>

	<?php if ( is_front_page() ) : ?>

		<?php get_template_part( 'content', 'home' ); ?>

	<?php elseif ( is_tax( 'job_category' ) ) : ?>

		<div class="site-content has-sidebar">
			<?php get_sidebar(); ?>
			<div class="content-area">
				<?php get_template_part( 'content', 'category' ); ?>
			</div>
		</div>

	<?php elseif ( is_search() ) : ?>

		<div class="site-content has-sidebar">
			<?php get_sidebar(); ?>
			<div class="content-area">
				<?php get_template_part( 'content', 'search' ); ?>
			</div>
		</div>

	<?php elseif ( have_posts() ) : ?>

		<?php
		while ( have_posts() ) :
			the_post();

			if ( is_page() ) {
				$content_type = 'page';
			} elseif ( is_single() ) {
				$content_type = 'single';
			} else {
				$content_type = get_post_format();
			}

			get_template_part( 'content', $content_type );
		endwhile;
		?>

	<?php else : ?>

		<?php get_template_part( 'no-results', 'index' ); ?>

	<?php endif; ?>

	</main>

<?php get_footer(); ?>
