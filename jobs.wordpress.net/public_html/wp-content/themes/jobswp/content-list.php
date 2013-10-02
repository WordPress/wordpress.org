<?php
	$evenodd = 0;
	if ( $posts ) {
		global $post;

		foreach ( $posts as $post ) {

			$evenodd = abs( $evenodd - 1 );
			echo '<div class="row row-'. $evenodd . '">';

			echo '<div class="job-date grid_2 alpha">' . get_the_date( 'M j' ) . '</div>';
			echo '<div class="job-title grid_4">';
			echo '<a href="'; the_permalink(); echo '" rel="bookmark">'; the_title(); echo '</a></div>';
			echo '<div class="job-type grid_1 alpha omega">';
			echo jobswp_get_job_meta( get_the_ID(), 'jobtype' );
			echo '</div>';
			echo '<div class="job-location grid_2 omega">';
			echo jobswp_get_job_meta( get_the_ID(), 'location' );
    		echo '</div>';

			echo '<div class="clear"></div>';
			echo '</div>';

		} // End foreach
	} // End if posts
	else {
		echo '<div class="row row-1">';
		echo "<div class='no-job grid_9'>";
		echo sprintf(
			__( 'There are no jobs in this category. If you\'re hiring, you can <a href="%s">post a new job</a>.', 'jobswp' ),
			'/post-a-job'
		);
		echo '</div>';
		echo '</div>';
	}
	?>
	</ul>

	<?php	
		if ( is_front_page() ) {
			global $category;
			echo '<p class="all-job-categories">';
			if ( ! $category )
				$category = array_pop( get_the_terms( get_the_ID(), 'job_category') );

			$link = '';
			$link .= '<a href="' . esc_attr( get_term_link( $category, 'job_category' ) ) . '" ';
			$link .= 'title="'. esc_attr( sprintf( __( 'View all jobs filed under %s', 'jobswp' ), $category->name ) ) . '"';
			$link .= '>';
			$link .= 'Show all '.apply_filters( 'list_terms', $category->name, $category ) . ' jobs &raquo;</a>';		
			echo $link;
			echo '</p>';
		} else {
			jobswp_content_nav( 'all-job-categories' );
		}
	?>

	<div class="clear"></div>
