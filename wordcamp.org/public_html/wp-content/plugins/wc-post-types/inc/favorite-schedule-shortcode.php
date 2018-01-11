<?php

/**
 * [schedule] shortcode building blocks and favourite session picker support.
 */

defined( 'WPINC' ) || die();

/**
 * Return an associative array of term_id -> term object mapping for all selected tracks.
 *
 * In case of 'all' is used as a value for $selected_tracks, information for all available tracks
 * gets returned.
 *
 * @param string $selected_tracks Comma-separated list of tracks to display or 'all'.
 *
 * @return array Associative array of terms with term_id as the key.
 */
function get_schedule_tracks( $selected_tracks ) {
	$tracks = array();
	if ( 'all' === $selected_tracks ) {
		// Include all tracks.
		$tracks = get_terms( 'wcb_track' );
	} else {
		// Loop through given tracks and look for terms.
		$terms = array_map( 'trim', explode( ',', $selected_tracks ) );

		foreach ( $terms as $term_slug ) {
			$term = get_term_by( 'slug', $term_slug, 'wcb_track' );
			if ( $term ) {
				$tracks[ $term->term_id ] = $term;
			}
		}
	}

	return $tracks;
}

/**
 * Return a time-sorted associative array mapping timestamp -> track_id -> session id.
 *
 * @param string $schedule_date               Date for which the sessions should be retrieved.
 * @param bool   $tracks_explicitly_specified True if tracks were explicitly specified in the shortcode,
 *                                            false otherwise.
 * @param array  $tracks                      Array of terms for tracks from get_schedule_tracks().
 *
 * @return array Associative array of session ids by time and track.
 */
function get_schedule_sessions( $schedule_date, $tracks_explicitly_specified, $tracks ) {
	$query_args = array(
		'post_type'      => 'wcb_session',
		'posts_per_page' => - 1,
		'meta_query'     => array(
			'relation' => 'AND',
			array(
				'key'     => '_wcpt_session_time',
				'compare' => 'EXISTS',
			),
		),
	);

	if ( $schedule_date && strtotime( $schedule_date ) ) {
		$query_args['meta_query'][] = array(
			'key'     => '_wcpt_session_time',
			'value'   => array(
				strtotime( $schedule_date ),
				strtotime( $schedule_date . ' +1 day' ),
			),
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		);
	}

	if ( $tracks_explicitly_specified ) {
		// If tracks were provided, restrict the lookup in WP_Query.
		if ( ! empty( $tracks ) ) {
			$query_args['tax_query'][] = array(
				'taxonomy' => 'wcb_track',
				'field'    => 'id',
				'terms'    => array_values( wp_list_pluck( $tracks, 'term_id' ) ),
			);
		}
	}

	// Loop through all sessions and assign them into the formatted
	// $sessions array: $sessions[ $time ][ $track ] = $session_id
	// Use 0 as the track ID if no tracks exist.
	$sessions       = array();
	$sessions_query = new WP_Query( $query_args );

	foreach ( $sessions_query->posts as $session ) {
		$time  = absint( get_post_meta( $session->ID, '_wcpt_session_time', true ) );
		$terms = get_the_terms( $session->ID, 'wcb_track' );

		if ( ! isset( $sessions[ $time ] ) ) {
			$sessions[ $time ] = array();
		}

		if ( empty( $terms ) ) {
			$sessions[ $time ][0] = $session->ID;
		} else {
			foreach ( $terms as $track ) {
				$sessions[ $time ][ $track->term_id ] = $session->ID;
			}
		}
	}

	// Sort all sessions by their key (timestamp).
	ksort( $sessions );

	return $sessions;
}

/**
 * Return an array of columns identified by term ids to be used for schedule table.
 *
 * @param array $tracks                      Array of terms for tracks from get_schedule_tracks().
 * @param array $sessions                    Array of sessions from get_schedule_sessions().
 * @param bool  $tracks_explicitly_specified True if tracks were explicitly specified in the shortcode,
 *                                           false otherwise.
 *
 * @return array Array of columns identified by term ids.
 */
function get_schedule_columns( $tracks, $sessions, $tracks_explicitly_specified ) {
	$columns = array();

	// Use tracks to form the columns.
	if ( $tracks ) {
		foreach ( $tracks as $track ) {
			$columns[ $track->term_id ] = $track->term_id;
		}
	} else {
		$columns[0] = 0;
	}

	// Remove empty columns unless tracks have been explicitly specified.
	if ( ! $tracks_explicitly_specified ) {
		$used_terms = array();

		foreach ( $sessions as $time => $entry ) {
			if ( is_array( $entry ) ) {
				foreach ( $entry as $term_id => $session_id ) {
					$used_terms[ $term_id ] = $term_id;
				}
			}
		}

		$columns = array_intersect( $columns, $used_terms );
		unset( $used_terms );
	}

	return $columns;
}

