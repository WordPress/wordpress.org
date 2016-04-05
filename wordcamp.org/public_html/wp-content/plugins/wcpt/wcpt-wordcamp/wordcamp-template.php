<?php

/**
 * wcpt_has_wordcamps()
 *
 * The main WordCamp loop. WordPress makes this easy for us.
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 * @param array $args Possible arguments to change returned WordCamps
 * @return object Multidimensional array of WordCamp information
 */
function wcpt_has_wordcamps( $args = '' ) {
	global $wcpt_template;

	$default = array(
		// Narrow query down to WordCamp Post Type
		'post_type'        => WCPT_POST_TYPE_ID,

		// No hierarchy
		'post_parent'      => '0',

		// 'author', 'date', 'title', 'modified', 'parent', rand',
		'orderby'          => 'date',

		// 'ASC', 'DESC'
		'order'            => 'DESC',

		// Default is 15
		'posts_per_page'   => 15,

		// Page Number
		'paged'            => 1,

		// Topic Search
		's'                => empty( $_REQUEST['wcs'] ) ? '' : esc_attr( $_REQUEST['wcs'] ),
	);

	// Set up variables
	$wcpt_q = wp_parse_args( $args, $default );
	$r      = extract( $wcpt_q );

	// Call the query
	$wcpt_template = new WP_Query( $wcpt_q );

	// Add pagination values to query object
	$wcpt_template->posts_per_page = $posts_per_page;
	$wcpt_template->paged          = $paged;

	// Only add pagination if query returned results
	if ( (int)$wcpt_template->found_posts && (int)$wcpt_template->posts_per_page ) {

		// Pagination settings with filter
		$wcpt_pagination = apply_filters( 'wcpt_pagination', array (
			'base'      => esc_url_raw( add_query_arg( 'wcpage', '%#%' ) ),
			'format'    => '',
			'total'     => ceil( (int)$wcpt_template->found_posts / (int)$posts_per_page ),
			'current'   => (int)$wcpt_template->paged,
			'prev_text' => '&larr;',
			'next_text' => '&rarr;',
			'mid_size'  => 1
		) );

		// Add pagination to query object
		$wcpt_template->pagination_links = paginate_links ( $wcpt_pagination );
	}

	return apply_filters( 'wcpt_has_wordcamps', $wcpt_template->have_posts(), $wcpt_template );
}

/**
 * wcpt_wordcamps()
 *
 * Whether there are more WordCamps available in the loop
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 * @return object WordCamp information
 */
function wcpt_wordcamps() {
	global $wcpt_template;
	return $wcpt_template->have_posts();
}

/**
 * wcpt_the_wordcamp()
 *
 * Loads up the current WordCamp in the loop
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 * @return object WordCamp information
 */
function wcpt_the_wordcamp() {
	global $wcpt_template;
	return $wcpt_template->the_post();
}

/**
 * wcpt_wordcamp_id()
 *
 * Echo id from wcpt_wordcamp_id()
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_id()
 */
function wcpt_wordcamp_id() {
	echo wcpt_get_wordcamp_id();
}
	/**
	 * wcpt_get_wordcamp_id()
	 *
	 * Get the id of the user in a WordCamp loop
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string WordCamp id
	 */
	function wcpt_get_wordcamp_id() {
		global $wcpt_template;

		if ( isset( $wcpt_template->post ) )
			$wordcamp_id = $wcpt_template->post->ID;
		else
			$wordcamp_id = get_the_ID();

		return apply_filters( 'wcpt_get_wordcamp_id', $wordcamp_id );
	}

/**
 * wcpt_wordcamp_permalink ()
 *
 * Output the link to the WordCamp in the WordCamp loop
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @param int $wordcamp_id optional
 * @uses wcpt_get_wordcamp_permalink()
 */
