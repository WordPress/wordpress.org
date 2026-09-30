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
		$html .= '<ul class="wporg-sidebar-items">';

		foreach ( $user->_wporg_bbp_user_notes as $note ) {
			$html .= sprintf(
				'<li class="wporg-sidebar-item"><p class="wporg-sidebar-note">%s</p><div class="wporg-sidebar-item-meta"><a href="%s">%s</a> · %s</div></li>',
				wp_trim_words( esc_html( $note->text ), 15 ),
				esc_url( 'https://wordpress.org/support/users/' . $user->user_nicename . '/' ),
				esc_html( gmdate( 'F j, Y', (int) strtotime( $note->date ) ) ),
				esc_html( $note->moderator )
			);
		}

		$html .= '</ul>';
	}

	return $html;
}

send_html( render_forum_notes( get_request() ) );
