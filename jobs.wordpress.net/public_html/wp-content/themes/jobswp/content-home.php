<?php
global $category;

$job_categories = Jobs_Dot_WP::get_job_categories();

if ( $job_categories ) :

foreach ( $job_categories as $category ) {
	$posts = Jobs_Dot_WP::get_jobs_for_category( $category );

	//Display the name of the category, whether there are jobs or not
	echo '<div class="jobs-group">';

	jobswp_archive_header(
		'<div class="job-list-head grid_7 alpha"><h2 class="job-cat-item job-cat-item-' . esc_attr( $category->slug ) . '">',
		'</h2></div>',
		$category->count,
		$category
	);

	get_template_part( 'content', 'list' );

	echo "</div>\n";
}

else : // Else no job categories defined.

	echo '<div class="jobs-group">';
	echo '<div class="job-list-head grid_7 alpha"><h2 class="job-cat-item">' . __( 'No job categories defined.', 'jobswp' ) . '</h2></div>';
	echo "</div>\n";

endif;
