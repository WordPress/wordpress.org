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
					vprintf( '<span class="profile-sp-link">[%s|%s]</span>', $author_links );
				?>

				<span class="profile-links">
					<a href="//profiles.wordpress.org/<?php echo $author->user_nicename; ?>">profile</a> |
					<a href="//wordpress.org/support/profile/<?php echo $author->user_nicename; ?>">support</a>
				</span>
				<span class="profile-email">
					&lt;<?php echo $author->user_email; ?>&gt;
					<span class="profile-sp-link">[<a href="https://supportpress.wordpress.org/plugins/?sender=<?php echo esc_attr( $author->user_email ); ?>&status=&todo=Search" title="Click to search Pluginrepo SupportPress for emails sent to/from this email address">SP</a>]</span>
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

		$unsavory = array();
		if ( property_exists( $author, 'capabilities' ) && isset( $author->capabilities['blocked'] ) && '1' == $author->capabilities['blocked'] ) {
			$unsavory[] = '<span title="User is banned from logging into WordPress.org">banned</span>';
		}
		if ( property_exists( $author, 'elf_not_trusted' ) && '1' == $author->elf_not_trusted ) {
			$unsavory[] = '<span title="User has been blocked from being able to post in the forums">blocked</span>';
		}
		if ( property_exists( $author, 'is_bozo' ) && '1' == $author->is_bozo ) {
			$unsavory[] = '<span title="User has been flagged by forum moderators as being problematic">a bozo</span>';
		}

		if ( $unsavory ) {
			echo '<p>This user is: <strong>' . implode( ', ', $unsavory ) . '</strong></p>';
		}

		$user_ips = array();// $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT poster_ip FROM plugin_2_posts WHERE poster_id = %s', $author->ID ) );
		$user_ips = array_filter( $user_ips, 'strlen' );
		if ( $user_ips ) :
			sort( $user_ips, SORT_NUMERIC );

			$user_ips = array_map( array( 'Author_Card', 'link_ip' ), $user_ips );

			echo '<p>IPs : ' . implode( ', ', $user_ips ) . '</p>';
		endif;

		if ( $author->user_pass == '~~~' ) : ?>
			<p><strong>Has not logged in since we reset passwords in June 2011</strong></p>
		<?php endif; ?>
		<div class="profile-plugins">
			<?php
			if ( empty( $author_commit ) && empty( $author_plugins ) ) {
				echo 'Not a developer on any plugin.';
			} else {
				echo '<strong>' . sprintf( _n( '1 plugin:', '%d plugins:', count( $all_plugins ) ), count( $all_plugins ) ) . '</strong>';

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
					vprintf( '<span class="profile-sp-link">[%s|%s]</span>', $plugin_links );

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

		self::show_warning_flags( $post, $author, $all_plugins );
	}

	/**
	 * Displays listing of warning flags for the plugin and its authors.
	 *
	 * @param \WP_Post $plugin           The plugin object.
	 * @param \WP_User $author           The plugin author.
	 * @param array    $all_plugins      Array of plugin objects for all of user's plugins.
	 */
	public static function show_warning_flags( $plugin, $author, $all_plugins ) {
		$flagged = array(
			'critical' => array(),
			'med'      => array(),
			'low'      => array(),
			'info'     => array(),
		);

		$approved_plugins = wp_list_filter( $all_plugins, array( 'post_status' => 'publish' ) );
		$rejected_plugins = wp_list_filter( $all_plugins, array( 'post_status' => 'rejected' ) );

		// More than one instance of a spammer coming from one of these IPs or IP blocks (critical)
		$post_ip       = get_post_meta( $plugin->ID, 'post_ip', true );
		$is_spammer_ip = false;

		$suspected_spammer_ip_blocks = array(
			'2.240.101.121',
			'2.240.163.90',
			'2.240.118.188',
			'2.241.60.160',
			'2.241.66.20',
			'2.241.124.187',
			'5.102.170.',
			'5.102.171.',
			'38.78.',
			'49.50.124.',
			'65.33.104.38',
			'71.41.77.202',
			'76.73.108.',
			'80.131.192.168',
			'87.188.67.',
			'87.188.75.',
			'87.188.82.',
			'91.228.',
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
			'217.87.249.',
			'217.87.251.',
			'217.87.252.',
		);

		foreach ( $suspected_spammer_ip_blocks as $spammer_ip ) {
			if ( 0 === strpos( $post_ip, $spammer_ip ) ) {
				$flagged['critical'][] = "spammer IP '$spammer_ip" . ( substr( $spammer_ip, - 1 ) == '.' ? '*' : '' ) . "'";
				$is_spammer_ip         = true;
				break;
			}
		}

		/*
		 * These IPs or IP blocks have instances of being used by spammers, but aren't concrete
		 * (or are fairly broad) that we don't want to auto-reject them. But we want to be wary. (high)
		 */
		$possible_spammer_ip_blocks = array(
			'2.240.',
			'2.241.',
			'91.238.',
			'182.68.',
		);
		if ( ! $is_spammer_ip ) {
			foreach ( $possible_spammer_ip_blocks as $spammer_ip ) {
				if ( 0 === strpos( $post_ip, $spammer_ip ) ) {
					$flagged['med'][] = "possible spammer IP '$spammer_ip" . ( substr( $spammer_ip, - 1 ) == '.' ? '*' : '' ) . "'";
					break;
				}
			}
		}

		/*
		 * If user is banned from logging into WP.org (critical)
		 *
		 * This is pretty rare. They would have to have been banned after having
		 * submitted the plugin.
		 */
		if ( property_exists( $author, 'capabilities' ) && isset( $author->capabilities['blocked'] ) && '1' == $author->capabilities['blocked'] ) {
			$flagged['critical'][] = 'user has been banned from logging into WP.org';
		}

		// If user < 2 days old, extra red-flaggy (high). ElseIf user is < 2 weeks old, consider them new. (med)
		$user_date    = new \DateTime( $author->user_registered );
		$user_date    = $user_date->format( 'U' );
		$request_date = new \DateTime( $plugin->post_date );
		$request_date = $request_date->format( 'U' );

		if ( $user_date > strtotime( '-3 days', $request_date ) ) {
			$flagged['med'][] = 'user &lt; 3 days old at request';
		} elseif ( $user_date > strtotime( '-2 weeks', $request_date ) ) {
			$flagged['low'][] = 'user &lt; 2 weeks old at request';
		}

		// If username ends in numbers and the user doesn't have any approved plugins.
		if ( preg_match( '/\d{3,}$/', $author->user_login ) && 0 === count( $approved_plugins ) ) {
			$flagged['med'][] = 'username ends in numbers';
		}

		// If username contains spammer-used words.
		$spam_username_substrings = array(
			'design',
			'develop',
			'html',
			'market',
			'seo',
		);
		foreach ( $spam_username_substrings as $spam ) {
			if ( false !== strpos( $author->user_login, $spam ) ) {
				$flagged['med'][] = "spammer-used username substring ($spam)";
				break;
			}
		}

		// If user's email is @yahoo.* or @mail.com (med).
		$suspicious_email_hosts = array( '@yahoo.', '@mail.com' );
		foreach ( $suspicious_email_hosts as $email_host ) {
			if ( false !== strpos( $author->user_email, $email_host ) ) {
				$flagged['med'][] = 'spammer-used email host';
				break;
			}
		}

		// If the plugin is for a typically spammed genre (med).
		$spam_names   = array();
		$spam_targets = array(
			'bookmark',
			'cookie',
			'facebook',
			'gallery',
			'google',
			'lightbox',
			'seo',
			'sitemap',
			'slide',
			'social',
			'twitter',
			'youtube',
		);
		foreach ( $spam_targets as $spam_target ) {
			if ( false !== strpos( $plugin->post_name, $spam_target ) || false !== strpos( $plugin->post_title, $spam_target ) ) {
				$spam_names[] = $spam_target;
			}
		}
		if ( ! empty( $spam_names ) ) {
			$flagged['low'][] = "plugin name/slug contains '" . implode( "', '", $spam_names ) . "'";
		}

		// If the plugin's name contains undesirable terms.
		$undesirables      = array();
		$undesirable_terms = array( 'autoblog', 'auto-blog', 'booking', 'plugin', 'spinning' );
		foreach ( $undesirable_terms as $undesirable ) {
			if ( false !== strpos( $plugin->post_name, $undesirable ) || false !== strpos( $plugin->post_title, $undesirable ) ) {
				$undesirables[] = $undesirable;
			}
		}
		if ( ! empty( $undesirables ) ) {
			$flagged['med'][] = "plugin name/slug contains potentially undesirable term(s) '" . implode( "', '", $undesirables ) . "'";
		}

		// Home URL is at weebly.com.
		if ( false !== strpos( $author->user_url, 'weebly.com' ) ) {
			$flagged['med'][] = 'spammer-used web host for user URL (weebly.com)';
		}

		// User's first plugin (low).
		if ( 0 === count( $approved_plugins ) ) {
			$flagged['low'][] = 'user has no open plugins';
		}

		// User was rejected for this plugin before.
		if ( ! empty( $rejected_plugins ) && in_array( $plugin->post_name, $rejected_plugins ) ) {
			$flagged['med'][] = 'user was previously rejected for this plugin';
		}

		// User has previously rejected plugins (med).
		if ( count( $rejected_plugins ) > 0 ) {
			$flagged['med'][] = 'user has rejected plugins';
		}

		// User is blocked from posting to the support forums (med).
		if ( property_exists( $author, 'elf_not_trusted' ) && '1' == $author->elf_not_trusted ) {
			$flagged['med'][] = 'user is blocked from posting to the support forums';
		}

		// User is marked as a bozo in the support forums (low).
		if ( property_exists( $author, 'is_bozo' ) && '1' == $author->is_bozo ) {
			$flagged['low'][] = 'user is a bozo in the support forums';
		}

		// No home URL (low).
		if ( empty( $author->user_url ) ) {
			$flagged['low'][] = 'no URL for user';
		} elseif ( false !== strpos( $author->user_url, 'blogspot.com' ) ) {
			$flagged['med'][] = 'user URL at blogspot.com';
		} elseif ( false !== strpos( $author->user_url, 'wordpress.com' ) ) {
			$flagged['low'][] = 'user URL at WordPress.com';
		}

		// User has submitted this plugin before (info).
		if ( in_array( $plugin->post_name, wp_list_pluck( $all_plugins, 'post_name' ) ) ) {
			$flagged['info'][] = 'user has submitted this plugin before';
		}

		$flagged = array_filter( $flagged );

		if ( empty( $flagged ) ) {
			echo '<span class="plugin-flagged-status plugin-queue-unflagged" style="display:none;" title="This plugin has no warning flags">&nbsp;</span>';
		} else {
			if ( isset( $flagged['critical'] ) ) {
				echo '<span class="plugin-flagged-status plugin-queue-flagged-critical" style="display:none;" title="This plugin should be rejected">&nbsp;</span>';
			}
			echo '<div class="plugin-queue-flagged">';
			echo '<h4>FLAGGED!</h4>';
			echo '<ul class="plugin-flagged">';

			foreach ( $flagged as $flag_level => $flag ) {
				$flag_name = 'critical' == $flag_level ? 'DO NOT APPROVE' : strtoupper( $flag_level );

				echo '<li class="plugin-flagged-' . $flag_level . '"><strong>' . $flag_name . ' (' . count( $flagged[ $flag_level ] ) . '):</strong> ';
				echo implode( '; ', $flagged[ $flag_level ] );

				// Critically flagged plugins should sit in queue for at least a week to give spammer
				// the impression that we're reviewing it
				if ( 'critical' == $flag_level ) {
					$reject_on = strftime( '%h. %e', strtotime( '+1 week', $request_date ) );
					echo '<br />Reject this plugin after ' . $reject_on . ' (to give impression we\'re reviewing it).';
				}

				echo '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		return;
	}
}