/**
 * Update and preprocess input attributes for [schedule] shortcode.
 *
 * @param array $attr Array of attributes from shortcode.
 *
 * @return array Array of attributes, after preprocessing.
 */
function preprocess_schedule_attributes( $attr ) {
	$attr = shortcode_atts(
		array(
			'date'         => null,
			'tracks'       => 'all',
			'speaker_link' => 'anchor',    // anchor|wporg|permalink|none
			'session_link' => 'permalink', // permalink|anchor|none
		), $attr
	);

	foreach ( array( 'tracks', 'speaker_link', 'session_link' ) as $key_for_case_sensitive_value ) {
		$attr[ $key_for_case_sensitive_value ] = strtolower( $attr[ $key_for_case_sensitive_value ] );
	}

	if ( ! in_array( $attr['speaker_link'], array( 'anchor', 'wporg', 'permalink', 'none' ), true ) ) {
		$attr['speaker_link'] = 'anchor';
	}

	if ( ! in_array( $attr['session_link'], array( 'permalink', 'anchor', 'none' ), true ) ) {
		$attr['session_link'] = 'permalink';
	}

	return $attr;
}

/**
 * Return plain text list of sessions marked as favourite sessions.
 *
 * Format of each list item:
 * Time of session | Session title [by Speaker] | Track name(s).
 *
 * @param array $sessions_rev        Array of sessions with reversed subarray track_id->session_id.
 * @param array $fav_sessions_lookup Mapping session _id -> 1 for favourite sessions.
 *
 * @return string List of sessions.
 */
function generate_plaintext_fav_sessions( $sessions_rev, $fav_sessions_lookup ) {
	$sessions_text = '';

	// timestamp -> session_id -> track_id.
	foreach ( $sessions_rev as $timestamp => $sessions_at_time ) {
		foreach ( $sessions_at_time as $session_id => $track_ids ) {
			// Skip sessions which are not marked favourite.
			if ( ! isset( $fav_sessions_lookup[ $session_id ] ) ) {
				continue;
			}

			$session              = get_post( $session_id );
			$session_title        = apply_filters( 'the_title', $session->post_title );
			$session_tracks       = get_the_terms( $session_id, 'wcb_track' );
			$session_track_titles = is_array( $session_tracks ) ? implode( ', ', wp_list_pluck( $session_tracks, 'name' ) ) : '';

			$speakers     = array();
			$speakers_ids = array_map( 'absint', (array) get_post_meta( $session_id, '_wcpt_speaker_id' ) );
			if ( ! empty( $speakers_ids ) ) {
				$speakers = get_posts( array(
					'post_type'      => 'wcb_speaker',
					'posts_per_page' => - 1,
					'post__in'       => $speakers_ids,
				) );
			}

			$speakers_names = array();
			foreach ( $speakers as $speaker ) {
				$speaker_name     = apply_filters( 'the_title', $speaker->post_title );
				$speakers_names[] = $speaker_name;
			}

			// Line format: Time of session | Session title [by Speaker] | Track name(s).
			$sessions_text .= date( get_option( 'time_format' ), $timestamp );
			$sessions_text .= ' | ';
			$sessions_text .= $session_title;
			if ( count( $speakers_names ) > 0 ) {
				$sessions_text .= _x( ' by ', 'Speaker for the session', 'wordcamporg' ) . implode( ', ', $speakers_names );
			}
			$sessions_text .= ' | ';
			$sessions_text .= $session_track_titles;
			$sessions_text .= "\n";
		}
	}

	return $sessions_text;

}

/**
 * Return array of dates for which there are sessions in the provided array.
 *
 * @param array  $sessions    Array of sessions from get_schedule_sessions().
 * @param string $date_format Date format string (same format as php).
 *
 * @return array Array of dates for WordCamp formatted according to date_format string.
 */
function get_sessions_dates( $sessions, $date_format ) {
	$session_timestamps = array_keys( $sessions );

	$session_dates = array_map(
		function( $timestamp ) use ( $date_format ) {
			return date( $date_format, $timestamp );
		},
		$session_timestamps
	);

	return array_unique( $session_dates );
}

/**
 * Return true if any of the sessions from $session_rev is in $fav_session_ids,
 * false otherwise.
 *
 * @param array $fav_session_ids Array with favourite sessions as keys.
 * @param array $sessions_rev    Array of sessions from flip_sessions_subarrays().
 *
 * @return bool true if there is any intersection, false otherwise.
 */
