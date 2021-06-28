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

		<h1><?php printf( __( 'Search Results for %s', 'wporg-forums' ), esc_html( get_query_var( 's' ) ) ); ?></h1>

		<div class="search">
			<?php
			while ( have_posts() ) :
				the_post();
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<a href="<?php echo esc_url( get_the_permalink() ); ?>" class="archive-block"><?php the_title( '<h2>', '</h2>' ); ?></a>

				<?php the_excerpt(); ?>
			</article>

			<?php endwhile; ?>

		</div>

		<div class="archive-pagination">
			<?php posts_nav_link(); ?>
		</div>
	</main>

<?php
get_footer();

