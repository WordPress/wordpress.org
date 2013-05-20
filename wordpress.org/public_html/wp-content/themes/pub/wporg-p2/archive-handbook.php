<?php
/**
 * Static page template.
 *
 * @package P2
 */
?>
<?php get_header(); ?>

<div class="sleeve_main">

	<div id="main">
		<h2>Handbook</h2>
		<?php /* <h3><?php the_title(); ?></h3> */ ?>
		<?php if ( have_posts() ) : ?>

			<ul id="postlist">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'entry', 'handbook' ); ?>
			<?php endwhile; ?>
			</ul>

		<?php endif; ?>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
