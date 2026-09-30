<?php
/**
 * FreeScout sidebar: the sender's personal data export and erasure requests.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

// DPO site.
$wp_init_host = 'https://wordpress.org/';
require __DIR__ . '/common.php';

/**
 * Renders the privacy requests panel.
 *
 * @param object $request Request payload.
 * @return string
 */
function render_privacy_requests( object $request ): string {
	if ( empty( $request->sender->email ) ) {
		return '<p class="wporg-sidebar-empty">No email found</p>';
	}

	// This needs to run as a user.
	wp_set_current_user( get_user_by( 'login', 'wordpressdotorg' )->ID );

	$email = get_user_email_for_email( $request );
	$html  = sprintf(
		'<ul class="wporg-sidebar-links"><li><a href="%s">Search erasures</a></li><li><a href="%s">Search exports</a></li></ul>',
		esc_url( add_query_arg( 's', rawurlencode( $email ), admin_url( 'erase-personal-data.php' ) ) ),
		esc_url( add_query_arg( 's', rawurlencode( $email ), admin_url( 'export-personal-data.php' ) ) )
	);

	// The sender may have filed requests from their own address, not the account's.
	$emails = array_unique( array_map( 'strtolower', array( $email, (string) $request->sender->email ) ) );

	$request_ids = array();
	foreach ( $emails as $requester_email ) {
		$request_ids = array_merge(
			$request_ids,
			get_posts(
				array(
					'post_type'      => 'user_request',
					'title'          => $requester_email,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			)
		);
	}

	// Newest first.
	$request_ids = array_unique( $request_ids );
	rsort( $request_ids );

	if ( ! $request_ids ) {
		return $html . '<p class="wporg-sidebar-empty">No requests found.</p>';
	}

	$html .= '<ul class="wporg-sidebar-items">';

	foreach ( $request_ids as $request_id ) {
		$user_request = wp_get_user_request( $request_id );
		$dates        = array();

		foreach ( array( 'created', 'modified', 'confirmed', 'completed' ) as $field ) {
			$timestamp = $user_request->{"{$field}_timestamp"};
			if ( ! $timestamp ) {
				continue;
			}

			$dates[ $timestamp ] = sprintf( '%s: %s', ucwords( $field ), gmdate( 'Y-m-d H:i:s', (int) $timestamp ) );
		}

		$type = match ( $user_request->action_name ) {
			'export_personal_data' => 'Export',
			'remove_personal_data' => 'Erasure',
			default                => ucwords( str_replace( '_', ' ', $user_request->action_name ) ),
		};

		$tone = match ( $user_request->status ) {
			'request-completed' => 'success',
			'request-failed'    => 'error',
			default             => 'warning',
		};

		$html .= sprintf(
			'<li class="wporg-sidebar-item" title="%s"><span class="wporg-sidebar-item-title">%s</span> %s<div class="wporg-sidebar-item-meta">%s</div></li>',
			esc_attr( implode( ', ', $dates ) ),
			esc_html( $type ),
			render_badge( get_post_status_object( $user_request->status )->label, $tone ),
			esc_html( gmdate( 'Y-m-d', (int) min( array_keys( $dates ) ) ) )
		);
	}

	return $html . '</ul>';
}

send_html( render_privacy_requests( get_request() ) );
