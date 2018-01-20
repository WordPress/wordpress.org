<?php
/**
 * Pagination template part, use with get_template_part().
 *
 * @package WordPressTV_Blog
 */

if ( $wp_query->max_num_pages > 1 ) :
	?>
	<div class="pagination">
		<div class="nav-previous"><?php next_posts_link( __( '<span class="meta-nav">&larr;</span> Older posts', 'wptv' ) ); ?></div>
		<div class="nav-next"><?php previous_posts_link( __( 'Newer posts <span class="meta-nav">&rarr;</span>', 'wptv' ) ); ?></div>
	</div>
	<?php
endif;
