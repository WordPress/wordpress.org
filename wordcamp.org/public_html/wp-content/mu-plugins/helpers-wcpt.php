<?php

defined( 'WPINC' ) or die();

/*
 * Helper functions related to the `wordcamp` post type.
 */


/**
 * Retrieve `wordcamp` posts and their metadata.
 *
 * @param array $args Optional. Extra arguments to pass to `get_posts()`.
 *
 * @return array
 */
function get_wordcamps( $args = array() ) {
	$args = wp_parse_args( $args, array(
		'post_type'   => WCPT_POST_TYPE_ID,
		'post_status' => 'any',
		'orderby'     => 'ID',
		'numberposts' => -1,
		'perm'        => 'readable',
	) );

	$wordcamps = get_posts( $args );

	foreach ( $wordcamps as &$wordcamp ) {
		$wordcamp->meta = get_post_custom( $wordcamp->ID );
	}

	return $wordcamps;
}

/**
 * Retrieves the `wordcamp` post and postmeta associated with the current site.
 *
 * `Site ID` is the most reliable way to associate a site with it's corresponding `wordcamp` post,
 * but wasn't historically assigned when new sites are created. For older sites, we fallback to
 * using the `URL` to associate them. That will only work if the site's site_url() exactly
 * matches the `wordcamp` post's `URL` meta field, though. It could also fail if we ever migrate
 * to a different URL structure.
 *
 * @return false|WP_Post
 */
function get_wordcamp_post() {
	$current_site_id  = get_current_blog_id();
	$current_site_url = site_url();

	switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

	$wordcamp = get_posts( array(
		'post_type'   => 'wordcamp',
		'post_status' => 'any',

		'meta_query' => array(
			'relation' => 'OR',

			array(
				'key'   => '_site_id',
				'value' => $current_site_id,
			),

			array(
				'key'   => 'URL',
				'value' => $current_site_url,
			),
		),
	) );

	if ( isset( $wordcamp[0]->ID ) ) {
		$wordcamp       = $wordcamp[0];
		$wordcamp->meta = get_post_custom( $wordcamp->ID );
	} else {
		$wordcamp = false;
	}

	restore_current_blog();

	return $wordcamp;
}

/**
 * Find the site that corresponds to the given `wordcamp` post
 *
 * @param WP_Post $wordcamp_post
 *
 * @return mixed An integer if successful, or boolean false if failed
 */
function get_wordcamp_site_id( $wordcamp_post ) {
	switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

	if ( ! $site_id = get_post_meta( $wordcamp_post->ID, '_site_id', true ) ) {
		$url = parse_url( get_post_meta( $wordcamp_post->ID, 'URL', true ) );

		if ( isset( $url['host'] ) && isset( $url['path'] ) ) {
			if ( $site = get_site_by_path( $url['host'], $url['path'] ) ) {
				$site_id = $site->blog_id;
			}
		}
	}

	restore_current_blog();

	return $site_id;
}

/**
 * Get a consistent WordCamp name in the 'WordCamp [Location] [Year]' format.
 *
 * The results of bloginfo( 'name' ) don't always contain the year, but the title of the site's corresponding
 * `wordcamp` post is usually named 'WordCamp [Location]', so we can get a consistent name most of the time
 * by using that and adding the year (if available).
 *
 * @param int $site_id Optionally, get the name for a site other than the current one.
 *
 * @return string
 */
function get_wordcamp_name( $site_id = 0 ) {
	$name = false;

	switch_to_blog( $site_id );

	if ( $wordcamp = get_wordcamp_post() ) {
		if ( ! empty( $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] ) ) {
			$name = $wordcamp->post_title . ' ' . date( 'Y', $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] );
		}
	}

	if ( ! $name ) {
		$name = get_bloginfo( 'name' );
	}

	restore_current_blog();

	return $name;
}


/**
 * Extract pieces from a WordCamp.org URL
 *
 * @todo find other code that's doing this same task in an ad-hoc manner, and convert it to use this instead
 *
 * @param string $url
 * @param string $part 'city', 'city-domain' (without the year, e.g. seattle.wordcamp.org), 'year'
 *
 * @return false|string|int False on errors; an integer for years; a string for city and city-domain
 */
function wcorg_get_url_part( $url, $part ) {
	$url_parts = explode( '.', parse_url( $url, PHP_URL_HOST ) );
	$result    = false;

	// Make sure it matches the typical year.city.wordcamp.org structure
	if ( 4 !== count( $url_parts ) ) {
		return $result;
	}

	switch ( $part ) {
		case 'city':
			$result = $url_parts[1];
			break;

		case 'city-domain':
			$result = ltrim( strstr( $url, '.' ), '.' );
			break;

		case 'year':
			$result = absint( $url_parts[0] );
			break;
	}

	return $result;
}


/**
 * Take the start and end dates for a WordCamp and calculate how many days it lasts.
 *
 * @param WP_Post $wordcamp
 *
 * @return int
 */
function wcorg_get_wordcamp_duration( WP_Post $wordcamp ) {
	// @todo Make sure $wordcamp is the correct post type

	$start = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );
	$end   = get_post_meta( $wordcamp->ID, 'End Date (YYYY-mm-dd)', true );

	// Assume 1 day duration if there is no end date
	if ( ! $end ) {
		return 1;
	}

	$duration_raw = $end - $start;

	// Add one second and round up to ensure the end date counts as a day as well
	$duration_days = ceil( ( $duration_raw + 1 ) / DAY_IN_SECONDS );

	return absint( $duration_days );
}

/**
 * Get a <select> dropdown of `wordcamp` posts with a select2 UI.
 *
 * The calling plugin is responsible for validating and processing the form, this just outputs a single field.
 *
 * @param string $name          Optional. The `name` attribute for the `select` element. Defaults to `wordcamp_id`.
 * @param array  $query_options Optional. Extra arguments to pass to `get_posts()`. Defaults to the values in `get_wordcamps()`.
 * @param int    $selected      Optional. The list option to select. Defaults to not selecting any.
 *
 * @return string The HTML for the <select> list.
 */
function get_wordcamp_dropdown( $name = 'wordcamp_id', $query_options = array(), $selected = 0 ) {
	$wordcamps = get_wordcamps( $query_options );

	wp_enqueue_script( 'select2' );
	wp_enqueue_style(  'select2' );

	ob_start();

	?>

	<select name="<?php echo esc_attr( $name ); ?>" class="select2">
		<option value=""><?php _e( 'Select a WordCamp', 'wordcamporg' ); ?></option>
		<option value=""></option>

		<?php foreach ( $wordcamps as $wordcamp ) : ?>
			<option
				value="<?php echo esc_attr( $wordcamp->ID ); ?>"
				<?php selected( $selected, $wordcamp->ID ); ?>
			>
				<?php

				echo esc_html( $wordcamp->post_title );
				if ( ! empty( $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] ) ) {
					echo ' ' . esc_html( date( 'Y', $wordcamp->meta['Start Date (YYYY-mm-dd)'][0] ) );
				}

				?>
			</option>
		<?php endforeach; ?>
	</select>

	<script>
		jQuery( document ).ready( function() {
			jQuery( '.select2' ).select2();
		} );
	</script>

	<?php

	return ob_get_clean();
}
