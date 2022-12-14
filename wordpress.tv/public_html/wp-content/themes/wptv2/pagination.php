<?php
/**
 * Pagination template part, use with get_template_part()
 *
 */

the_posts_pagination( array(
	'prev_text' => __( '<span class="meta-nav">&larr;</span> Newer videos', 'wptv' ),
	'next_text' => __( 'Older videos <span class="meta-nav">&rarr;</span>', 'wptv' ),
) );
