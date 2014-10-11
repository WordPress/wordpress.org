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
	if ( $cached = get_transient( 'trac_components_page' ) ) {
		echo $cached;
	} else {
		ob_start();
		$post = get_page_by_path( 'components' );
		setup_postdata( $post );

		the_content();
		if ( have_posts() ) :
			echo '<table>';
			while ( have_posts() ) : the_post();
				do_action( 'component_table_row', get_post() );
			endwhile;
			echo '</table>';
		endif;

		$cache = ob_get_clean();
		set_transient( 'trac_components_page', $cache, 300 );
		echo $cache;
	}
?>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
