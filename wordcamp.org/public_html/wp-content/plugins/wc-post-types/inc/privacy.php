<?php
/**
 * Functions for privacy, data export, and erasure.
 *
 * @package WordCamp\Post_Types\Privacy
 */

namespace WordCamp\Post_Types\Privacy;

defined( 'WPINC' ) || die();

use WP_Query, WP_User, WP_Error;

add_filter( 'wp_privacy_personal_data_exporters', __NAMESPACE__ . '\register_personal_data_exporters' );
//add_filter( 'wp_privacy_personal_data_erasers', __NAMESPACE__ . '\register_personal_data_erasers' );

/**
 * Registers the personal data exporter for each WordCamp post type.
 *
 * @param array $exporters
 *
 * @return array
 */
function register_personal_data_exporters( $exporters ) {
	$exporters['wcb_speaker'] = array(
		'exporter_friendly_name' => __( 'WordCamp Speaker Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\speaker_personal_data_exporter',
	);

	$exporters['wcb_sponsor'] = array(
		'exporter_friendly_name' => __( 'WordCamp Sponsor Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\sponsor_personal_data_exporter',
	);

	$exporters['wcb_organizer'] = array(
		'exporter_friendly_name' => __( 'WordCamp Organizer Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\organizer_personal_data_exporter',
	);

	return $exporters;
}

/**
 * Finds and exports personal data in the Speaker post type.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function speaker_personal_data_exporter( $email_address, $page ) {
	$props_to_export = [
		'post_title'         => __( 'Speaker Name', 'wordcamporg' ),
		'post_content'       => __( 'Speaker Bio', 'wordcamporg' ),
		'_wcb_speaker_email' => __( 'Gravatar Email', 'wordcamporg' ),
		'_wcpt_user_id'      => __( 'WordPress.org Username', 'wordcamporg' ),
	];

	return _personal_data_exporter( 'wcb_speaker', $props_to_export, $email_address, $page );
}

/**
 * Finds and exports personal data in the Sponsor post type.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function sponsor_personal_data_exporter( $email_address, $page ) {
	$props_to_export = [
		'_wcpt_sponsor_company_name'  => __( 'Company Name', 'wordcamporg' ),
		'_wcpt_sponsor_first_name'    => __( 'First Name', 'wordcamporg' ),
		'_wcpt_sponsor_last_name'     => __( 'Last Name', 'wordcamporg' ),
		'_wcpt_sponsor_email_address' => __( 'Email Address', 'wordcamporg' ),
		'_wcpt_sponsor_phone_number'  => __( 'Phone Number', 'wordcamporg' ),
	];

	return _personal_data_exporter( 'wcb_sponsor', $props_to_export, $email_address, $page );
}

/**
 * Finds and exports personal data in the Organizer post type.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function organizer_personal_data_exporter( $email_address, $page ) {
	$props_to_export = [
		'post_title'    => __( 'Organizer Name', 'wordcamporg' ),
		'_wcpt_user_id' => __( 'WordPress.org Username', 'wordcamporg' ),
	];

	return _personal_data_exporter( 'wcb_organizer', $props_to_export, $email_address, $page );
}

/**
 * Finds and exports personal data in a particular post type, for a particular email address.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function _personal_data_exporter( $post_type, array $props_to_export, $email_address, $page ) {
	$page = (int) $page;

	$exporters   = apply_filters( 'wp_privacy_personal_data_exporters', [] );
	$group_label = $exporters[ $post_type ]['exporter_friendly_name'] ?: sprintf( __( '%s Data', 'wordcamporg' ), $post_type );

	$data_to_export = array();

	$post_query = get_wc_posts( $post_type, $email_address, $page );

	if ( is_wp_error( $post_query ) ) {
		return [
			'data' => [],
			'done' => true,
		];
	}

	foreach ( (array) $post_query->posts as $post ) {
		$post_data_to_export = [];

		foreach ( $props_to_export as $key => $label ) {
			if ( in_array( $key, [ 'post_title', 'post_content' ], true ) ) {
				$value = $post->$key;
			} else {
				$value = get_post_meta( $post->ID, $key, true );
			}

			if ( '_wcpt_user_id' === $key ) {
				$user = get_user_by( 'id', $value );

				if ( $user ) {
					$value = $user->user_login;
				} else {
					$value = false;
				}
			}

			if ( ! empty( $value ) ) {
				$post_data_to_export[] = [
					'name'  => $label,
					'value' => $value,
				];
			}
		}

		if ( ! empty( $post_data_to_export ) ) {
			$data_to_export[] = [
				'group_id'    => $post_type,
				'group_label' => $group_label,
				'item_id'     => "{$post_type}-{$post->ID}",
				'data'        => $post_data_to_export,
			];
		}
	}

	$done = $post_query->max_num_pages <= $page;

	return [
		'data' => $data_to_export,
		'done' => $done,
	];
}

/**
 * Registers the personal data eraser for each WordCamp post type.
 *
 * @param array $erasers
 *
 * @return array
 */
function register_personal_data_erasers( $erasers ) {
	$erasers['wcb_speaker'] = array(
		'exporter_friendly_name' => __( 'WordCamp Speaker Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\speaker_personal_data_eraser',
	);

	$erasers['wcb_sponsor'] = array(
		'exporter_friendly_name' => __( 'WordCamp Sponsor Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\sponsor_personal_data_eraser',
	);

	$erasers['wcb_organizer'] = array(
		'exporter_friendly_name' => __( 'WordCamp Organizer Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\organizer_personal_data_eraser',
	);

	return $erasers;
}


function speaker_personal_data_eraser( $email_address, $page ) {
	// @todo
}


function sponsor_personal_data_eraser( $email_address, $page ) {
	// @todo
}


function organizer_personal_data_eraser( $email_address, $page ) {
	// @todo
}


/**
 * Get the list of a particular post type related to a particular email address.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return WP_Query|WP_Error
 */
function get_wc_posts( $post_type, $email_address, $page ) {
	$number = 20;

	$query_args = [
		'posts_per_page' => $number,
		'paged'          => $page,
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'orderby'        => 'ID',
		'order'          => 'ASC',
	];

	switch ( $post_type ) {
		case 'wcb_speaker' :
			$meta_query = [
				[
					'key'   => '_wcb_speaker_email',
					'value' => $email_address,
				],
			];

			$user = get_user_by( 'email', $email_address );

			if ( $user instanceof WP_User ) {
				$meta_query[] = [
					[
						'key'   => '_wcpt_user_id',
						'value' => $user->ID,
						'type'  => 'NUMERIC',
					],
				];

				$meta_query['relation'] = 'OR';
			}

			$query_args['meta_query'] = $meta_query;
			break;
		case 'wcb_sponsor' :
			$meta_query = [
				[
					'key'   => '_wcpt_sponsor_email_address',
					'value' => $email_address,
				],
			];

			$query_args['meta_query'] = $meta_query;
			break;
		case 'wcb_organizer' :
			$user = get_user_by( 'email', $email_address );

			if ( $user instanceof WP_User ) {
				$meta_query = [
					[
						'key'   => '_wcpt_user_id',
						'value' => $user->ID,
						'type'  => 'NUMERIC',
					],
				];

				$query_args['meta_query'] = $meta_query;
			} else {
				$query_args = [];
			}
			break;
	}

	if ( empty( $query_args ) ) {
		return new WP_Error();
	}

	return new WP_Query( $query_args );
}
