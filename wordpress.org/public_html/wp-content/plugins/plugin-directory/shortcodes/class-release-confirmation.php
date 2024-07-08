<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use Two_Factor_Core;
use function WordPressdotorg\Two_Factor\{ get_js_revalidation_url, get_revalidation_status };

/**
 * The [release-confirmation] shortcode handler.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Release_Confirmation {

	const SHORTCODE = 'release-confirmation';
	const COOKIE    = 'release_confirmation_access_token';
	const META_KEY  = '_release_confirmation_access_token';
	const URL_PARAM = 'access_token';

	/**
	 * @return string
	 */
	static function display() {
		$plugins = Tools::get_users_write_access_plugins( wp_get_current_user() );

		if ( ! $plugins ) {
			if ( ! headers_sent() ) {
				wp_safe_redirect( home_url( '/developers/' ) );
			}

			// Redirect via JS too, as technically the page output should've already started.
			echo '<script>document.location=' . json_encode( home_url( '/developers/' ) ) . '</script>';
			exit;
		}

		$plugins = array_map( function( $slug ) {
			return Plugin_Directory::get_plugin_post( $slug );
		}, $plugins );

		// Remove closed plugins.
		$plugins = array_filter( $plugins, function( $plugin ) {
			return ( $plugin && in_array( $plugin->post_status, array( 'publish', 'disabled', 'approved' ) ) );
		} );

		uasort( $plugins, function( $a, $b ) {
			// Get the most recent commit confirmation.
			$a_releases = Plugin_Directory::get_releases( $a );
			$b_releases = Plugin_Directory::get_releases( $b );

			$a_latest_release = $a_releases ? max( wp_list_pluck( $a_releases, 'date' ) ) : 0;
			$b_latest_release = $b_releases ? max( wp_list_pluck( $b_releases, 'date' ) ) : 0;

			$a_latest_release = max( $a_latest_release, strtotime( $a->last_updated ) );
			$b_latest_release = max( $b_latest_release, strtotime( $b->last_updated ) );

			return $b_latest_release <=> $a_latest_release;
		} );

		ob_start();

		$should_show_access_notice = false;
		foreach ( $plugins as $plugin ) {
			if ( $plugin->release_confirmation ) {
				$should_show_access_notice = true;
			}
		}

		if ( ! self::can_access() && $should_show_access_notice ) {
			if ( Two_Factor_Core::is_user_using_two_factor( get_current_user_id() ) ) {
				// A Two Factor user needs to have a valid recently re-validated session.
				printf(
					'<div class="plugin-notice notice notice-info notice-alt please-revalidate"><p>%s</p></div>',
					sprintf(
						__( 'Please <a href="%s">revalidate your two-factor session</a> to perform actions.', 'wporg-plugins'),
						esc_url( get_js_revalidation_url( get_permalink() ) )
					)
				);
				echo '<script>
					window.addEventListener( "reValidationComplete", function() {
						document.querySelector(".please-revalidate").remove();

						document.querySelectorAll(".disabled.disabled-by-2fa").forEach( function( el ) {
							el.classList.remove("disabled");
							el.classList.remove("disabled-by-2fa");
						} );
					} );
				</script>';

			} elseif ( isset( $_REQUEST['send_access_email'] ) ) {
				printf(
					'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
					__( 'Check your email for an access link to perform actions.', 'wporg-plugins')
				);
			} else {
				printf(
					'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
					sprintf(
						/* translators: %s: URL */
						__( 'Check your email for an access link, or <a href="%s">request a new email</a> to perform actions.', 'wporg-plugins'),
						Template::get_release_confirmation_access_link()
					)
				);
			}
		}

		echo '<p>' . __( 'This page is for authorized committers to view and manage releases of their plugins. Plugins with confirmations enabled require an extra action on this page to approve each new release.', 'wporg-plugins' ) . '</p>';

		/* translators: %s: plugins@wordpress.org */
		echo '<p>' . sprintf( __( 'Release confirmations can be enabled on the Advanced view of plugin pages. If you need to disable release confirmations for a plugin, please contact %s.', 'wporg-plugins' ), 'plugins@wordpress.org' ) . '</p>';

		$not_enabled = [];
		foreach ( $plugins as $plugin ) {
			self::single_plugin( $plugin );

			if ( ! $plugin->release_confirmation ) {
				$not_enabled[] = $plugin;
			}
		}

		if ( $not_enabled ) {
			printf(
				'<p><em>' . __( 'The following plugins do not have release confirmations enabled: %s', 'wporg-plugins') . '</em></p>',
				wp_sprintf_l( '%l', array_filter( array_map( function( $plugin ) {
					if ( 'publish' == get_post_status( $plugin ) ) {
						return sprintf(
							'<a href="%s">%s</a>',
							get_permalink( $plugin ),
							get_the_title( $plugin )
						);
					}
				}, $not_enabled ) ) )
			);
		}

		return ob_get_clean();
	}

	static function single_plugin( $plugin, $releases = false, $include_header = true ) {
		if ( false === $releases ) {
			$releases = Plugin_Directory::get_releases( $plugin );
		}

		if ( $include_header ) {
			printf(
				'<h2><a href="%s">%s</a></h2>',
				get_permalink( $plugin ),
				get_the_title( $plugin )
			);
		}

		echo '<table class="widefat plugin-releases-listing">
		<thead>
			<tr>
				<th>Version</th>
				<th>Date</th>
				<th>Committer</th>
				<th>Approval</th>
				<th>Actions</th>
		</thead>';

		if ( ! $releases ) {
			echo '<tr class="no-items"><td colspan="5"><em>' . __( 'No releases.', 'wporg-plugins' ) . '</em></td></tr>';
		}

		foreach ( $releases as $data ) {
			if ( ! is_array( $data['committer'] ) ) {
				$data['committer'] = (array) $data['committer'];
			}
			foreach ( $data['committer'] as $i => $login ) {
				$data['committer'][ $i ] = sprintf(
					'<a href="%s">%s</a>',
					'https://profiles.wordpress.org/' . get_user_by( 'login', $login )->user_nicename . '/',
					esc_html( $login )
				);
			}

			printf(
				'<tr>
					<td>%s</td>
					<td title="%s">%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
				</tr>',
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( sprintf(
						'https://plugins.trac.wordpress.org/browser/%s/tags/%s/',
						$plugin->post_name,
						$data['tag']
					) ),
					esc_html( $data['version'] )
				),
				esc_attr( gmdate( 'Y-m-d H:i:s', $data['date'] ) ),
				esc_html( sprintf( __( '%s ago', 'wporg-plugins' ), human_time_diff( $data['date'] ) ) ),
				implode( ', ', $data['committer'] ),
				self::get_approval_text( $plugin, $data ),
				self::get_actions( $plugin, $data )
			);
		}

		echo '</table>';
	}

	static function get_approval_text( $plugin, $data ) {
		ob_start();

		if ( ! $data['confirmations_required'] ) {
			_e( 'Release did not require confirmation.', 'wporg-plugins' );
		} else if ( ! empty( $data['discarded'] ) ) {
			_e( 'Release discarded.', 'wporg-plugins' );
		} else if ( $data['confirmed'] ) {
			_e( 'Release confirmed.', 'wporg-plugins' );
		} else if ( 1 == $data['confirmations_required'] ) {
			_e( 'Waiting for confirmation.', 'wporg-plugins' );
		} else {
			printf(
				__( '%s of %s required confirmations.', 'wporg-plugins' ),
				number_format_i18n( count( $data['confirmations'] ) ),
				number_format_i18n( $plugin->release_confirmation )
			);
		}

		echo '<div>';
		foreach ( $data['confirmations'] as $who => $time ) {
			if ( $who === wp_get_current_user()->user_login ) {
				$approved_text = sprintf(
					/* translators: 1: '5 hours' */
					__( 'You approved this, %1$s ago.', 'wporg-plugins' ),
					human_time_diff( $time )
				);
			} else {
				$user = get_user_by( 'login', $who );

				$approved_text = sprintf(
					/* translators: 1: Username, 2: '5 hours' */
					__( 'Approved by %1$s, %2$s ago.', 'wporg-plugins' ),
					$user->display_name ?: $user->user_login,
					human_time_diff( $time )
				);
			}

			printf(
				'<span title="%s">%s</span><br>',
				esc_attr( gmdate( 'Y-m-d H:i:s', $time ) ),
				$approved_text
			);
		}

		if ( ! empty( $data['discarded'] ) ) {
			$user = get_user_by( 'login', $data['discarded']['user'] );
			printf(
				'<span title="%s">%s</span><br>',
				esc_attr( gmdate( 'Y-m-d H:i:s', $time ) ),
				sprintf(
					__( 'Discarded by %1$s, %2$s ago.', 'wporg-plugins' ),
					$user->display_name ?: $user->user_login,
					human_time_diff( $data['discarded']['time'] )
				)
			);
		}
		echo '</div>';

		return ob_get_clean();
	}

	static function get_actions( $plugin, $data ) {
		$buttons = [];

		if ( $data['confirmations_required'] && empty( $data['discarded'] ) ) {
			$current_user_confirmed = isset( $data['confirmations'][ wp_get_current_user()->user_login ] );

			if ( ! $current_user_confirmed && ! $data['confirmed'] ) {
				$can_access = self::can_access();
				$can_manage = current_user_can( 'plugin_manage_releases', $plugin  );

				$disabled_attr  = '';
				$disabled_class = '';
				if ( ! $can_access || ! $can_manage ) {
					$disabled_attr = 'disabled';
				}
				if ( $can_manage && ! $can_access ) {
					$disabled_class = 'disabled disabled-by-2fa';
				}

				$buttons[] = sprintf(
					'<button type="submit" formaction="%s" class="approve-release wp-element-button button %s" %s>%s</button>',
					$can_manage ? Template::get_release_confirmation_link( $data['tag'], $plugin ) : '',
					esc_attr( $disabled_class ),
					esc_attr( $disabled_attr ),
					__( 'Confirm', 'wporg-plugins' )
				);
				$buttons[] = sprintf(
					'<button type="submit" formaction="%s" class="approve-release wp-element-button button %s" %s>%s</button>',
					$can_manage ? Template::get_release_confirmation_link( $data['tag'], $plugin, 'discard' ) : '',
					esc_attr( $disabled_class ),
					esc_attr( $disabled_attr ),
					__( 'Discard', 'wporg-plugins' )
				);

			}
		}

		return ! $buttons ? '' : '<form>' . implode( ' ', $buttons ) . '</form>';
	}

	static function can_access() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Plugin reviewers can always access the release management functionality, in wp-admin.
		if ( current_user_can( 'plugin_review' ) && is_admin() ) {
			return true;
		}

		// If they have an access token...
		if ( ! empty( $_COOKIE[ self::COOKIE ] ) ) {
			// ...and it be valid..
			$token = get_user_meta( get_current_user_id(), self::META_KEY, true );
			if (
				$token &&
				$token['time'] > ( time() - DAY_IN_SECONDS ) &&
				wp_check_password( $_COOKIE[ self::COOKIE ], $token['token'] )
			) {
				return true;
			}
		}

		// If the user uses 2FA, and they've re-validated, they can access.
		if ( Two_Factor_Core::is_user_using_two_factor( get_current_user_id() ) ) {
			// Check to see if they've confirmed their 2FA status recently..
			$status = get_revalidation_status();
			if ( ! $status['needs_revalidate'] ) {
				return true;
			}
		}

		return false;
	}

	static function generate_access_url( $user = null, $plugin = false ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$releases_page = home_url( '/developers/releases/' );
		if ( $plugin ) {
			$releases_page = get_permalink( $plugin );

			// Bounce through the login form for when this is being generated outside of a logged in request.
			if ( ! is_user_logged_in() || $user->ID !== get_current_user_id() ) {
				$releases_page = wp_login_url( $releases_page );
			}
		}

		// For a user with 2FA, they just need to view the page.
		if ( Two_Factor_Core::is_user_using_two_factor( $user->ID ) ) {
			return $releases_page;
		}

		$time      = time();
		$plaintext = wp_generate_password( 24, false );
		$token     = wp_hash_password( $plaintext );
		update_user_meta( $user->ID, self::META_KEY, compact( 'token', 'time' ) );

		$url = add_query_arg(
			self::URL_PARAM,
			urlencode( $plaintext ),
			$releases_page
		);

		return $url;
	}

	static function template_redirect() {
		$post = get_post();
		if ( ! $post || ! is_page() || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		// Migrate URL param to cookie.
		if ( isset( $_REQUEST[ self::URL_PARAM ] ) ) {
			setcookie( self::COOKIE, $_REQUEST[ self::URL_PARAM ], time() + DAY_IN_SECONDS, '/plugins/', 'wordpress.org', true, true );
		}

		// Expire the cookie when needed. This is not for security, only performance / cleanliness.
		if ( isset( $_COOKIE[ self::COOKIE ] ) && ! self::can_access() ) {
			unset( $_COOKIE[ self::COOKIE ] );
			setcookie( self::COOKIE, false, time() - DAY_IN_SECONDS );
		}

		// This page requires login.
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink() ) );
			exit;
		} else if ( isset( $_REQUEST[ self::URL_PARAM ] ) ) {
			wp_safe_redirect( remove_query_arg( self::URL_PARAM ) );
			exit;
		}

		// A page with this shortcode has no need to be indexed.
		add_filter( 'wporg_noindex_request', '__return_true' );
	}

	/**
	 * Displays the notice on the plugin front-end.
	 */
	static function frontend_notice() {
		$plugin   = get_post();
		$releases = Plugin_Directory::get_releases( $plugin ) ?: [];

		$releases_needing_confirmation = [];
		foreach ( $releases as $release ) {
			if ( ! $release['confirmed'] && $release['confirmations_required'] && empty( $release['discarded'] ) ) {
				$releases_needing_confirmation[] = $release;
			}
		}

		if ( ! $releases_needing_confirmation ) {
			return;
		}

		// Temporary;
		if ( ! self::can_access() && ! Two_Factor_Core::is_user_using_two_factor( get_current_user_id() ) ) {
			return printf(
				'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
				sprintf(
					__( 'This plugin has <a href="%s">a pending release that requires confirmation</a>.', 'wporg-plugins' ),
					home_url( '/developers/releases/' ) // TODO: Hardcoded URL.
				)
			);
		}

		echo '<div class="plugin-notice notice notice-info notice-alt">';

		$needs_revalidation = ! self::can_access();

		echo '<p' . ( $needs_revalidation ? ' style="display:none" class="wporg-2fa-hidden"' : '' ) . '>' . __( 'This plugin has a pending release that requires confirmation.', 'wporg-plugins' ) . '</p>';

		if ( $needs_revalidation ) {
			printf(
				'<p class="wporg-2fa-please-revalidate">%s</p>',
				sprintf(
					__( 'This plugin has a pending release that requires confirmation. Please <a href="%s">revalidate your two-factor session</a> to perform actions.', 'wporg-plugins' ),
					get_js_revalidation_url( get_permalink() )
				)
			);
			echo '<script>
					window.addEventListener( "reValidationComplete", function() {
						try {
							document.querySelector(".wporg-2fa-please-revalidate").remove();
							document.querySelector(".wporg-2fa-hidden").style.display = "block";

							document.querySelectorAll(".disabled.disabled-by-2fa").forEach( function( el ) {
								el.classList.remove("disabled");
								el.classList.remove("disabled-by-2fa");
							} );
						} catch(e) {
							document.location.reload();
						}
					} );
				</script>';
		}

		// Show the release actions.
		self::single_plugin( $plugin, $releases_needing_confirmation, false );

		echo '</div>';

	}
}
