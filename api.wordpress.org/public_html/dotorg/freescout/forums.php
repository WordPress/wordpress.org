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
 * Renders the forum notes panel.
 *
 * @param object $request Request payload.
 * @return string
 */
function render_forum_notes( object $request ): string {
	$html = '';
	$user = get_user_by( 'email', get_user_email_for_email( $request ) );

	if ( $user && $user->_wporg_bbp_user_notes ) {
		foreach ( $user->_wporg_bbp_user_notes as $note ) {
			$html .= '<p><a href="' . esc_url( 'https://wordpress.org/support/users/' . $user->user_nicename . '/' ) . '">' . esc_html( gmdate( 'F j, Y', (int) strtotime( $note->date ) ) ) . ':</a> ';
			$html .= '<em>' . wp_trim_words( esc_html( $note->text ), 15 ) . '</em>';
			$html .= ' By ' . esc_html( $note->moderator ) . '</p>';
		}
	}

	return $html;
}

send_html( render_forum_notes( get_request() ) );