function wcpt_wordcamp_permalink( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_permalink( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_permalink()
	 *
	 * Return the link to the WordCamp in the loop
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @param int $wordcamp_id optional
	 * @uses apply_filters
	 * @uses get_permalink
	 * @return string Permanent link to WordCamp
	 */
	function wcpt_get_wordcamp_permalink( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		return apply_filters( 'wcpt_get_wordcamp_permalink', get_permalink( $wordcamp_id ) );
	}

/**
 * wcpt_wordcamp_title ()
 *
 * Output the title of the WordCamp in the loop
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @param int $wordcamp_id optional
 * @uses wcpt_get_wordcamp_title()
 */
function wcpt_wordcamp_title( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_title( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_title ()
	 *
	 * Return the title of the WordCamp in the loop
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @param int $wordcamp_id optional
	 * @uses apply_filters
	 * @uses get_the_title()
	 * @return string Title of WordCamp
	 *
	 */
	function wcpt_get_wordcamp_title( $wordcamp_id = 0 ) {
		return apply_filters( 'wcpt_get_wordcamp_title', get_the_title( $wordcamp_id ) );
	}

/**
 * wcpt_wordcamp_link ()
 *
 * Output the title of the WordCamp in the loop
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @param int $wordcamp_id optional
 * @uses wcpt_get_wordcamp_link()
 */
function wcpt_wordcamp_link( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_link( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_link ()
	 *
	 * Return the title of the WordCamp in the loop
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @param int $wordcamp_id optional
	 * @uses apply_filters
	 * @uses get_the_link()
	 * @return string Title of WordCamp
	 *
	 */
	function wcpt_get_wordcamp_link( $wordcamp_id = 0 ) {

		$title = get_the_title( $wordcamp_id );

		// Has URL
		if ( $url = wcpt_get_wordcamp_url( $wordcamp_id ) )
			$link = '<a href="' . esc_url( $url ) . '">' . esc_html( $title ) . '</a>';

		// No URL
		else
			$link = esc_html( $title );

		return apply_filters( 'wcpt_get_wordcamp_link', $link );
	}

/**
 * wcpt_wordcamp_start_date ()
 *
 * Output the WordCamps date
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_start_date()
 * @param int $wordcamp_id optional
 */
function wcpt_wordcamp_start_date( $wordcamp_id = 0, $format = 'F j, Y' ) {
	echo wcpt_get_wordcamp_start_date( $wordcamp_id, $format );
}
	/**
	 * wcpt_get_wordcamp_start_date ()
	 *
	 * Return the WordCamps date
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string
	 * @param int $wordcamp_id optional
	 */
	function wcpt_get_wordcamp_start_date( $wordcamp_id = 0, $format = 'F j, Y' ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		if ( $date = get_post_meta( $wordcamp_id, 'Start Date (YYYY-mm-dd)', true ) )
			$date = date( $format, $date );

		return apply_filters( 'wcpt_get_wordcamp_start_date', $date, $format );
	}

/**
 * wcpt_wordcamp_end_date ()
 *
 * Output the WordCamps date
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_end_date()
 * @param int $wordcamp_id optional
 */
function wcpt_wordcamp_end_date( $wordcamp_id = 0, $format = 'F j, Y' ) {
	echo wcpt_get_wordcamp_end_date( $wordcamp_id, $format );
}
	/**
	 * wcpt_get_wordcamp_end_date ()
	 *
	 * Return the WordCamps date
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string
	 * @param int $wordcamp_id optional
	 */
	function wcpt_get_wordcamp_end_date( $wordcamp_id = 0, $format = 'F j, Y' ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		if ( $date = get_post_meta( $wordcamp_id, 'End Date (YYYY-mm-dd)', true ) )
			$date = date( $format, $date );

		return apply_filters( 'wcpt_get_wordcamp_end_date', $date, $format );
	}

/**
 * wcpt_wordcamp_location ()
 *
 * Output the WordCamps location
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_location()
 * @param int $wordcamp_id optional
 */
function wcpt_wordcamp_location( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_location( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_location ()
	 *
	 * Return the WordCamps location
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string
	 * @param int $wordcamp_id optional
	 */
	function wcpt_get_wordcamp_location( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		return apply_filters( 'wcpt_get_wordcamp_location', get_post_meta( $wordcamp_id, 'Location', true ) );
	}

/**
 * wcpt_wordcamp_organizer_name ()
 *
 * Output the WordCamps organizer
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_organizer_name()
 * @param int $wordcamp_id optional
 */
function wcpt_wordcamp_organizer_name( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_organizer_name( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_organizer_name ()
	 *
	 * Return the WordCamps organizer
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string
	 * @param int $wordcamp_id optional
	 */
	function wcpt_get_wordcamp_organizer_name( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		return apply_filters( 'wcpt_get_wordcamp_organizer_name', get_post_meta( $wordcamp_id, 'Organizer Name', true ) );
	}

/**
 * wcpt_wordcamp_venue_name ()
 *
 * Output the WordCamps organizer
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_venue_name()
 * @param int $wordcamp_id optional
 */
function wcpt_wordcamp_venue_name( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_venue_name( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_venue_name ()
	 *
	 * Return the WordCamps organizer
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string
	 * @param int $wordcamp_id optional
	 */
	function wcpt_get_wordcamp_venue_name( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		return apply_filters( 'wcpt_get_wordcamp_venue_name', get_post_meta( $wordcamp_id, 'Venue Name', true ) );
	}

/**
 * wcpt_wordcamp_url ()
 *
 * Output the WordCamps URL
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @uses wcpt_get_wordcamp_url()
 * @param int $wordcamp_id optional
 */
function wcpt_wordcamp_url( $wordcamp_id = 0 ) {
	echo wcpt_get_wordcamp_url( $wordcamp_id );
}
	/**
	 * wcpt_get_wordcamp_url ()
	 *
	 * Return the WordCamps URL
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @return string
	 * @param int $wordcamp_id optional
	 */
	function wcpt_get_wordcamp_url( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		return apply_filters( 'wcpt_get_wordcamp_url', set_url_scheme( get_post_meta( $wordcamp_id, 'URL', true ), 'https' ) );
	}

/**
 * wcpt_wordcamp_pagination_count ()
 *
 * Output the pagination count
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 *
 * @global WP_Query $wcpt_template
 */
function wcpt_wordcamp_pagination_count() {
	echo wcpt_get_wordcamp_pagination_count();
}
	/**
	 * wcpt_get_wordcamp_pagination_count ()
	 *
	 * Return the pagination count
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @global WP_Query $wcpt_template
	 * @return string
	 */
	function wcpt_get_wordcamp_pagination_count() {
		global $wcpt_template;

		// Set pagination values
		$start_num = intval( ( $wcpt_template->paged - 1 ) * $wcpt_template->posts_per_page ) + 1;
		$from_num  = wcpt_number_format( $start_num );
		$to_num    = wcpt_number_format( ( $start_num + ( $wcpt_template->posts_per_page - 1 ) > $wcpt_template->found_posts ) ? $wcpt_template->found_posts : $start_num + ( $wcpt_template->posts_per_page - 1 ) );
		$total     = wcpt_number_format( $wcpt_template->found_posts );

		// Set return string
		if ( $total > 1 )
			$retstr = sprintf( __( 'Viewing %1$s to %2$s (of %3$s)', 'wcpt' ), $from_num, $to_num, $total );
		else
			$retstr = sprintf( __( 'Viewing %1$s WordCamp', 'wcpt' ), $total );

		// Filter and return
		return apply_filters( 'wcpt_get_wordcamp_pagination_count', $retstr );
	}

/**
 * wcpt_wordcamp_pagination_links ()
 *
 * Output pagination links
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wcpt_wordcamp_pagination_links() {
	echo wcpt_get_wordcamp_pagination_links();
}
	/**
	 * wcpt_get_wordcamp_pagination_links ()
	 *
	 * Return pagination links
	 *
	 * @package WordCamp Post Type
	 * @subpackage Template Tags
	 * @since WordCamp Post Type (0.1)
	 *
	 * @global WP_Query $wcpt_template
	 * @return string
	 */
	function wcpt_get_wordcamp_pagination_links() {
		global $wcpt_template;

		return apply_filters( 'wcpt_get_wordcamp_pagination_links', $wcpt_template->pagination_links );
	}


/**
 * Moved from WordCamp Central functions.php
 */
if ( ! function_exists( 'wcpt_wordcamp_physical_address' ) ) :
	function wcpt_wordcamp_physical_address( $wordcamp_id = 0 ) {
		echo wp_filter_kses( nl2br( wcpt_get_wordcamp_physical_address( $wordcamp_id ) ) );
	}
endif;

if ( ! function_exists( 'wcpt_get_wordcamp_physical_address' ) ) :
	function wcpt_get_wordcamp_physical_address( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		$address = get_post_meta( $wordcamp_id, 'Physical Address', true );
		return apply_filters( 'wcpt_get_wordcamp_physical_address', $address );
	}
endif;

if ( ! function_exists( 'wcpt_wordcamp_venue_url' ) ) :
	function wcpt_wordcamp_venue_url( $wordcamp_id = 0 ) {
		echo esc_url( wcpt_get_wordcamp_venue_url( $wordcamp_id ) );
	}
endif;

if ( ! function_exists( 'wcpt_get_wordcamp_venue_url' ) ) :
	function wcpt_get_wordcamp_venue_url( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		$venue_url = get_post_meta( $wordcamp_id, 'Website URL', true );
		return apply_filters( 'wcpt_get_wordcamp_venue_url', $venue_url );
	}
endif;

if ( ! function_exists( 'wcpt_get_wordcamp_twitter_screen_name' ) ) :
	function wcpt_get_wordcamp_twitter_screen_name( $wordcamp_id = 0 ) {
		if ( empty( $wordcamp_id ) )
			$wordcamp_id = wcpt_get_wordcamp_id();

		$screen_name = get_post_meta( $wordcamp_id, 'Twitter', true );
		return apply_filters( 'wcpt_get_wordcamp_twitter_screen_name', $screen_name );
	}
endif;

/*
 * Miscellaneous
 */
function wcpt_feed_event_info( $content ) {
	$custom_fields = get_post_custom();
	ob_start();
	
	?>
	
	<?php if ( isset( $custom_fields[ 'Start Date (YYYY-mm-dd)' ][0] ) && $custom_fields[ 'Start Date (YYYY-mm-dd)' ][0] ) : ?>
		<p>
			Dates:
			<?php echo esc_html( date( 'F j, Y', $custom_fields[ 'Start Date (YYYY-mm-dd)' ][0] ) ); ?>
	
			<?php if ( isset( $custom_fields[ 'End Date (YYYY-mm-dd)' ][0] ) && $custom_fields[ 'End Date (YYYY-mm-dd)' ][0] ) : ?>
				- <?php echo esc_html( date( 'F j, Y', $custom_fields[ 'End Date (YYYY-mm-dd)' ][0] ) ); ?>
			<?php endif; ?>
		</p>
	<?php endif; ?>

	<?php if ( isset( $custom_fields[ 'Location' ][0] ) && $custom_fields[ 'Location' ][0] ) : ?>
		<p>
			Location:
			<?php echo esc_html( $custom_fields[ 'Location' ][0] ); ?>
		</p>
	<?php endif; ?>

	<?php if ( isset( $custom_fields[ 'Venue Name' ][0] ) && $custom_fields[ 'Venue Name' ][0] ) : ?>
		<p>
			Venue:
			<?php echo esc_html( $custom_fields[ 'Venue Name' ][0] ); ?>
		</p>
	<?php endif; ?>
	
	<?php if ( isset( $custom_fields[ 'URL' ][0] ) && '' != str_replace( 'http://', '', $custom_fields[ 'URL' ][0] ) ) : ?>
		<p>
			Website:
			<a href="<?php echo esc_attr( esc_url( $custom_fields[ 'URL' ][0] ) ); ?>"><?php echo esc_html( esc_url( $custom_fields[ 'URL' ][0] ) ); ?></a>
		</p>
	<?php endif; ?>
	
	<?php
	
	$event_info = ob_get_clean();

	if ( 'the_excerpt_rss' == current_filter() ) {
		$event_info = strip_tags( $event_info );
	}
	
	return $content . $event_info;
}
add_filter( 'the_content_feed', 'wcpt_feed_event_info' );
add_filter( 'the_excerpt_rss',  'wcpt_feed_event_info' );

/**
 * Add feed for WordCamp posts to the head section
 * 
 * This helps publicize the feed, because it will show up in feed aggregators when users enter the site URL, even if they don't know the feed exists. 
 */
function add_wordcamp_feed_link_to_head() {
	if ( ! is_post_type_archive( WCPT_POST_TYPE_ID ) ) {
		?>
		
		<link rel="alternate" type="<?php echo esc_attr( feed_content_type() ); ?>" title="New WordCamp Announcements" href="<?php echo esc_url( get_post_type_archive_feed_link( WCPT_POST_TYPE_ID ) ); ?>" />
	
		<?php
	}
}
add_action( 'wp_head', 'add_wordcamp_feed_link_to_head', 4 ); // after feed_links_extra()
