<?php
/**
 * Template for component pages, for make/core.
 */
?>
<?php get_header(); ?>

<div class="sleeve_main">

	<div id="main">
		<h2><?php the_title(); ?> component</h2>

		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>

				<div id="prologue-<?php the_ID(); ?>" <?php post_class('postcontent'); ?>>
					<div id="content-<?php the_ID(); ?>" class="">
						<?php the_content( __( '(More ...)' , 'p2' ) ); ?>
					</div>

					<?php wp_link_pages( array( 'before' => '<p class="page-nav">' . __( 'Pages:', 'p2' ) ) ); ?>

					<div class="bottom-of-entry">&nbsp;</div>
				</div>
			<?php endwhile; ?>

		<?php endif; ?>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
