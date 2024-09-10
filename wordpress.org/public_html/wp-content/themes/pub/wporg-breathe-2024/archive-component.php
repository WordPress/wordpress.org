<?php
/**
 * Template for component pages, for make/core.
 */

$trac_name = 'Core';
if ( isset( $wporg_trac_notifications ) ) {
	$trac_name = $wporg_trac_notifications->trac_name();
}
?>
<?php get_header(); ?>

	<div id="primary" class="content-area">
		<div role="main">
		<h1><?php printf( 'WordPress %s Components', $trac_name ); ?></h1>

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

		</div>

	</div><!-- #primary -->
	<div id="primary-modal"></div>

	<!-- A fake o2 content area, so that it doesn't overwrite the table -->
	<div style="display: none;"><div id="content"></div></div>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
