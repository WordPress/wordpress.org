<?php
global $category;

$job_categories = Jobs_Dot_WP::get_job_categories();

if ( $job_categories ) :

// Groups jobs according to job category.
foreach ( $job_categories as $i => $category ) {
	$job_category_jobs[ $category->slug ] = Jobs_Dot_WP::get_jobs_for_category( $category );
	$latest_post = $job_category_jobs[ $category->slug ][0];
	// Add key for sorting.
	$job_categories[ $i ]->latest_post_date = $latest_post ? $latest_post->post_date : '0';
}

// Sort job categories according to recency of latest post.
$job_categories = wp_list_sort( $job_categories, [ 'latest_post_date' => 'DESC', 'name' => 'ASC' ] );

// Display the categories and their jobs.
foreach ( $job_categories as $category ) {
	$posts = $job_category_jobs[ $category->slug ];

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
