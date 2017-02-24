<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Author Card admin metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Author_Card {
	/**
	 * Displays information about the author of the current plugin.
	 */
	public static function display() {
		global $wpdb;

		add_action( 'wporg_usercards_after_content', array(
			__NAMESPACE__ . '\Author_Card',
			'show_warning_flags'
		), 10, 6 );

		$post   = get_post();
		$author = get_user_by( 'id', $post->post_author );

		$author_commit  = Tools::get_users_write_access_plugins( $author );
		$author_plugins = get_posts( array(
			'author'       => $author->ID,
			'post_type'    => 'plugin',
			'post__not_in' => array( $post->ID ),
		) );
		$all_plugins = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_name IN ('" . implode( "', '", array_merge( $author_commit, wp_list_pluck( $author_plugins, 'post_name' ) ) ) . "')" );
		?>
		<p class="profile">
			<?php echo get_avatar( $author->ID, 48 ); ?>
			<span class="profile-details">
				<strong><a href="//profiles.wordpress.org/<?php echo $author->user_nicename; ?>"><?php echo $author->user_login; ?></a></strong>
				<?php
					$author_links = array(
						'<a href="//make.wordpress.org/pluginrepo/?s=' . urlencode( esc_attr( $author->user_nicename ) ) . '" title="Click to search Pluginrepo P2 for mention of this author">P2</a>',
						'<a href="https://supportpress.wordpress.org/plugins/?q=' . urlencode( esc_attr( $author->user_nicename ) ) . '&status=&todo=Search+%C2%BB" title="Click to search Pluginrepo SupportPress for mention of this author">SP</a>',
					);
					vprintf( '<span class="profile-sp-link">[ %s | %s ]</span>', $author_links );
				?>

				<span class="profile-links">
					<a href="//profiles.wordpress.org/<?php echo $author->user_nicename; ?>">profile</a> |
					<a href="//wordpress.org/support/users/<?php echo $author->user_nicename; ?>">support</a>
				</span>
				<span class="profile-email">
					&lt;<?php echo $author->user_email; ?>&gt;
					<span class="profile-sp-link">[ <a href="https://supportpress.wordpress.org/plugins/?sender=<?php echo esc_attr( $author->user_email ); ?>&status=&todo=Search" title="Click to search Pluginrepo SupportPress for emails sent to/from this email address">SP</a> ]</span>
				</span>
				<span class="profile-join">
					Joined <?php echo human_time_diff( strtotime( $author->user_registered ) ); ?> ago (<?php echo date( 'Y-M-d', strtotime( $author->user_registered ) ); ?>)
				</span>
			</span>
		</p>
		<?php if ( ! empty( $author->user_url ) ) : ?>
			<p class="profile-url">
				Author URL: <a href="http://href.li?<?php echo esc_url( $author->user_url ); ?>"><?php echo esc_html( $author->user_url ); ?></a>
			</p>
		<?php
			endif;

		if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
			$user = new \WP_User( $author, '', WPORG_SUPPORT_FORUMS_BLOGID );
			if ( ! empty( $user->allcaps['bbp_blocked'] ) ) 
				echo '<p>This user is: <strong><span title="User is banned from logging into WordPress.org">banned</span></strong></p>';
		}

		$post_ids = get_posts( array(
			'fields'         => 'ids',
			'post_type'      => 'plugin',
			'post_status'    => 'any',
			'author'         => $author->ID,
			'meta_key'       => '_author_ip',
			'posts_per_page' => -1,
		) );

		$user_ips = array_unique( array_map( function( $post_id ) {
			return get_post_meta( $post_id, '_author_ip', true );
		}, $post_ids ) );

		if ( $user_ips ) :
			sort( $user_ips, SORT_NUMERIC );

			printf( '<p>IPs : %s</p>', implode( ', ', array_map( array( __NAMESPACE__ . '\Author_Card', 'link_ip' ), $user_ips ) ) );
		endif;

		if ( $author->user_pass == '~~~' ) : ?>
			<p><strong>Has not logged in since we reset passwords in June 2011</strong></p>
		<?php endif; ?>
		<div class="profile-plugins">
			<?php
			if ( empty( $author_commit ) && empty( $author_plugins ) ) {
				echo 'Not a developer on any plugin.';
			} else {
				echo '<strong>' . sprintf( _n( '%d plugin:', '%d plugins:', count( $all_plugins ), 'wporg-plugins' ), count( $all_plugins ) ) . '</strong>';

				echo '<ul>';
				foreach ( $all_plugins as $plugin ) {
					echo '<li>';
					$note    = false;
					$extra   = '';
					$classes = $tooltips = array();

					if ( in_array( $plugin->post_name, wp_list_pluck( $author_plugins, 'post_name' ) ) ) {
						$tooltips[] = 'This user submitted this plugin.';
						$classes[]  = 'plugin-owner';
						if ( ! in_array( $plugin->post_name, $author_commit ) ) {
							$note       = true;
							$tooltips[] = 'The user is not a current committer.';
						}
					}

					$plugin_slug = $plugin->post_name;
					if ( in_array( $plugin->post_status, array( 'draft', 'pending' ) ) ) {
						$extra .= ' (requested ' . human_time_diff( strtotime( $plugin->topic_start_time ) ) . ' ago)';
						$tooltips[] = 'Requested, remains unapproved.';
						$classes[]  = 'profile-plugin-requested';

					} elseif ( 'rejected' === $plugin->post_status ) {
						$tooltips[]  = 'Plugin was rejected.';
						$classes[]   = 'profile-plugin-rejected';
						$plugin_slug = substr( $plugin_slug, 9, - 9 );

					} elseif ( 'closed' === $plugin->post_status ) {
						$tooltips[] = 'Plugin is closed.';
						$classes[]  = 'profile-plugin-closed';

					} elseif ( 'disabled' === $plugin->post_status ) {
						$tooltips[] = 'Plugin is disabled (updates are active).';
						$classes[]  = 'profile-plugin-closed';
						$note = true;

					} else {
						// Plugin is some fashion of open.
						if ( 'approved' === $plugin->post_status ) {
							$note       = true;
							$tooltips[] = 'Plugin is approved, but has no data.';
						} else {
							$tooltips[] = 'Plugin is open.';
						}
						$classes[]      = 'profile-plugin-open';

						if ( strtotime( '-2 years' ) > strtotime( $plugin->post_date ) ) {
							$tooltips[] = 'Plugin is open, but has not been updated in more than two years.';
							$classes[]  = 'profile-plugin-open-old';
						}
					}

					echo '<span>';

					printf( '<a class="%1$s" title="%2$s" href="%3$s">%4$s</a>',
						esc_attr( implode( ' ', $classes ) ),
						esc_attr( implode( ' ', $tooltips ) ),
						add_query_arg( array( 'post' => $plugin->ID, 'action' => 'edit' ), admin_url( 'post.php' ) ),
						$plugin->post_name
					);

					if ( $note ) {
						echo '*';
					}

					$plugin_links = array(
						'<a href="//make.wordpress.org/pluginrepo/?s=' . urlencode( esc_attr( $plugin_slug ) ) . '" title="Click to search Pluginrepo P2 for mention of this plugin">P2</a>',
						'<a href="https://supportpress.wordpress.org/plugins/?q=' . urlencode( esc_attr( $plugin_slug ) ) . '&status=&todo=Search+%C2%BB" title="Click to search Pluginrepo SupportPress for mention of this plugin">SP</a>',
					);
					vprintf( '<span class="profile-sp-link">[ %s | %s ]</span>', $plugin_links );

					if ( $extra ) {
						echo $extra;
					}

					echo '</span></li>';
				}
				echo '</ul>';
			}
			?>
		</div>
		<?php

		/**
		 * Fires at the end of a plugin's author card.
		 *
		 * @param \WP_Post $plugin           The plugin object.
		 * @param \WP_User $author           The plugin author.
		 * @param array    $all_plugins      Array of plugin objects for all of user's plugins.
		 */
		do_action( 'wporg_plugins_author_card', $post, $author, $all_plugins );
	}

	/**
	 * Builds a link to a list of plugins submitted from a given IP.
	 *
	 * @param string $ip IP address of the plugin author.
	 * @return string
	 */
	protected static function link_ip( $ip ) {
		return sprintf( '<a href="%1$s">%2$s</a>', esc_url( add_query_arg( array(
			'post_type' => 'plugin',
			's'         => $ip,
		), admin_url( 'edit.php' ) ) ), $ip );
	}
}
