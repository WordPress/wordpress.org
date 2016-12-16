<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

const USERNAMES_KEY     = 'wcpt-mentors-usernames';
const MENTORS_CACHE_KEY = 'wcpt-mentors-data';

const STATUS_UPDATED       = 'Mentor usernames updated.';
const STATUS_INVALID_NONCE = 'Invalid nonce. Mentor usernames were not updated.';
const STATUS_NO_USERNAME   = 'No username data was submitted.';
const STATUS_UPDATE_FAILED = 'Mentor usernames could not be updated.';


add_action( 'admin_init', __NAMESPACE__ . '\admin_init' );

/**
 * Initialize admin functionality
 */
function admin_init() {
	// Send a nag email about un-mentored camps every Tuesday
	if ( ! wp_next_scheduled( 'wcpt_mentors_assignment_nag' ) ) {
		wp_schedule_single_event( strtotime( 'next Tuesday' ), 'wcpt_mentors_assignment_nag' );
	}

	// Admin notices
	if ( isset( $_GET['wcpt-status'] ) ) {
		add_action( 'admin_notices', __NAMESPACE__ . '\status_admin_notice' );
	}
}

add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_pages' );

/**
 * Register new admin pages
 */
function add_admin_pages() {
	$page_hook = \add_submenu_page(
		'edit.php?post_type=wordcamp',
		'Mentors',
		'Mentors',
		'manage_network',
		'mentors',
		__NAMESPACE__ . '\render_options_page'
	);
}

/**
 * Render the view for the options page
 */
function render_options_page() {
	$mentors          = get_mentors();
	$unmentored_camps = get_unmentored_camps();
	$usernames        = get_usernames();

	require_once( dirname( __DIR__ ) . '/views/mentors/dashboard.php' );
}

/**
 * Get all mentors
 *
 * @return array
 */
function get_mentors() {
	$mentors = get_all_mentor_data();

	if ( empty( $mentors ) ) {
		return array();
	}

	$mentor_email = wp_list_pluck( $mentors, 'email' );

	$post_statuses = array_diff(
		\WordCamp_Loader::get_mentored_post_statuses(),
		array( 'wcpt-closed' )
	);

	$mentored_camps = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => $post_statuses,
		'posts_per_page' => 10000,
		'order'          => 'ASC',
		'orderby'        => 'name',
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => 'Mentor WordPress.org User Name',
				'value'   => get_usernames(),
				'compare' => 'IN',
			),
			array(
				'key'     => 'Mentor E-mail Address',
				'value'   => $mentor_email,
				'compare' => 'IN',
			),
		),
	) );

	$mentors = array_map( function( $value ) {
		$value['camps_mentoring'] = array();
		return $value;
	}, $mentors );

	foreach ( $mentored_camps as $camp ) {
		$username = get_post_meta( $camp->ID, 'Mentor WordPress.org User Name', true );
		$email    = get_post_meta( $camp->ID, 'Mentor E-mail Address', true );

		// Camp has username
		if ( false !== $username && isset( $mentors[ $username ] ) ) {
			$mentors[ $username ]['camps_mentoring'][ $camp->ID ] = $camp->post_title;
		}
		// Camp doesn't have username, but has email
		else if ( false !== $email && false !== $key = array_search( $email, $mentor_email ) ) {
			// Add asterisk to show camp has mentor email but not username
			$mentors[ $key ]['camps_mentoring'][ $camp->ID ] = $camp->post_title . ' *';
		}
	}

	return $mentors;
}

/**
 * Count the total number of camps being mentored
 *
 * @param array $mentors
 *
 * @return int
 */
function count_camps_being_mentored( $mentors ) {
	$camps_being_mentored = 0;

	foreach ( $mentors as $mentor ) {
		$camps_being_mentored += count( $mentor['camps_mentoring'] );
	}

	return $camps_being_mentored;
}

/**
 * Get active camps that haven't been assigned a mentor
 *
 * @return array Multidimensional array of un-mentored camps divided by whether they have a start date set yet.
 */
function get_unmentored_camps() {
	$unmentored_camps = array(
		'yesdate' => array(),
		'nodate'  => array(),
	);

	$post_statuses = array_diff(
		\WordCamp_Loader::get_mentored_post_statuses(),
		array( 'wcpt-closed' )
	);

	$posts = get_posts( array(
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => $post_statuses,
		'posts_per_page' => 10000,
		'order'          => 'ASC',
		'orderby'        => 'meta_value name',
		'meta_key'       => 'Start Date (YYYY-mm-dd)',
		'meta_query'     => array(
			'relation' => 'OR',
			array(
				'key'     => 'Mentor WordPress.org User Name',
				'value'   => get_usernames(),
				'compare' => 'NOT IN'
			),
			array(
				'key'     => 'Mentor WordPress.org User Name',
				'compare' => 'NOT EXISTS'
			),
		),
	) );

	foreach ( $posts as $post ) {
		$start_date = wcpt_get_wordcamp_start_date( $post->ID );
		$email = get_post_meta( $post->ID, 'Mentor E-mail Address', true );
		$section = ( $start_date ) ? 'yesdate' : 'nodate';

		$unmentored_camps[ $section ][ $post->ID ] = array(
			'name'      => $post->post_title,
			'date'      => $start_date,
			'has_email' => ! ! $email,
		);
	}

	return $unmentored_camps;
}

/**
 * Get the stored array of mentor usernames and sanitize before returning.
 *
 * @return array
 */
function get_usernames() {
	$raw_usernames = get_site_option( USERNAMES_KEY, array() );

	return sanitize_usernames( $raw_usernames );
}

/**
 * Sanitize a list of usernames and store in the database.
 *
 * @param string|array $usernames
 *
 * @return bool
 */
