<div class="jobs-group">

	<?php
	jobswp_archive_header(
		'<div class="job-list-head grid_7 alpha"><h2 class="search-header">' .
		sprintf( __( 'Search Results for: %s', 'jobswp' ), '<span>' . get_search_query() . '</span>' ),
		'</h2></div>',
		$wp_query->found_posts
	);

	get_template_part( 'content', 'list' );

?>

</div>
