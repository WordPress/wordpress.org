<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

require_once dirname( dirname( __DIR__ ) ) . '/class-template.php';
require_once dirname( dirname( __DIR__ ) ) . '/class-tools.php';

use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The Author Card admin metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Author_Card {

	/**
	 * List of known problematic IPs
	 *
	 * @var array
	 */
	public static $iffy_ips = [
		'2.240.',
		'2.241.',
		'5.102.170.',
		'5.102.171.',
		'38.78.',
		'47.15.',
		'49.50.124.',
		'65.33.104.38',
		'71.41.77.202',
		'76.73.108.',
		'80.131.192.168',
		'87.188.',
		'91.228.',
		'91.238.',
		'94.103.41.',
		'109.123.',
		'110.55.1.251',
		'110.55.4.248',
		'116.193.162.',
		'119.235.251.',
		'159.253.145.183',
		'173.171.9.190',
		'173.234.140.18',
		'188.116.36.',
		'217.87.',
	];

	/**
	 * Displays information about the author of the current plugin.
	 *
	 * @param int|WP_Post $post_or_user_id The post or the ID of a specific user.
	 */
	public static function display( $post_or_user_id = '' ) {
		global $wpdb;

		add_action( 'wporg_usercards_after_content', array(
			__NAMESPACE__ . '\Author_Card',
			'show_warning_flags',
		), 10, 6 );

		if ( is_numeric( $post_or_user_id ) ) {
			$post   = '';
			$author = get_user_by( 'id', $post_or_user_id );
		} else {
			$post   = $post_or_user_id ?: get_post();
			$author = is_object( $post ) ? get_user_by( 'id', $post->post_author ) : '';
		}

		if ( ! $author ) {
			return;
		}

		$author_support_rep = get_posts( [
			'post_type'      => 'plugin',
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'tax_query'      => [
				[
					'taxonomy' => 'plugin_support_reps',
					'field'    => 'slug',
					'terms'    => $author->user_nicename,
				],
			],
		] );

		$author_commit    = Tools::get_users_write_access_plugins( $author );
		$author_plugins_q = array(
			'author'         => $author->ID,
			'post_type'      => 'plugin',
			'post_status'    => array( 'approved', 'closed', 'disabled', 'new', 'pending', 'publish', 'rejected' ),
			'posts_per_page' => -1,
		);
		if ( $post ) {
			$author_plugins_q['post__not_in'] = array( $post->ID );
		}
		$author_plugins = get_posts( $author_plugins_q );
		$all_plugins    = $wpdb->get_results( "SELECT * FROM {$wpdb->posts} WHERE post_name IN ('" . implode( "', '", array_merge( $author_commit, wp_list_pluck( $author_plugins, 'post_name' ) ) ) . "')" );
		?>
		<div class="profile">
		<div class="profile-personal">
			<?php echo get_avatar( $author->ID, 48 ); ?>
			<div class="profile-details">
				<strong><a href="//profiles.wordpress.org/<?php echo $author->user_nicename; ?>"><?php echo $author->user_login; ?></a></strong>
				<?php
				$author_links = array(
					sprintf(
						'<a href="//make.wordpress.org/pluginrepo/?s=%s" title="%s">P2</a>',
						urlencode( esc_attr( $author->user_nicename ) ),
						esc_attr__( 'Click to search Pluginrepo P2 for mentions of this author', 'wporg-plugins' )
					),
					sprintf(
						'<a href="https://secure.helpscout.net/search/?query=mailbox:Plugins%%20%s" title="%s">HS</a>',
						urlencode( esc_attr( $author->user_nicename ) ),
						esc_attr__( 'Click to search Help Scout for mentions of this author', 'wporg-plugins' )
					),
				);
				vprintf( '<span class="profile-sp-link">[ %s | %s ]</span>', $author_links );
				?>

				<span class="profile-links">
					<a href="//profiles.wordpress.org/<?php echo $author->user_nicename; ?>"><?php _e( 'profile', 'wporg-plugins' ); ?></a> |
					<a href="//wordpress.org/support/users/<?php echo $author->user_nicename; ?>"><?php _e( 'support', 'wporg-plugins' ); ?></a>
				</span>

				<div class="profile-email">
					&lt;<?php echo esc_attr( $author->user_email ); ?>&gt;
					<?php
					$author_email_links = array(
						sprintf(
							'<a href="https://secure.helpscout.net/search/?query=mailbox:Plugins%%20%s" title="%s">HS</a>',
							urlencode( $author->user_email ),
							esc_attr__( 'Click to search Help Scout for emails sent to/from this email address', 'wporg-plugins' )
						),
					);
					vprintf( '<span class="profile-sp-link">[ %s ]</span>', $author_email_links );
					?>
				</div>
				<div class="profile-join">
					<?php
					/* translators: 1: time ago, 2: registration date */
					printf(
						__( 'Joined %1$s ago (%2$s)', 'wporg-plugins' ),
						human_time_diff( strtotime( $author->user_registered ) ),
						date( 'Y-M-d', strtotime( $author->user_registered ) )
					);
					?>
				</div>
			</div>
		</div>

		<?php if ( ! empty( $author->user_url ) ) : ?>
			<p class="profile-url">
				<?php _e( 'Author URL:', 'wporg-plugins' ); ?>
				<a href="<?php echo esc_url( $author->user_url ); ?>"><?php echo esc_html( $author->user_url ); ?></a>
			</p>
		<?php endif; ?>

		<div class="profile-user-notes">
			<?php
			if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				$user     = new \WP_User( $author, '', WPORG_SUPPORT_FORUMS_BLOGID );
				$statuses = array();

				if ( ! empty( $user->allcaps['bbp_blocked'] ) ) {
					$statuses[] = array(
						'text' => __( 'banned', 'wporg-plugins' ),
						'desc' => __( 'User is banned from logging into WordPress.org', 'wporg-plugins' ),
					);
				}

				if ( (bool) get_user_meta( $user->ID, 'is_bozo', true ) ) {
					$statuses[] = array(
						'text' => __( 'flagged', 'wporg-plugins' ),
						'desc' => __( 'User is flagged in the support forums', 'wporg-plugins' ),
					);
				}

				if ( $statuses ) {
					$labels = array();
					foreach ( $statuses as $status ) {
						$labels[] = sprintf(
							'<strong><span title="%s">%s</span></strong>',
							esc_attr( $status['desc'] ),
							$status['text']
						);
					}
					/* translators: %s: comma-separated list of negative user status labels */
					echo '<p>' . sprintf( __( 'This user is: %s', 'wporg-plugins' ), implode( ', ', $labels ) ) . '</p>';
				}

				$user_notes = get_user_meta( $user->ID, '_wporg_bbp_user_notes', true );
			}

			if ( ! empty( $user_notes ) ) {
				_e( 'User notes:', 'wporg-plugins' );
				echo '<ul>';
				foreach ( $user_notes as $note ) {
					$note_meta = sprintf(
						/* translators: 1: user note author's display name, 2: date */
						__( 'By %1$s on %2$s', 'wporg-plugins' ),
						$note->moderator,
						$note->date
					);

					$note_html  = apply_filters( 'comment_text', $note->text, null, array() );
					$note_html .= sprintf( '<p class="textright">%s</p>', $note_meta );

					echo '<li>' . $note_html . '</li>' . "\n";
				}
				echo '</ul>';
			}
			?>
		</div>

		<?php
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

			/* translators: %s: comma-separated list of plugin author's IP addresses */
			printf(
				'<p>' . __( 'IPs : %s', 'wporg-plugins' ) . '</p>',
				implode( ', ', array_map( array( __NAMESPACE__ . '\Author_Card', 'link_ip' ), $user_ips ) )
			);
		endif;
		?>

		<?php if ( $author->user_pass == '~~~' ) : ?>
			<p><strong><?php _e( 'Has not logged in since we reset passwords in June 2011', 'wporg-plugins' ); ?></strong></p>
		<?php endif; ?>

		<div class="profile-plugins">
			<?php
			if ( empty( $author_commit ) && empty( $author_plugins ) ) {
				_e( 'Not a developer on any plugin.', 'wporg-plugins' );
			} else {
				echo '<strong>' . sprintf( _n( '%d plugin:', '%d plugins:', count( $all_plugins ), 'wporg-plugins' ), count( $all_plugins ) ) . '</strong>';

				echo '<ul>';
				self::display_plugin_links( $all_plugins, $author_plugins, $author_commit );
				echo '</ul>';
			}

			if ( ! empty( $author_support_rep ) ) :
				?>
				<p><strong><?php esc_html_e( 'Support Rep for:', 'wporg-plugins' ); ?></strong></p>
				<ul>
					<?php self::display_plugin_links( $author_support_rep, $author_plugins, $author_commit ); ?>
				</ul>
			<?php endif; ?>
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

		echo '</div>';
	}

	/**
	 * Builds a link to a list of plugins submitted from a given IP.
	 *
	 * @param string $ip IP address of the plugin author.
	 * @return string
	 */
	protected static function link_ip( $ip ) {

		$ip_data = array(
			'name'    => $ip,
			'tooltip' => '',
			'iffy'    => false,
		);

		foreach ( self::$iffy_ips as $check_ip ) {
			if ( false !== strpos( $ip, $check_ip ) ) {
				$ip_data['name']   .= '*';
				$ip_data['tooltip'] = 'This IP may be problematic and has been used for abuse before.';
				$ip_data['iffy']    = true;
			}
		}

		$output_ip = sprintf(
			'<a href="%1$s" title="%2$s">%3$s</a>',
			esc_url( add_query_arg( array(
				'post_type' => 'plugin',
				's'         => $ip,
			), admin_url( 'edit.php' ) ) ),
			$ip_data['tooltip'],
			$ip_data['name']
		);

		return $output_ip;
	}

	/**
	 * Displays a list of the passed plugins with their meta information.
	 *
	 * @param array $all_plugins    All plugins associated with this author.
	 * @param array $author_plugins Other plugins by this author.
	 * @param array $author_commit  Plugins the author has commit access to.
	 */
	protected static function display_plugin_links( $all_plugins, $author_plugins, $author_commit ) {
		foreach ( $all_plugins as $plugin ) {
			echo '<li>';
			$note         = false;
			$extra        = '';
			$classes      = $tooltips = array();
			$last_updated = get_post_meta( $plugin->ID, 'last_updated', true );

			if ( in_array( $plugin->post_name, wp_list_pluck( $author_plugins, 'post_name' ) ) ) {
				$tooltips[] = __( 'This user submitted this plugin.', 'wporg-plugins ' );
				$classes[]  = 'plugin-owner';
				if ( ! in_array( $plugin->post_name, $author_commit ) ) {
					$note       = true;
					$tooltips[] = __( 'The user is not a current committer.', 'wporg-plugins' );
				}
			}

			$plugin_name = $plugin->post_title;
			$plugin_slug = $plugin->post_name;
			if ( in_array( $plugin->post_status, array( 'new', 'pending' ) ) ) {
				/* translators: %s: time ago */
				$extra     .= sprintf(
					__( '(requested %s ago)', 'wporg-plugins' ),
					human_time_diff( strtotime( $last_updated ) )
				);
				$tooltips[] = __( 'Requested, remains unapproved.', 'wporg-plugins' );
				$classes[]  = 'profile-plugin-requested';

			} elseif ( 'rejected' === $plugin->post_status ) {
				$tooltips[]  = __( 'Plugin was rejected.', 'wporg-plugins' );
				$classes[]   = 'profile-plugin-rejected';
				$plugin_slug = substr( $plugin_slug, 9, - 9 );

			} elseif ( 'closed' === $plugin->post_status ) {
				/* translators: %s: close/disable reason */
				$extra     .= sprintf(
					__( '(closed: %s)', 'wporg-plugins' ),
					Template::get_close_reason( $plugin )
				);
				$tooltips[] = __( 'Plugin is closed.', 'wporg-plugins' );
				$classes[]  = 'profile-plugin-closed';

			} elseif ( 'disabled' === $plugin->post_status ) {
				/* translators: %s: close/disable reason */
				$extra     .= sprintf(
					__( '(disabled: %s)', 'wporg-plugins' ),
					Template::get_close_reason( $plugin )
				);
				$tooltips[] = __( 'Plugin is disabled (updates are active).', 'wporg-plugins' );
				$classes[]  = 'profile-plugin-closed';
				$note       = true;

			} else {
				// Plugin is some fashion of open.
				if ( 'approved' === $plugin->post_status ) {
					$note       = true;
					$tooltips[] = __( 'Plugin is approved, but has no data.', 'wporg-plugins' );
					$classes[]  = 'profile-plugin-open-unused';
				} elseif ( strtotime( '-2 years' ) > strtotime( $last_updated ) ) {
					$tooltips[] = __( 'Plugin is open but has not been updated in more than two years.', 'wporg-plugins' );
					$classes[]  = 'profile-plugin-open-old';
				} else {
					$tooltips[] = __( 'Plugin is open.', 'wporg-plugins' );
				}
				$classes[] = 'profile-plugin-open';
			}

			echo '<span>';

			printf(
				'<a class="%1$s" title="%2$s" href="%3$s">%4$s</a>',
				esc_attr( implode( ' ', $classes ) ),
				esc_attr( implode( ' ', $tooltips ) ),
				esc_attr( get_permalink( $plugin ) ),
				$plugin->post_name
			);

			if ( $note ) {
				echo '*';
			}

			vprintf( '<span class="profile-sp-link">[ %s | %s | %s ]</span>', [
				sprintf(
					'<a href="%s" title="%s">%s</a>',
					esc_url( get_edit_post_link( $plugin->ID, '' ) ),
					esc_attr__( 'Edit this plugin', 'wporg-plugins' ),
					__( 'Edit', 'wporg-plugins' )
				),
				sprintf(
					'<a href="//make.wordpress.org/pluginrepo/?s=%s" title="%s">P2</a>',
					urlencode( esc_attr( $plugin_slug ) ),
					esc_attr__( 'Click to search Plugin Team P2 for mentions of this plugin', 'wporg-plugins' )
				),
				sprintf(
					'<a href="https://secure.helpscout.net/search/?query=mailbox:Plugins%%20%s" title="%s">HS</a>',
					rawurlencode( esc_attr( $plugin_name ) ),
					esc_attr__( 'Click to search Help Scout for mentions of this plugin', 'wporg-plugins' )
				),
			] );

			if ( $extra ) {
				echo ' ' . $extra;
			}

			echo '</span></li>' . "\n";
		}
	}
}
