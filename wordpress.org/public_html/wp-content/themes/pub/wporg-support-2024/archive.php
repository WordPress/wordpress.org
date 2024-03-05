<?php
/**
 * The catchall archive template.
 *
 * If no specific archive layout is defined, we'll go with
 * a generic simplistic one, like this, just to actually
 * be able to show some content.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<h1><?php single_cat_title(); ?></h1>

		<div class="three-up helphub-front-page">
			<?php
			while ( have_posts() ) :
				the_post();
			?>

				<a href="<?php echo esc_url( get_the_permalink() ); ?>" class="archive-block">
					<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
						<?php the_title( '<h2>', '</h2>' ); ?>

						<?php the_excerpt(); ?>
					</article>
				</a>


			<?php endwhile; ?>

		</div>

		<div class="archive-pagination">
			<?php posts_nav_link(); ?>
		</div>
	</main>

<?php
get_footer();

