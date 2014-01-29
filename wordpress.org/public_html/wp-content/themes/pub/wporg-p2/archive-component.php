<?php
/**
 * Template for component pages, for make/core.
 */
?>
<?php get_header(); ?>

<div class="sleeve_main">

	<div id="main" class="postcontent compact-components">
		<h2>WordPress core components</h2>

	<?php
	if ( false && $cached = get_transient( 'trac_components_page' ) ) {
		echo $cached;
	} else {
		ob_start();
		$post = get_page_by_path( 'components' );
		setup_postdata( $post );

		the_content();
		?>
		<p id="toggle-compact-components"><label><input type="checkbox" /> Expanded view</label></p>
		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<div class="component-info">
					<h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
					<?php the_content(); ?>
				</div>
			<?php endwhile; ?>

		<?php endif; ?>
	<?php
		$cache = ob_get_clean();
		set_transient( 'trac_components_page', $cache, 300 );
		echo $cache;
	}
	?>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
