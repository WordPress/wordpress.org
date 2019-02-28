<?php
/**
 * Pagination template part, use with get_template_part().
 *
 * @package WordPressTV_Blog
 */

the_posts_pagination( array(
	'prev_text' => __( '<span class="meta-nav">&larr;</span> Newer posts', 'wptv' ),
	'next_text' => __( 'Older posts <span class="meta-nav">&rarr;</span>', 'wptv' ),
) );
