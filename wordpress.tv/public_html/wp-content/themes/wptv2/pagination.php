<?php
/**
 * Pagination template part, use with get_template_part()
 */

if ( $wp_query->max_num_pages > 1 ) :
?>
<div class="pagination">
	<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older videos', 'wptv' ) ); ?></div>
	<div class="nav-next"><?php previous_posts_link( __( 'Newer videos <span class="meta-nav">&rarr;</span>', 'wptv' ) ); ?></div>
</div>
<?php
endif;
