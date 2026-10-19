<?php
/**
 * FreeScout sidebar: forum moderator notes about the sender.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

require __DIR__ . '/common.php';

/**
 * Gets the forum notes panel.
 *
 * @param object $request Request payload.
 * @return array Panel blocks.
 */
function render_forum_notes( object $request ): array {
	$user = get_user_by( 'email', get_user_email_for_email( $request ) );

	if ( ! $user || ! $user->_wporg_bbp_user_notes ) {
		return array();
	}

	$items = array();
	foreach ( $user->_wporg_bbp_user_notes as $note ) {
		// Not wp_trim_words(): it strips tags, cutting a note at "<3".
		$words = preg_split( '/\s+/', trim( (string) $note->text ) );

		$items[] = array(
			'note' => implode( ' ', array_slice( $words, 0, 15 ) ) . ( count( $words ) > 15 ? '…' : '' ),
			'meta' => array(
				array(
					'text' => gmdate( 'F j, Y', (int) strtotime( $note->date ) ),
					'url'  => 'https://wordpress.org/support/users/' . $user->user_nicename . '/',
				),
				array( 'text' => (string) $note->moderator ),
			),
		);
	}

	return array(
		array(
			'type'  => 'items',
			'items' => $items,
		),
	);
}

send_panel( render_forum_notes( get_request( basename( __FILE__ ) ) ) );
