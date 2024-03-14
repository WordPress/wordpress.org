<?php
/**
 * Template part for displaying single posts in an archive list.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WPBBP
 */

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h2 class="entry-title">
			<a href="<?php echo esc_url( get_the_permalink() ); ?>">
				<?php the_title(); ?>
			</a>
		</h2>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<div class="container">
			<?php the_excerpt(); ?>
		</div>
	</div><!-- .entry-content -->
</article><!-- #post-## -->
