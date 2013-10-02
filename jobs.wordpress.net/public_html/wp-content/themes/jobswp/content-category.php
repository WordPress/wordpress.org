<div class="jobs-group">

<?php
	global $category;

	$q = get_queried_object();
	$category = get_term( $q->term_id, $q->taxonomy );

	jobswp_archive_header(
		'<div class="job-list-head grid_7 alpha"><h2 class="job-cat-item job-cat-item-' . esc_attr( $category->slug ) . '">',
		'</h2></div>',
		$category->count,
		$category
	);

	get_template_part( 'content', 'list' );
?>

</div>
