<?php
/**
 * Breadcrumbs template part, use with get_template_part()
 */

global $wptv;
?>
<div class="breadcrumb">
	<a href="<?php echo esc_attr( home_url() );?>">Home</a>
	<?php
		$wptv->the_category( '<span class="arrow">&raquo;</span>' );
		$wptv->the_event( '<span class="arrow">&raquo;</span>' );
	?>
</div>
