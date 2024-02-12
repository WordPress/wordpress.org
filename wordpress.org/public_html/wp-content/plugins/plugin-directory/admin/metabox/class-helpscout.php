<?php
/**
 * Email list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Email list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class HelpScout {
	public static function display() {
		global $wpdb;
		$post = get_post();

		// Trim off the rejected prefix/suffix.
		$slug   = preg_replace( '/(^rejected-|-rejected(-\d)?$)/i', '', $post->post_name );

		// If the slug is not set, we can't query HelpScout.
		if ( ! $post->post_name || ! $slug ) {
			echo 'Invalid Slug, cannot query emails.';
			return;
		}

		$emails = $wpdb->get_results( $wpdb->prepare(
			"SELECT emails.*
				FROM %i emails
					JOIN %i meta ON emails.id = meta.helpscout_id
				WHERE meta.meta_key = 'plugins' AND meta.meta_value IN( %s, %s )
				ORDER BY `created` DESC",
			"{$wpdb->base_prefix}helpscout",
			"{$wpdb->base_prefix}helpscout_meta",
			$slug,
			$post->post_name
		) );

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
					esc_url( 'https://secure.helpscout.net/conversation/' . $email->id . '/' . $email->number ),
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
