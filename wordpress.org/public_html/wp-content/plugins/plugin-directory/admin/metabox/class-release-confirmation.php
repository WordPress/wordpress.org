<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * Plugin Release Confirmation controls.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Release_Confirmation {
	static function display() {
		global $post;

		$confirmations_required = $post->release_confirmation_enabled;
		$confirmed_releases     = get_post_meta( $post->ID, 'confirmed_releases', true ) ?: [];

		echo 'Release Confirmation: <strong>' . ( $confirmations_required ? 'Enabled' : 'Disabled' ) . '</strong>';

		if ( ! $confirmed_releaes ) {
			return;
		}

		echo '<table class="widefat">
		<thead>
			<tr>
				<th>Tag</th>
				<th>Date</th>
				<th>Committer</th>
				<th>Approval</th>
				<th>Override</th>
		</thead>';

		foreach ( $confirmed_releases as $tag => $data ) {
			$data['confirmations_required'] = $confirmations_required;

			printf(
				'<tr>
					<td>%s</td>
					<td title="%s">%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
				</tr>',
				esc_html( $tag ),
				esc_attr( gmdate( 'Y-m-d H:i:s', $data['date'] ) ),
				esc_html( human_time_diff( $data['date'] ) ),
				esc_html( $data['committer'] ),
				self::get_approval_text( $data ),
				self::get_actions( $data )
			);
		}
		echo '</table>';
	}

	protected static function get_approval_text( $data ) {
		if ( ! $data['confirmations_required'] ) {
			return 'Not required.';
		}

		return count( $data['confirmations'] ?? [] ) . '/' . $data['confirmations_required'];
	}

	protected static function get_actions( $data ) {
		return '<button class="button button-primary override-approve-release">Approve</button>' . '&nbsp' .
			'<button class="button button-secondary override-deny-release">Deny</button>';
	}
}
