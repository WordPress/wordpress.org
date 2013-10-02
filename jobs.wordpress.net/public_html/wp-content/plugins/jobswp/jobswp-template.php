<?php

defined( 'ABSPATH' ) or die();

/**
 * Returns the value of a job's meta field, for display.
 *
 * @param int $post_id The post ID
 * @param string $meta_key The field name/key
 * @return string
 */
function jobswp_get_job_meta( $post_id, $meta_key ) {
	$val = get_post_meta( $post_id, $meta_key, true );
	$val = apply_filters( 'jobswp_metadata_raw', $val, $post_id, $meta_key );
	$val = Jobs_Dot_WP::validate_job_field( $meta_key, $val );

	switch ( $meta_key ) :
		case 'location':
			if ( empty( $val ) )
				$val = 'N/A';
			break;
		case 'jobtype':
			if ( 'ppt' == $val )
				$val = 'Project';
			elseif ( 'ft' == $val )
				$val = 'Full Time';
			elseif ( 'pt' == $val )
				$val = 'Part Time';
			else
				$val = 'N/A';
			break;
		case 'howtoapply':
			$method = get_post_meta( $post_id, 'howtoapply_method', true );
			// For older jobs that didn't define howtoapply_method explicitly, figure it out
			if ( ! $method ) {
				if ( 0 < strpos( $val, '@' ) )
					$method = 'email';
				elseif ( 0 === strpos( $val, 'http' ) )
					$method = 'web';
				else
					$method = 'phone';
			}
			if ( 'email' == $method ) {
				$val = sprintf( __( 'Via <a href="%s">email</a>', 'jobswp' ), esc_attr( 'mailto:' . sanitize_email( $val ) ) );
			} elseif ( 'web' == $method ) {
				// Prepend 'http://' if no protocol was specified by job poster
				if ( 0 !== strpos( $val, 'http' ) )
					$val = 'http://' . $val;
				$val = sprintf( __( 'Via <a href="%s">web form</a>', 'jobswp' ), esc_attr( esc_url_raw( $val ) ) );
			} else {
				$val = esc_html( $val );
			}
			break;
	endswitch;

	return apply_filters( 'jobswp_metadata', $val, $post_id, $meta_key );
}

/**
 * Outputs the "table" section header for a given job category.
 *
 * @param string $before Text to appear before the header
 * @param string $after Text to appear after the header
 * @param int $jobscnt Count of jobs in the given job category/section
 * @param WP_Category|null $category The job category, if a job category
 * @return string
 */
function jobswp_archive_header( $before = '', $after = '', $jobscnt = 0, $category = null ) {
	$output = '<div class="row row-head">';
	$link = $before;
	if ( $category ) {
			$link .= '<a href="' . get_term_feed_link( $category->term_id, $category->taxonomy ) . '"';
			$title = ' title="' . $category->name . '"';
			$alt = ' alt="' . $category->name . '"';
			$link .= $title;
			$link .= '>';
			$link .= '</a> ';
			$link .= '<a href="' . get_term_link( $category, 'job_category' ) . '" ';
			$link .= 'title="' . sprintf( __( 'View all jobs listed under %s', 'jobswp' ), esc_attr( $category->name ) ) . '"';
			$link .= '>';
			$link .= apply_filters( 'list_cats', $category->name, $category ).'</a>';
	}
	$link .= ' '.$after;        
	$output .= $link;
    $output .= '<div class="grid_2 omega jobs-count">';

	if ( is_search() ) {
		$feed_link = get_search_feed_link();
		$jobscnt = '';
	} else {
		$feed_link = get_term_feed_link( $category->term_id, $category->taxonomy );
		$jobscnt = sprintf( _n( '%d job', '%d jobs', $jobscnt, 'jobswp' ), $jobscnt );
	}

	$output .= '<a href="' . $feed_link . '"><div class="dashicons dashicons-rss"></div></a>' . $jobscnt . '</div>
		</div>
		<div class="clear"></div>
		<div class="row job-list-col-labels">
			<div class="job-date grid_2 alpha">Date Posted</div>
			<div class="job-title grid_4">Job Title</div>
			<div class="job-type grid_1 alpha omega">Job Type</div>
			<div class="job-location grid_2 omega">Location</div>
			<div class="clear"></div>
		</div>';

	echo $output;
}

