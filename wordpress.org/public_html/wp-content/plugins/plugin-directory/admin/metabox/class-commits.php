<?php
/**
 * Revision list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */

namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Revision list for the plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Commits {
	/**
	 * Number of commits to show.
	 */
	const REVS_TO_SHOW = 25;

	/**
	 * Displays links to the last 50 commits for the plugin.
	 */
	public static function display() {
		global $wpdb;
		$post = get_post();

		$changes = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM trac_plugins WHERE `slug` = %s AND category = 'changeset' ORDER BY `pubdate` DESC LIMIT %d",
			$post->post_name,
			self::REVS_TO_SHOW
		) );

		echo '<table class="widefat changesets">';
		echo '<thead>
			<tr>
				<th>Author</th>
				<th>When</th>
				<th>Message</th>
				<th>Actions</th>
			</tr>
		</thead>';

		foreach ( $changes as $i => $change ) {
			$actions = [];
			$user    = get_user_by( 'user_login', $change->username );
			$actions = apply_filters( 'plugin_directory_admin_commits_actions', $actions, $change, $changes );

			printf(
				"<tr>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
				</tr>\n",
				sprintf(
					'<a href="https://profiles.wordpress.org/%s/">%s</a>',
					$user->user_nicename ?? $change->username,
					$user->user_login ?? $change->username
				),
				esc_html( $change->pubdate ),
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( $change->link ),
					esc_html( $change->title )
				),
				implode( ' ', $actions )
			);
		}

		echo '</table>';
		printf(
			'<small>Showing the last %d revisions</small>',
			self::REVS_TO_SHOW
		);
	}

}
