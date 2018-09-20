<?php
/**
 * Breadcrumbs template part, use with get_template_part()
 *
 * @global WordPressTV_Theme $wptv
 */

global $wptv;
?>
<div class="breadcrumb">
	<a href="<?php echo esc_url( home_url() ); ?>"><?php _e( 'Home', 'wptv' ); ?></a>
	<?php
		$wptv->the_category( '<span class="arrow">&raquo;</span>' );
		$wptv->the_event( '<span class="arrow">&raquo;</span>' );
	?>
</div>