function includes_fav_session( $fav_session_ids, $sessions_rev ) {
	foreach ( $sessions_rev as $timestamp => $session_id_subarray ) {
		foreach ( $session_id_subarray as $session_id => $_ ) {
			if ( isset( $fav_session_ids[ $session_id ] ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Return array of sessions with reverted subarrays, i.e. transformed from
 * timestamp -> track_id -> session_id into
 * timestamp -> session_id -> [track_id1, track_id2, ...]
 *
 * @param array $sessions An array of sessions from get_schedule_sessions().
 *
 * @return array Array with format timestamp -> session_id -> [track_id1, track_id2, ...].
 */
function flip_sessions_subarrays( $sessions ) {
	$sessions_reversed = array();

	foreach ( $sessions as $timestamp => $sessions_at_time ) {
		foreach ( $sessions_at_time as $track_id => $session_id ) {
			$sessions_reversed[ $timestamp ][ $session_id ][] = $track_id;
		}
	}

	return $sessions_reversed;
}

/**
 * Return plain text email message body for sharing favourite sessions email.
 *
 * @param string $wordcamp_name       WordCamp name to be used in the email.
 * @param array  $fav_sessions_lookup Mapping session _id -> 1 for favourite sessions.
 *
 * @return string                     Plain text body of the email.
 */
function generate_email_body( $wordcamp_name, $fav_sessions_lookup ) {
	$date_format                 = get_option( 'date_format' );
	$tracks                      = get_schedule_tracks( 'all' );
	$tracks_explicitly_specified = false; // include all tracks in the email.
	$sessions                    = get_schedule_sessions( null, $tracks_explicitly_specified, $tracks );
	$sessions_dates              = get_sessions_dates( $sessions, $date_format );

	// Convert timestamp -> track_id -> session_id to timestamp -> session_id -> [track_id1, ...].
	$sessions_reversed = flip_sessions_subarrays( $sessions );

	$email_message = $wordcamp_name . "\n\n";

	// Create list of sessions for each day.
	foreach ( $sessions_dates as $current_day ) {
		// Filter only the sessions for the 'current' day.
		$sessions_for_current_day = array_filter(
			$sessions_reversed,
			function( $date_ ) use ( $current_day, $date_format ) {
				return date( $date_format, $date_ ) === $current_day;
			},
			ARRAY_FILTER_USE_KEY
		);

		$email_message .= $current_day . "\n";

		// Skip days when there's no session marked as favourite.
		if ( ! includes_fav_session( $fav_sessions_lookup, $sessions_for_current_day ) ) {
			$email_message .= "\n";
			continue;
		}

		$email_message .= generate_plaintext_fav_sessions( $sessions_for_current_day, $fav_sessions_lookup );
		$email_message .= "\n\n";
	}

	return $email_message;
}

/**
 * Return true if the email favourite sessions feature should be disabled,
 * false otherwise.
 *
 * Kill switch for sharing schedule over email -- both for REST API endpoint and UI
 * in [schedule] shortcode.
 *
 * @return bool true if email functionality should be disabled, false otherwise.
 */
function email_fav_sessions_disabled() {
	return false;
}

/**
 * Send favourite sessions email to address specified in the REST request.
 *
 * REST API handler for 'wc-post-types/v1/email-fav-sessions' endpoint.
 *
 * @param WP_REST_Request $request REST API Request object.
 *
 * @return WP_REST_Response|WP_Error
 */
function send_favourite_sessions_email( WP_REST_Request $request ) {
	// There's no need to check intention or authorization, since this is meant to be available to
	// unauthenticated visitors.

	if ( email_fav_sessions_disabled() ) {
		return new WP_REST_Response(
			array(
				'message' => esc_html__( 'Email functionality disabled.', 'wordcamporg' ),
			), 200
		);
	}

	$params = $request->get_params();
	// Input sanitized by REST controller.
	$email_address = $params['email-address'];
	$fav_sessions  = $params['session-list'];

	// Don't send the email if no sessions were marked as favourite.
	if ( count( explode( ',', $fav_sessions ) ) === 0 ) {
		return new WP_Error(
			'fav_sessions_no_sessions',
			esc_html__( 'No sessions selected.', 'wordcamporg' ),
			array(
				'status' => 400,
			)
		);
	}

	$fav_sessions_lookup = array_fill_keys( explode( ',', $fav_sessions ), 1 );

	$wordcamp_name = get_wordcamp_name();

	$headers[] = 'From: ' . $wordcamp_name . ' <do-not-reply@wordcamp.org>';
	$headers[] = 'Content-Type: text/plain; charset=' . get_bloginfo( 'charset' );

	$subject = sprintf( __( 'My favorite sessions for %s', 'wordcamporg' ), $wordcamp_name );
	$message = generate_email_body( $wordcamp_name, $fav_sessions_lookup );

	if ( wp_mail( $email_address, $subject, $message, $headers ) ) {
		return new WP_REST_Response(
			array(
				'message' => esc_html__( 'Email sent successfully to ', 'wordcamporg' ) . " $email_address.",
			), 200
		);
	}

	// Email was not sent successfully.
	return new WP_Error(
		'fav_sessions_email_failed',
		esc_html__( 'Favourite sessions email failed.', 'wordcamporg' ),
		array(
			'status' => 500,
		)
	);
}