/**
 * Outputs the text field input and surrounding markup.
 *
 * @param string $field_name The field name/key
 * @param string $field_label The label text
 * @param boolean $required Is the field a required input?
 * @param string $type The HTML5 input type. Default is 'text'.
 * @return string
 */
function jobswp_text_field( $field_name, $field_label, $required = false, $type = 'text' ) {
	$field_name  = esc_attr( $field_name );
	$field_label = esc_html( $field_label );

	echo '<div class="post-job-input post-job-input-$field_name">' . "\n";
	echo "<label for='$field_name'>$field_label" . ( $required ? '*' : '' ) . "</label>\n";

	$html5_input_types = array( 'color', 'date', 'datetime', 'datetime-local', 'email', 'month', 'number',
		'range', 'search', 'tel', 'time', 'url', 'week' );
	if ( ! in_array( $type, $html5_input_types ) )
		$type = 'text';

	echo "<input type='$type' name='$field_name' class='" .
		( $required ? jobswp_required_field_classes( $field_name ) : '' ) .
		"' " .
		jobswp_field_value( $field_name ) .
		( $required ? ' required' : '' ) .
		" />\n";
	echo "</div>\n";
}

/**
 * Returns the classes for a required field.
 *
 * Takes into consideration if it is called during a POST. If so and the field
 * is not defined or is blank, then 'lacks-input' is also assigned.
 *
 * @param string $field The field name/key
 * @return string Space-separated string of classes
 */
function jobswp_required_field_classes( $field ) {
	$classes = 'required';
	if ( $_POST && ( ! isset( $_POST[ $field ] ) || '' == trim( $_POST[ $field ] ) ) )
		$classes .= ' lacks-input';
	return $classes;
}

/**
 * Returns the appropriate field value markup for use in appropriate form field.
 *
 * @param string $field Field name/key
 * @param string $option_value Related value, if appropriate. (e.g. the other
 *  value to compare against for selected() or checked())
 * @return string
 */
function jobswp_field_value( $field, $option_value = '' ) {
	$val = '';

	if ( $_POST && isset( $_POST[ $field ] ) && ! empty( $_POST[ $field ] ) ) {
		// Allow certain HTML in job_description field
		if ( 'job_description' == $field )
			$val = stripslashes( wp_filter_kses( trim( $_POST[ $field ] ) ) );
		else
			$val = esc_attr( trim( strip_tags( stripslashes( $_POST[ $field ] ) ) ) );
	}

	// Output appropriate attribute based on field type
	if ( $val ) {
		if ( in_array( $field, array( 'category', 'howtoapply_method', 'jobtype' ) ) )
			return selected( $val, $option_value, false );
		elseif ( 'job_description' == $field )
			return $val;
		else
			return "value='$val'";
	}
}

/**
 * Gets the list of allowed HTML tags.
 *
 * Like WP's allowed_tags(), but gets the allowed tags via wp_kses_allowed_html() instead
 * of using the global $allowedtags.
 *
 * @return string Displayable string of acceptable tags (e.g. "<tag1> <tag2 attr=''> <tag3>")
 */
function jobswp_allowed_tags() {
	$allowed = '';
	$allowedtags = wp_kses_allowed_html();
	ksort( $allowedtags );
	foreach ( (array) $allowedtags as $tag => $attributes ) {
		$allowed .= '<'.$tag;
		if ( 0 < count( $attributes ) ) {
			foreach ( $attributes as $attribute => $limits ) {
				$allowed .= ' '.$attribute.'=""';
			}
		}
		$allowed .= '> ';
	}
	return htmlentities( $allowed );
}
