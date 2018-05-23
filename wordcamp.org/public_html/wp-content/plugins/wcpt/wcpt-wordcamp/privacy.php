<?php

namespace WordCamp\WCPT\Privacy;
defined( 'WPINC' ) || die();

use WP_Query;

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
	$exporters['wcpt-details'] = array(
		'exporter_friendly_name' => __( 'WordCamp Details', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\details_personal_data_exporter',
	);

	$exporters['wcpt-application'] = array(
		'exporter_friendly_name' => __( 'WordCamp Application Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\application_personal_data_exporter',
	);

	return $exporters;
}

/**
 * Finds and exports personal data associated with an email address in WCPT postmeta.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function details_personal_data_exporter( $email_address, $page ) {
	$post_query     = _get_wordcamp_posts( 'details', $email_address, $page );
	$email_keys     = _get_email_postmeta_keys();
	$data_to_export = [];

	foreach ( (array) $post_query->posts as $post ) {
		$wcpt_data_to_export = [];

		foreach ( $email_keys as $email_key => $props ) {
			$email_value = get_post_meta( $post->ID, $email_key, true );

			if ( $email_value === $email_address ) {
				$wcpt_data_to_export[] = [
					'name'  => $email_key,
					'value' => $email_value,
				];

				foreach ( $props['assoc_fields'] as $assoc_key ) {
					$assoc_value = get_post_meta( $post->ID, $assoc_key, true );

					if ( ! empty( $assoc_value ) ) {
						$wcpt_data_to_export[] = [
							'name'  => $assoc_key,
							'value' => $assoc_value,
						];
					}
				}
			}
		}

		if ( ! empty( $wcpt_data_to_export ) ) {
			$data_to_export[] = array(
				'group_id'    => 'wcpt-details',
				'group_label' => 'WordCamp Details',
				'item_id'     => "wcpt-{$post->ID}",
				'data'        => $wcpt_data_to_export,
			);
		}
	}

	$done = $post_query->max_num_pages <= $page;

	return [
		'data' => $data_to_export,
		'done' => $done,
	];
}

/**
 * Finds and exports personal data associated with an email address in a WCPT's original application data.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function application_personal_data_exporter( $email_address, $page ) {
	$post_query     = _get_wordcamp_posts( 'application', $email_address, $page );
	$data_to_export = [];

	foreach ( (array) $post_query->posts as $post ) {
		$application_data    = get_post_meta( $post->ID, '_application_data', true );
		$wcpt_data_to_export = [];

		$lead_organizer_keys = [
			'q_1079074_first_name'                       => 'First Name',
			'q_1079074_last_name'                        => 'Last Name',
			'q_1079059_email'                            => 'Email',
			'q_4236565_wporg_username'                   => 'WordPress.org Username',
			'q_4236565_slack_username'                   => 'WordPress Slack Username',
			'where_find_online'                          => 'Where can we find you online?',
			'q_1079060_add1'                             => 'Address Line 1',
			'q_1079060_add2'                             => 'Address Line 2',
			'q_1079060_city'                             => 'City',
			'q_1079060_state'                            => 'State',
			'q_1079060_zip'                              => 'Zip Code',
			'q_1079060_country'                          => 'Country',
			'q_1045947_years_using_wp'                   => 'How long have you been using WordPress?',
			'q_1068246_ways_involved'                    => 'How have you been involved in the WordPress community so far?',
			'q_1068246_ways_involved_other'              => 'How have you been involved in the WordPress community so far? (Other)',
			'q_1046032_attended_camp_before'             => 'Have you ever attended a WordCamp before?',
			'q_1046033_camps_been_to'                    => 'What WordCamps have you been to?',
			'q_1068223_hope_to_accomplish'               => 'What do you hope to accomplish by organizing a WordCamp?',
			'q_1068223_hope_to_accomplish_other'         => 'What do you hope to accomplish by organizing a WordCamp? (Other)',
			'q_1045953_role_in_meetup'                   => 'What is your role in the meetup group?',
			'q_1046038_organized_event_before'           => 'Have you ever organized an event like this before?',
			'q_1046099_describe_events'                  => 'Please give a brief description of the events you&#039;ve been involved in organizing and what your role was.',
			'q_1068188_relationship_co_organizers'       => 'What&#039;s your relationship to your co-organizers?',
			'q_1068188_relationship_co_organizers_other' => 'What&#039;s your relationship to your co-organizers? (Other)',
			'q_1068214_raise_money'                      => 'Are you confident you can raise money from local sponsors to cover the event costs?',
			'q_1079098_anything_else'                    => 'Anything else you want us to know while we&#039;re looking over your application?',
			'q_1079112_best_describes_you'               => 'Which of these best describes you?',
			'q_1079112_best_describes_you_other'         => 'Which of these best describes you? (Other)',
		];

		$co_organizer_keys = [
			'q_1068188_relationship_co_organizers'       => 'What&#039;s your relationship to your co-organizers?',
			'q_1068188_relationship_co_organizers_other' => 'What&#039;s your relationship to your co-organizers? (Other)',
			'q_1068187_co_organizer_contact_info'        => 'Please enter the names and email addresses of your co-organizers',
		];

		if ( $application_data['q_1079059_email'] === $email_address ) {
			foreach ( $lead_organizer_keys as $lkey => $llabel ) {
				if ( isset( $application_data[ $lkey ] ) && ! empty( $application_data[ $lkey ] ) ) {
					$lvalue = $application_data[ $lkey ];

					if ( is_array( $lvalue ) ) {
						$lvalue = implode( ', ', $lvalue );
					}

					$wcpt_data_to_export[] = [
						'name'  => $llabel,
						'value' => $lvalue,
					];
				}
			}
		}

		if ( preg_match( "/.*\b$email_address\b.*/", $application_data['q_1068187_co_organizer_contact_info'] ) ) {
			foreach ( $co_organizer_keys as $ckey => $clabel ) {
				if ( isset( $application_data[ $ckey ] ) && ! empty( $application_data[ $ckey ] ) ) {
					$cvalue = $application_data[ $ckey ];

					if ( is_array( $cvalue ) ) {
						$cvalue = implode( ', ', $cvalue );
					}

					$wcpt_data_to_export[] = [
						'name'  => $clabel,
						'value' => $cvalue,
					];
				}
			}
		}

		if ( ! empty( $wcpt_data_to_export ) ) {
			$data_to_export[] = array(
				'group_id'    => 'wcpt-application',
				'group_label' => 'WordCamp Application Data',
				'item_id'     => "wcpt-{$post->ID}",
				'data'        => $wcpt_data_to_export,
			);
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
	$erasers['wcpt-details'] = array(
		'exporter_friendly_name' => __( 'WordCamp Details', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\details_personal_data_eraser',
	);

	$erasers['wcpt-application'] = array(
		'exporter_friendly_name' => __( 'WordCamp Application Data', 'wordcamporg' ),
		'callback'               => __NAMESPACE__ . '\application_personal_data_eraser',
	);

	return $erasers;
}

/**
 * Finds and erases personal data associated with an email address in WCPT postmeta.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function details_personal_data_eraser( $email_address, $page ) {
	$post_query     = _get_wordcamp_posts( 'details', $email_address, $page );
	$items_removed  = false;
	$items_retained = false;
	$messages       = [];

	foreach ( (array) $post_query->posts as $post ) {
		// @todo
	}

	$done = $post_query->max_num_pages <= $page;

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => $done,
	);
}

/**
 * Finds and erases personal data associated with an email address in a WCPT's original application data.
 *
 * @param string $email_address
 * @param int    $page
 *
 * @return array
 */
function application_personal_data_eraser( $email_address, $page ) {
	$post_query     = _get_wordcamp_posts( 'application', $email_address, $page );
	$items_removed  = false;
	$items_retained = false;
	$messages       = [];

	foreach ( (array) $post_query->posts as $post ) {
		// @todo
	}

	$done = $post_query->max_num_pages <= $page;

	return array(
		'items_removed'  => $items_removed,
		'items_retained' => $items_retained,
		'messages'       => $messages,
		'done'           => $done,
	);
}

/**
 * Get the list of WCPT posts associated with a particular email address.
 *
 * @param string $query_type    `details` or `application`.
 * @param string $email_address
 * @param int    $page
 *
 * @return WP_Query
 */
function _get_wordcamp_posts( $query_type, $email_address, $page ) {
	$page   = (int) $page;
	$number = 20;

	$args = [
		'post_type'      => WCPT_POST_TYPE_ID,
		'post_status'    => 'any',
		'orderby'        => 'ID',
		'numberposts'    => - 1,
		'perm'           => 'readable',
		'posts_per_page' => $number,
		'paged'          => $page,
	];

	switch ( $query_type ) {
		case 'details' :
			$email_keys = _get_email_postmeta_keys();

			$args['meta_query'] = [
				'relation' => 'OR',
			];

			foreach ( $email_keys as $key => $props ) {
				$args['meta_query'][] = [
					'key'   => $key,
					'value' => $email_address,
				];
			}
			break;
		case 'application' :
			$args['meta_query'] = [
				'relation' => 'OR',
				[
					'key'     => '_application_data',
					'value'   => $email_address,
					'compare' => 'LIKE', // There are multiple places in the serialized array where an email address could be.
				],
			];
			break;
	}

	return new WP_Query( $args );
}

/**
 * Define the list of postmeta fields that may contain an email address, and other fields associated with each of them.
 *
 * @return array
 */
function _get_email_postmeta_keys() {
	// @todo Since the postmeta keys are also the field labels, we might need to expand this array to include translatable
	// strings for the labels.

	return [
		'Email Address' => [
			'assoc_fields' => [
				'Organizer Name',
				'WordPress.org Username',
				'Telephone',
				'Mailing Address',
			],
		],
		'Sponsor Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Sponsor Wrangler Name',
			],
		],
		'Budget Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Budget Wrangler Name',
			],
		],
		'Venue Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Venue Wrangler Name',
			],
		],
		'Speaker Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Speaker Wrangler Name',
			],
		],
		'Food/Beverage Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Food/Beverage Wrangler Name',
			],
		],
		'Swag Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Swag Wrangler Name',
			],
		],
		'Volunteer Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Volunteer Wrangler Name',
			],
		],
		'Printing Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Printing Wrangler Name',
			],
		],
		'Design Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Design Wrangler Name',
			],
		],
		'Website Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Website Wrangler Name',
			],
		],
		'Social Media/Publicity Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Social Media/Publicity Wrangler Name',
			],
		],
		'A/V Wrangler E-mail Address' => [
			'assoc_fields' => [
				'A/V Wrangler Name',
			],
		],
		'Party Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Party Wrangler Name',
			],
		],
		'Travel Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Travel Wrangler Name',
			],
		],
		'Safety Wrangler E-mail Address' => [
			'assoc_fields' => [
				'Safety Wrangler Name',
			],
		],
		'Mentor E-mail Address' => [
			'assoc_fields' => [
				'Mentor WordPress.org User Name',
				'Mentor Name',
			],
		],
	];
}
