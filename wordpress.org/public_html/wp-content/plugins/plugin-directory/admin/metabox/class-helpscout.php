<?php
/**
 * Email list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Email list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class HelpScout {
	public static function display() {
		$post = get_post();

		// If the slug is not set, we can't query HelpScout.
		if ( ! $post->post_name ) {
			echo 'Invalid Slug, cannot query emails.';
			return;
		}

		$emails = Tools::get_helpscout_emails( $post );

		echo '<table class="widefat striped helpscout-emails">';
		echo '<thead>
			<tr>
				<th>Subject</th>
				<th>Last Modified</th>
				<th>Status</th>
				<th>Who</th>
			</tr>
		</thead>';

		if ( ! $emails ) {
			echo '<tr><td colspan="4" class="no-items">No emails found.</td></tr>';
		}

		foreach ( $emails as $email ) {
			$subject = trim( str_ireplace( '[WordPress Plugin Directory]', '', $email->subject ) );

			printf(
				"<tr>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
				</tr>\n",
				sprintf(
					'<a href="%s" title="%s">%s</a>',
					esc_url( $email->url ),
					esc_attr( $email->preview ),
					esc_html( $subject )
				),
				sprintf(
					'<span title="%s">%s ago</span>',
					esc_attr(
						sprintf(
							'Created %s, Last Modified %s',
							$email->created,
							$email->modified
						)
					),
					esc_html( human_time_diff( max( strtotime( $email->created ), strtotime( $email->modified ) ) ) )
				),
				esc_html( ucwords( $email->status ) ),
				esc_html( explode( '<', $email->email )[0] ),
			);
		}

		echo '</table>';
	}

}
