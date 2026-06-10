<?php

defined( 'ABSPATH' ) or die();

class Jobs_Dot_WP_Moderation {

	public static function init() {
		add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ], 10, 2 );
	}

	/**
	 * Adds the metaboxes.
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post object.
	 */
	public static function add_meta_boxes() {
		add_meta_box( 'jobs_similar', __( 'Similar Jobs', 'jobswp' ), [ __CLASS__, 'meta_box_similar_jobs' ], 'job', 'normal', 'default' );
	}

	/**
	 * Outputs the contents for the Similar Jobs metabox.
	 *
	 * Similarity is based on job title, job description, job poster, job poster email, job poster IP address.
	 *
	 * For matches, only concerned with showing Title, Job Categories, Poster, Poster Email, Author IP.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Associative array of additional data.
	 */
	public static function meta_box_similar_jobs( $post, $args ) {
		global $wpdb;

		$post_title = get_the_title( $post );
		$post_content = $post->post_content;
		$job_poster_first_name = get_post_meta( $post->ID, 'first_name', true );
		$job_poster_last_name = get_post_meta( $post->ID, 'last_name', true );
		$job_poster_email = get_post_meta( $post->ID, 'email', true) ;

		$query = $wpdb->prepare(
			"SELECT DISTINCT $wpdb->posts.*
			FROM $wpdb->posts
			LEFT JOIN $wpdb->postmeta ON ($wpdb->posts.ID = $wpdb->postmeta.post_id)
			WHERE $wpdb->posts.post_type = 'job'
			AND $wpdb->posts.ID != %d
			AND $wpdb->posts.post_status IN ( 'draft', 'publish' )
			AND (
				$wpdb->posts.post_title = %s
				OR (
					$wpdb->postmeta.meta_key = 'first_name'
					AND $wpdb->postmeta.meta_value = %s
					AND $wpdb->postmeta.meta_key = 'last_name'
					AND $wpdb->postmeta.meta_value = %s
				)
				OR $wpdb->posts.post_content = %s
				OR ($wpdb->postmeta.meta_key = 'email'
					AND $wpdb->postmeta.meta_value = %s)
			)",
			$post->ID,
			$post_title,
			$job_poster_first_name,
			$job_poster_last_name,
			$post_content,
			$job_poster_email
		);
	
		$similar_jobs = $wpdb->get_results( $query );

		if ($similar_jobs) {
			echo '<table class="similar-jobs-table wp-list-table widefat fixed striped table-view-list posts">';
			echo '<thead>';
			echo '<tr>';
			echo '<th>Title</th>';
			echo '<th>Status</th>';
			echo '<th>Job Category</th>';
			echo '<th>Job Poster</th>';
			echo '<th>Job Poster Email</th>';
			echo '<th>Notes</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';
	
			foreach ($similar_jobs as $similar_job) {
				echo '<tr>';
				echo '<td><a href="' . esc_url( get_edit_post_link( $similar_job->ID ) ) . '">' . esc_html( $similar_job->post_title ) . '</a></td>';
				echo '<td>' . $similar_job->post_status . '</td>';
    // Output job categories
    echo '<td>';
    $job_categories = get_the_terms($similar_job->ID, 'job_category');
    if ($job_categories && !is_wp_error($job_categories)) {
        $category_names = wp_list_pluck($job_categories, 'name');
        echo implode(', ', $category_names);
    }
    echo '</td>';
				echo '<td>' . get_post_meta( $similar_job->ID, 'first_name', true ) . ' ' . get_post_meta( $similar_job->ID, 'last_name', true ) . '</td>';
				echo '<td>' . get_post_meta( $similar_job->ID, 'email', true ) . '</td>';
echo '<td>';
if ( $similar_job->post_content === $post->post_content ) {
	echo 'Same job description';
}
echo '</td>';
				echo '</tr>';
			}
	
			echo '</tbody>';
			echo '</table>';
		} else {
			echo __( 'No similar jobs found.', 'jobswp' );
		}
	}

}

add_action( 'plugins_loaded', [ 'Jobs_Dot_WP_Moderation', 'init' ] );
