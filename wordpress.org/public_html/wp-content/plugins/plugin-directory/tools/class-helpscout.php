<?php
namespace WordPressdotorg\Plugin_Directory\Tools;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

class Helpscout {

	/**
	 * The namespace a rejected plugin's slug is wrapped in.
	 */
	const REJECTED_SLUG_REGEX = '/(^rejected-|-rejected(-\d)?$)/i';

	/**
	 * Fetch the slug a post's emails may also be recorded against.
	 *
	 * A rejected plugin's slug is wrapped to free up the original slug, so emails for
	 * it may still be recorded against the unwrapped one. That's only true for as long
	 * as no other plugin holds the unwrapped slug.
	 *
	 * @param string $post_name The post slug.
	 * @param int    $post_id   The post ID.
	 * @return string The unwrapped slug, or $post_name if it isn't this post's to claim.
	 */
	protected static function get_unwrapped_slug( $post_name, $post_id ) {
		$unwrapped = preg_replace( self::REJECTED_SLUG_REGEX, '', $post_name );
		if ( ! $unwrapped || $unwrapped === $post_name ) {
			return $post_name;
		}

		$owner = Plugin_Directory::get_plugin_post( $unwrapped );
		if ( $owner && (int) $owner->ID !== (int) $post_id ) {
			return $post_name;
		}

		return $unwrapped;
	}

	/**
	 * Fetch the list of known Helpscout emails for a given post.
	 *
	 * @param \WP_Post $post The post to fetch emails for.
	 * @return array
	 */
	public static function get_emails( $post, $filters = [] ) {
		global $wpdb;

		$limit = 999;
		if ( isset( $filters['limit'] ) ) {
			$limit = absint( $filters['limit'] );
			unset( $filters['limit'] );
		}

		// Trim off the rejected prefix/suffix.
		$slug   = self::get_unwrapped_slug( $post->post_name, $post->ID );
		$wheres = '';

		foreach ( $filters as $key => $value ) { 
			$wheres .= $wpdb->prepare( 'AND emails.%i LIKE %s ', $key, '%' . $value . '%' );
		}

		$emails = $wpdb->get_results( $wpdb->prepare(
			"SELECT emails.*
				FROM %i emails
					JOIN %i meta ON emails.id = meta.helpscout_id
				WHERE meta.meta_key = 'plugins' AND meta.meta_value IN( %s, %s )
					$wheres
				ORDER BY `created` DESC
				LIMIT %d",
			"{$wpdb->base_prefix}helpscout",
			"{$wpdb->base_prefix}helpscout_meta",
			$slug,
			$post->post_name,
			$limit
		) );

		foreach ( $emails as &$email ) {
			$email->url = 'https://secure.helpscout.net/conversation/' . $email->id . '/' . $email->number;
		}

		if ( 1 === $limit ) {
			return reset( $emails );
		}

		return $emails;
	}

	/**
	 * Monitors post updates for a slug change, and updates the associated HelpScout email meta to match the new slug.
	 *
	 * @param int $post_id The ID of the post being updated.
	 * @param WP_Post $post_after The post object after the update.
	 * @param WP_Post $post_before The post object before the update.
	 * @return void
	 */
	public static function post_updated( $post_id, $post_after, $post_before ) {
		global $wpdb;
		if (
			'plugin' !== $post_after->post_type ||
			$post_after->post_name === $post_before->post_name
		) {
			return;
		}

		$new_slug = $post_after->post_name;
		$old_slug = self::get_unwrapped_slug( $post_before->post_name, $post_id );

		$wpdb->query( $wpdb->prepare(
			"UPDATE %i meta
				SET meta.meta_value = %s
				WHERE meta.meta_key = 'plugins' AND meta.meta_value IN( %s, %s )",
			"{$wpdb->base_prefix}helpscout_meta",
			$new_slug,
			$old_slug,
			$post_before->post_name
		) );
	}

	/**
	 * Display the Helpscout emails in the admin metabox.
	 */
	public static function admin_metabox_display() {
		$post = get_post();

		// If the slug is not set, we can't query HelpScout.
		if ( ! $post->post_name ) {
			echo 'Invalid Slug, cannot query emails.';
			return;
		}

		$emails = self::get_emails( $post );

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
