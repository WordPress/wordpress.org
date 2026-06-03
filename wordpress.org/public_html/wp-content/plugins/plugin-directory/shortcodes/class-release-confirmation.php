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
		// In the rest-api, just return the shortcode tag, so it can be rendered properly in the app or other consumers.
		if ( wp_is_serving_rest_request() ) {
			return '[' . self::SHORTCODE . ']';
		}

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
		if (
			class_exists( 'Two_Factor_Core' ) &&
			! Two_Factor_Core::is_user_using_two_factor( get_current_user_id() )
		) {
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
				'<h2 id="releases-%s"><a href="%s">%s</a></h2>',
				esc_attr( $plugin->post_name ),
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
		<colgroup>
			<col width="25%">
		</colgroup>
		<thead>
			<tr>
				<th>' . _x( 'Release', 'Releases Table header', 'wporg-plugins' ) . '</th>
				<th>&nbsp;</th>
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
					'https://profiles.wordpress.org/' . ( get_user_by( 'login', $login )->user_nicename ?? '' ) . '/',
					esc_html( $login )
				);
			}

			printf(
				'<tr>
					<td>%s<br><small>%s</small></td>
					<td>
						<form method="POST">
							<div class="plugin-releases-listing-actions">%s</div>
							%s
						</form>
					</td>
				</tr>',
				sprintf(
					__( 'Version %s', 'wporg-plugins' ),
					sprintf(
						'<a href="%s">%s</a>',
						esc_url( sprintf(
							'https://plugins.trac.wordpress.org/browser/%s/tags/%s/',
							$plugin->post_name,
							$data['tag']
						) ),
						esc_html( $data['version'] )
					),
				),
				sprintf(
					/* translators: 1: time eg. '3 hours ago', 2: the committer(s). */
					__( 'Released %1$s by %2$s', 'wporg-plugins' ),
					sprintf(
						'<span title="%s">%s</span>',
						esc_attr( gmdate( 'Y-m-d H:i:s', $data['date'] ) ),
						esc_html( sprintf( __( '%s ago', 'wporg-plugins' ), human_time_diff( $data['date'] ) ) ),
					),
					implode( ', ', $data['committer'] ),
				),
				self::get_actions( $plugin, $data ),
				self::get_approval_text( $plugin, $data ) .
					self::get_rollout_strategy( $plugin, $data )
			);
		}

		echo '</table>';
		echo '</div>';
		echo '<style>
			.plugin-releases-listing-actions {
				float: right;
			}
		</style>';
	}

	static function get_approval_text( $plugin, $data ) {
		ob_start();

		if ( ! $data['confirmations_required'] ) {
			_e( 'Release did not require confirmation.', 'wporg-plugins' );
		} else if ( ! empty( $data['discarded'] ) ) {
			_e( 'Release discarded.', 'wporg-plugins' );
		} elseif ( $data['confirmed'] && ! $data['zips_built'] ) {
			_e( 'Release confirmed, waiting for processing.', 'wporg-plugins' );
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
				esc_attr( gmdate( 'Y-m-d H:i:s', $data['discarded']['time'] ) ),
				sprintf(
					__( 'Discarded by %1$s, %2$s ago.', 'wporg-plugins' ),
					$user->display_name ?: $user->user_login,
					human_time_diff( $data['discarded']['time'] )
				)
			);
		}

		// Add a note that the release is in a processing state.
		if ( $data['confirmed'] && ! $data['zips_built'] ) {
			printf(
				'<span>%s</span><br>',
				__( 'The ZIP files for this release have not yet been built by WordPress.org.', 'wporg-plugins' )
			);
		}

		self::render_cooldown_status( $data );

		echo '</div>';

		$text = ob_get_clean();

		/**
		 * Filters the release approval text.
		 *
		 * @param string  $text   The release approval text.
		 * @param WP_Post $plugin The plugin post object.
		 * @param array   $data   The release data.
		 * @return string
		 */
		return apply_filters( 'wporg_plugins_release_approval_text', $text, $plugin, $data );
	}

	/**
	 * Render a single line describing the cooldown state of a release: pending serve time.
	 * Skipped for releases without a cooldown delay (feature off at release creation, or
	 * force-released), discarded releases, releases that haven't moved past
	 * confirmation/processing, or where the cooldown window has elapsed.
	 *
	 * @param array $data The release row from Plugin_Directory::get_releases().
	 */
	protected static function render_cooldown_status( $data ) {
		$release_delay = (int) ( $data['release_delay'] ?? 0 );
		if ( ! $release_delay ) {
			return;
		}

		if ( ! empty( $data['discarded'] ) ) {
			return;
		}

		// Skip when the release hasn't moved past the confirmation/processing stage yet.
		if ( $data['confirmations_required'] && ( ! $data['confirmed'] || ! $data['zips_built'] ) ) {
			return;
		}

		$release_time   = $data['confirmations'] ? max( $data['confirmations'] ) : (int) $data['date'];
		$cooldown_until = $release_time + $release_delay;

		if ( $cooldown_until <= time() ) {
			return;
		}

		$message = sprintf(
			/* translators: %s: relative time until cooldown expires */
			__( 'Will be served to sites in %s.', 'wporg-plugins' ),
			human_time_diff( time(), $cooldown_until )
		);
		printf(
			'<span title="%s">%s</span><br>',
			esc_attr( gmdate( 'Y-m-d H:i:s', $cooldown_until ) ),
			esc_html( $message )
		);
	}

	static function get_actions( $plugin, $data ) {
		$buttons = [];

		if (
			! is_user_logged_in() ||
			! current_user_can( 'plugin_manage_releases', $plugin  ) ||
			( class_exists( 'Two_Factor_Core' ) && ! Two_Factor_Core::is_user_using_two_factor( get_current_user_id() ) ) ||

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
					'<button formaction="%s" class="wp-element-button button approve-release" data-2fa-required data-2fa-message="%s">%s</button>',
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
					'<button formaction="%s" class="wp-element-button button discard-release has-very-light-gray-background-color has-charcoal-1-color" data-2fa-required data-2fa-message="%s">%s</button>',
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
				'<button formaction="%s" class="wp-element-button button undo-discard">%s</buttona>',
				Template::get_release_confirmation_link( $data['tag'], $plugin, 'undo-discard' ),
				__( 'Undo Discard', 'wporg-plugins' )
			);
		}

		return implode( ' ', $buttons );
	}

	/**
	 * Display the Rollout Strategy options for a given plugin release.
	 *
	 * @param WP_Post $plugin The plugin post object.
	 * @param array   $data   The release data.
	 * @return string HTML for the rollout strategy options.
	 */
	static function get_rollout_strategy( $plugin, $data ) {
		if ( ! current_user_can( 'plugin_manage_releases', $plugin ) ) {
			return '';
		}

		if ( ! $data['confirmations_required'] || ! empty( $data['discarded'] ) ) {
			return '';
		}

		$rollout = $data['rollout_strategy'] ?? '';
		if ( $data['confirmed'] && ! $rollout ) {
			// If the release is confirmed, but no rollout strategy was set for the release, don't display the UI.
			return '';
		}

		ob_start();
		echo '<div class="release-strategy">';
		echo '<h3>' . __( 'Rollout Strategy', 'wporg-plugins' ) . '</h3>';

		echo '<select
			id="rollout_strategy"
			name="rollout_strategy"
			onchange="this.nextElementSibling.innerText = this.options[this.selectedIndex].dataset.description;"'
			. disabled( $data['confirmed'], true, false ) .
			'>';
		foreach ( Template::get_rollout_strategies() as $slug => $set ) {
			printf(
				'<option value="%s" data-description="%s" %s>%s</option>',
				esc_attr( $slug ),
				esc_attr( $set['description'] ),
				selected( $rollout, $slug, false ),
				esc_html( $set['name'] )
			);
		}
		echo '</select>';
		echo '<div class="help">' . esc_html( Template::get_rollout_strategies()[ $rollout ]['description'] ?? '' ) . '</div>';

		echo '</div>';

		return ob_get_clean();
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
	 * Surfaces an in-cooldown notice to committers on the plugin's public page.
	 *
	 * Bails when the viewer isn't a committer, when there's no current release in
	 * an active cooldown window, or when the release was force-released
	 * (release_delay = 0 ⇒ no cooldown).
	 *
	 * @param WP_Post $post The currently displayed post.
	 */
	public static function frontend_cooldown_notice( $post = null ) {
		$post = get_post( $post );

		if ( ! $post || ! current_user_can( 'plugin_admin_edit', $post ) ) {
			return;
		}

		$version = get_post_meta( $post->ID, 'version', true );
		if ( ! $version ) {
			return;
		}

		$release = Plugin_Directory::get_release( $post, $version );
		if ( ! $release ) {
			return;
		}

		$release_delay = (int) ( $release['release_delay'] ?? 0 );
		if ( ! $release_delay ) {
			return;
		}

		$release_time   = $release['confirmations'] ? max( $release['confirmations'] ) : (int) $release['date'];
		$cooldown_until = $release_time + $release_delay;

		if ( $cooldown_until <= time() ) {
			return;
		}

		printf(
			'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
			wp_kses(
				sprintf(
					/* translators: 1: plugin version, 2: relative time until cooldown expires, 3: delay duration in hours, 4: plugins@wordpress.org link */
					__( 'Version %1$s will be released to sites in about %2$s. WordPress.org currently delays plugin updates by %3$d hours so moderators and security scanners can review changes before they reach users. If this update fixes a security issue that needs to ship sooner, contact %4$s.', 'wporg-plugins' ),
					'<code>' . esc_html( $version ) . '</code>',
					esc_html( human_time_diff( time(), $cooldown_until ) ),
					(int) ( $release_delay / HOUR_IN_SECONDS ),
					'<a href="mailto:plugins@wordpress.org">plugins@wordpress.org</a>'
				),
				array(
					'code' => array(),
					'a'    => array( 'href' => true ),
				)
			)
		);
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