function set_usernames( $usernames ) {
	$sanitized_usernames = sanitize_usernames( $usernames );

	return update_site_option( USERNAMES_KEY, $sanitized_usernames );
}

/**
 * Sanitize an array of usernames. Convert to an array first if a string is provided.
 *
 * @param string|array $usernames
 *
 * @return array
 */
function sanitize_usernames( $usernames ) {
	if ( ! is_array( $usernames ) ) {
		$usernames = explode( ',', $usernames );
	}

	$usernames = array_map( 'trim', $usernames );

	$usernames = array_map( 'sanitize_user', $usernames );

	// Remove empty array items
	return array_filter( $usernames );
}

/**
 * @todo
 *
 * @param array $sanitized_usernames
 */
function validate_usernames( array $sanitized_usernames ) {
	// todo
}

add_action( 'admin_post_wcpt-mentors-update-usernames', __NAMESPACE__ . '\update_usernames' );

/**
 * Admin Post callback to receive a list of usernames from a form and store it in the database.
 */
function update_usernames() {
	// Base redirect URL
	$redirect_url = add_query_arg( array(
		'post_type' => 'wordcamp',
		'page'      => 'mentors',
	), admin_url( 'edit.php' ) );

	// Invalid nonce
	if ( ! isset( $_POST['wcpt-mentors-nonce'] ) ||
		 ! wp_verify_nonce( $_POST['wcpt-mentors-nonce'], 'wcpt-mentors-update-usernames' ) ) {
		$status_code = 'invalid-nonce';
	}
	// No usernames field
	else if ( ! isset( $_POST['wcpt-mentors-usernames'] ) ) {
		$status_code = 'no-username';
	}
	//
	else {
		$raw_usernames = $_POST['wcpt-mentors-usernames'];

		$success = set_usernames( $raw_usernames );

		if ( $success ) {
			$status_code = 'updated';

			// Bust cache
			delete_site_transient( MENTORS_CACHE_KEY );
		} else {
			$status_code = 'update-failed';
		}
	}

	$redirect_url = add_query_arg( 'wcpt-status', $status_code, $redirect_url );

	wp_safe_redirect( esc_url_raw( $redirect_url ) );
}

/**
 * Display the result of `update_usernames`
 */
function status_admin_notice() {
	global $pagenow;

	if ( 'edit.php' !== $pagenow ||
		 ! isset( $_GET['page'] ) ||
		 'mentors' !== $_GET['page'] ||
		 ! isset( $_GET['wcpt-status'] ) ) {
		return;
	}

	$status = 'STATUS_' . strtoupper( str_replace( '-', '_', $_GET['wcpt-status'] ) );

	if ( ! defined( __NAMESPACE__ . '\\' . $status ) ) {
		return;
	}

	$type = 'success';
	if ( 'STATUS_UPDATED' !== $status ) {
		$type = 'error';
	}

	$message = constant( __NAMESPACE__ . '\\' . $status );

	?>
	<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
		<?php echo wpautop( esc_html( $message ) ); ?>
	</div>
<?php
}

/**
 * Retrieve name and email for a particular mentor username.
 *
 * @param string $username
 *
 * @return array
 */
function get_mentor_data( $username ) {
	$usernames = get_usernames();
	$data = array();

	// Data for specific mentor
	if ( in_array( $username, $usernames ) ) {
		$user = \get_user_by( 'login', $username );

		if ( $user instanceof \WP_User ) {
			// Make sure we get a name
			if ( $user->display_name ) {
				$name = $user->display_name;
			} else if ( $user->nickname ) {
				$name = $user->nickname;
			} else if ( $user->first_name && $user->last_name ) {
				$name = sprintf( '%s %s', $user->first_name, $user->last_name );
			} else {
				$name = $username;
			}

			$data[ $username ] = array(
				'name'  => $name,
				'email' => $user->user_email,
			);
		}
	}

	return $data;
}

/**
 * Retrieve data for all the mentor usernames in the list.
 *
 * @return array
 */
function get_all_mentor_data() {
	if ( false !== $data = \get_site_transient( MENTORS_CACHE_KEY ) ) {
		return $data;
	}

	$usernames = get_usernames();
	$data = array();

	foreach ( $usernames as $username ) {
		$data = array_merge( $data, get_mentor_data( $username ) );
	}

	ksort( $data );

	\set_site_transient( MENTORS_CACHE_KEY, $data, DAY_IN_SECONDS );

	return $data;
}

add_action( 'wcpt_mentors_assignment_nag', __NAMESPACE__ . '\assignment_nag' );

/**
 * Send an email nag listing the current un-mentored camps.
 */
function assignment_nag() {
	$unmentored_camps = get_unmentored_camps();

	if ( empty( $unmentored_camps['yesdate'] ) && empty( $unmentored_camps['nodate'] ) ) {
		return;
	}

	$dashboard_url = add_query_arg( array(
		'post_type' => 'wordcamp',
		'page'      => 'mentors',
	), admin_url( 'edit.php' ) );

	// Render message
	ob_start(); ?>
<?php require_once( dirname( __DIR__ ) . '/views/mentors/unmentored-camps.php' ); ?>

<p><a href="<?php echo esc_url( $dashboard_url ); ?>">Mentors Dashboard &raquo;</a></p>
<?php

	$to      = array( 'support@wordcamp.org' );
	$subject = 'WordCamps without a mentor as of ' . gmdate( 'Y-m-d \T H:i:s \Z' );
	$message = ob_get_clean();
	$headers = array(
		'From:         noreply@wordcamp.org',
		'Content-Type: text/html; charset=UTF-8',
	);

	wp_mail(
		array_map( 'sanitize_email', $to ),
		esc_html( $subject ),
		wp_kses_post( $message ),
		array_map( 'esc_html', $headers )
	);
}