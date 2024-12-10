<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use Two_Factor_Core;
use function WordPressdotorg\Two_Factor\{
	Revalidation\get_status as get_revalidation_status,
	Revalidation\get_js_url as get_revalidation_js_url,
	get_onboarding_account_url as get_2fa_onboarding_url
};

/**
 * The [release-confirmation] shortcode handler.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Release_Confirmation {

	const SHORTCODE = 'release-confirmation';
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

		// If the user is not using 2FA, show a notice.
		if ( ! Two_Factor_Core::is_user_using_two_factor( get_current_user_id() ) ) {
			printf(
				'<div class="plugin-notice notice notice-error notice-alt"><p>%s</p></div>',
				sprintf(
					__( 'Your account has elevated privileges and requires extra security before you can manage plugin releases. Please <a href="%s">enable two-factor authentication now</a>.', 'wporg-plugins' ),
					get_2fa_onboarding_url()
				)
			);
		}

		echo '<p>' . __( 'This page is for authorized committers to view and manage releases of their plugins. Plugins with confirmations enabled require an extra action on this page to approve each new release.', 'wporg-plugins' ) . '</p>';

		/* translators: %s: plugins@wordpress.org */
		echo '<p>' . sprintf( __( 'Release confirmations can be enabled on the Advanced view of plugin pages. If you need to disable release confirmations for a plugin, please contact %s.', 'wporg-plugins' ), 'plugins@wordpress.org' ) . '</p>';

		$not_enabled = [];
		foreach ( $plugins as $plugin ) {
			printf(
				'<h2><a href="%s">%s</a></h2>',
				get_permalink( $plugin ),
				get_the_title( $plugin )
			);

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

	static function single_plugin( $plugin ) {
		$releases = Plugin_Directory::get_releases( $plugin );

		echo '<div class="wp-block-table is-style-stripes">
		<table class="plugin-releases-listing">
		<thead>
			<tr>
				<th>Version</th>
				<th>Date</th>
				<th>Committer</th>
				<th>Approval</th>
				<th>Actions</th>
		</thead></div>';

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
					<td><div class="plugin-releases-listing-actions">%s</div></td>
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
		echo '</div>';
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

		if (
			! is_user_logged_in() ||
			! Two_Factor_Core::is_user_using_two_factor( get_current_user_id() ) ||
			! current_user_can( 'plugin_manage_releases', $plugin  ) ||

			// No need to show actions if the release can't be confirmed, or is already confirmed
			! $data['confirmations_required'] ||
			$data['confirmed']
		) {
			return '';
		}

		if ( empty( $data['discarded'] ) ) {
			$current_user_confirmed = isset( $data['confirmations'][ wp_get_current_user()->user_login ] );

			if ( ! $current_user_confirmed ) {
				$confirm_link = Template::get_release_confirmation_link( $data['tag'], $plugin );
				$discard_link = Template::get_release_confirmation_link( $data['tag'], $plugin, 'discard' );

				$confirm_link = get_revalidation_js_url( $confirm_link );
				$discard_link = get_revalidation_js_url( $discard_link );

				$buttons[] = sprintf(
					'<a href="%s" class="wp-element-button button approve-release" data-2fa-required data-2fa-message="%s">%s</a>',
					$confirm_link,
					esc_attr(
						sprintf(
							/* translators: 1: Version number, 2: Plugin name. */
							__( 'Confirm your Two-Factor Authentication to release version %1$s of %2$s.', 'wporg-plugins' ),
							esc_html( $data['version'] ),
							$plugin->post_title
						)
					),
					__( 'Confirm', 'wporg-plugins' )
				);

				$buttons[] = sprintf(
					'<a href="%s" class="wp-element-button button approve-release" data-2fa-required data-2fa-message="%s">%s</a>',
					$discard_link,
					esc_attr(
						sprintf(
							/* translators: 1: Version number, 2: Plugin name. */
							__( 'Confirm your Two-Factor Authentication to discard the release %1$s for %2$s.', 'wporg-plugins' ),
							esc_html( $data['version'] ),
							$plugin->post_title
						)
					),
					__( 'Discard', 'wporg-plugins' )
				);

			}
		} elseif (
			$data['discarded'] &&
			current_user_can( 'plugin_review' ) &&
			( time() - $data['discarded']['time'] ) < 2 * DAY_IN_SECONDS
		) {
			// Plugin reviewers can undo a discard within 48hrs.
			$buttons[] = sprintf(
				'<a href="%s" class="wp-element-button button undo-discard">%s</a>',
				Template::get_release_confirmation_link( $data['tag'], $plugin, 'undo-discard' ),
				__( 'Undo Discard', 'wporg-plugins' )
			);
		}

		return implode( ' ', $buttons );
	}

	static function generate_access_url( $user = null ) {
		return home_url( '/developers/releases/' );
	}

	static function template_redirect() {
		$post = get_post();
		if ( ! $post || ! is_page() || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
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
	 *
	 * @param WP_Post $post The currently displayed post.
	 * @return void
	 */
	static function frontend_unconfirmed_releases_notice( $post = null ) {
		$post = get_post( $post );

		if ( ! $post->release_confirmation || ! current_user_can( 'plugin_admin_edit', $post ) ) {
			return;
		}

		$releases = Plugin_Directory::get_releases( $post ) ?: [];
		$warning  = false;

		foreach ( $releases as $release ) {
			if ( ! $release['confirmed'] && $release['confirmations_required'] && empty( $release['discarded'] ) ) {
				$warning = true;
				break;
			}
		}

		if ( ! $warning ) {
			return;
		}

		printf(
			'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
			sprintf(
				__( 'This plugin has <a href="%s">a pending release that requires confirmation</a>.', 'wporg-plugins' ),
				home_url( '/developers/releases/' ) // TODO: Hardcoded URL.
			)
		);
	}
}
