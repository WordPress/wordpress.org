<h4><?php _e( 'Categories', 'rosetta' ); ?></h4>
<ul>
	<?php wp_list_categories( 'title_li=&show_count=1&orderby=count&order=DESC&number=10' ); ?>
</ul>

<h4><?php _e( 'Blog Archives', 'rosetta' ); ?></h4>
<ul>
	<?php wp_get_archives( 'type=monthly&limit=12' ); ?>
</ul>
