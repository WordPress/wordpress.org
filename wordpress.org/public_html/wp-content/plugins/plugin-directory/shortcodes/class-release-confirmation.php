<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Email\Release_Confirmation_Access as Release_Confirmation_Access_Email;

/**
 * The [release-confirmation] shortcode handler.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Release_Confirmation {

	const SHORTCODE = 'release-confirmation';
	const COOKIE    = 'release_confirmation_access_token';
	const NONCE     = 'plugins-developers-releases-page';
	const URL_PARAM = 'access_token';

	/**
	 * @return string
	 */
	static function display() {
		$plugins = Tools::get_users_write_access_plugins( wp_get_current_user() );

		if ( ! $plugins ) {
			wp_safe_redirect( home_url( '/developers/' ) );
			// Redirect via JS too, as technically the page output should've already started (but probably hasn't on WordPress.org)
			echo '<script>document.location=' . json_encode( home_url( '/developers/' ) ) . '</script>';
			exit;
		}

		$plugins = array_map( function( $slug ) {
			return Plugin_Directory::get_plugin_post( $slug );
		}, $plugins );

		// Remove closed plugins.
		$plugins = array_filter( $plugins, function( $plugin ) {
			return $plugin && 'publish' === $plugin->post_status;
		} );

		uasort( $plugins, function( $a, $b ) {
			// Get the most recent commit confirmation.
			$a_releases = get_post_meta( $a->ID, 'confirmed_releases', true ) ?: [];
			$b_releases = get_post_meta( $b->ID, 'confirmed_releases', true ) ?: [];

			$a_latest_release = $a_releases ? max( wp_list_pluck( $a_releases, 'date' ) ) : 0;
			$b_latest_release = $b_releases ? max( wp_list_pluck( $b_releases, 'date' ) ) : 0;

			$a_latest_release = max( $a_latest_release, strtotime( $a->last_updated ) );
			$b_latest_release = max( $b_latest_release, strtotime( $b->last_updated ) );

			return $b_latest_release <=> $a_latest_release;
		} );

		ob_start();

		if ( ! self::can_access() ) {
			if ( isset( $_REQUEST['send_access_email'] ) ) {
				$email = new Release_Confirmation_Access_Email(
					wp_get_current_user()
				);
				$email->send();

				printf(
					'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
					__( 'Check your email for an access link to perform actions.', 'wporg-plugins'),
				);
			} else {
				printf(
					'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
					sprintf(
						/* translators: %s: URL */
						__( 'Check your email for an access link, or <a href="%s">request a new email</a> to perform actions.', 'wporg-plugins'),
						add_query_arg( 'send_access_email', 1 )
					)
				);
			}
		}

		echo '<p>' . 'Intro to this page goes here. Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.' . '</p>';

		$not_enabled = [];
		foreach ( $plugins as $plugin ) {
			self::single_plugin_row( $plugin );

			if ( ! $plugin->release_confirmation && 'publish' === $plugin->post_status ) {
				$not_enabled[] = $plugin;
			}
		}

		if ( $not_enabled ) {
			printf( 
				'<em>' . __( 'The following plugins do not have release confirmations enabled: %s', 'wporg-plugins') . '</em>',
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

	static function single_plugin_row( $plugin, $include_header = true ) {
		$confirmations_required = $plugin->release_confirmation;
		$confirmed_releases     = get_post_meta( $plugin->ID, 'confirmed_releases', true ) ?: [];
		$all_tags               = get_post_meta( $plugin->ID, 'tags', true ) ?: [];

		// Sort releases most recent first.
		uasort( $confirmed_releases, function( $a, $b ) {
			return $b['date'] <=> $a['date'];
		} );

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
				<th class="actions">Actions</th>
		</thead>';

		if ( ! $confirmed_releases && ! $all_tags ) {
			echo '<tr class="no-items"><td colspan="5"><em>' . __( 'No releases.', 'wporg-plugins' ) . '</em></td></tr>';
		}

		foreach ( $confirmed_releases as $tag => $data ) {
			$data['confirmations_required'] = $confirmations_required;

			printf(
				'<tr>
					<td>%s</td>
					<td title="%s">%s</td>
					<td>%s</td>
					<td>%s</td>
					<td>%s</td>
				</tr>',
				sprintf(
					'<a href="https://plugins.trac.wordpress.org/browser/%s/tags/%s/">%s</a>',
					$plugin->post_name,
					esc_html( $tag ),
					esc_html( $data['version'] )
				),
				esc_attr( gmdate( 'Y-m-d H:i:s', $data['date'] ) ),
				esc_html( sprintf( __( '%s ago', 'wporg-plugins' ), human_time_diff( $data['date'] ) ) ),
				esc_html( implode( ', ', (array) $data['committer'] ) ),
				self::get_approval_text( $plugin, $data ),
				self::get_actions( $plugin, $data )
			);
		}

		// List older releases too.
		foreach ( $all_tags as $tag => $tag_data ) {
			if ( isset( $confirmed_releases[ $tag ] ) ) {
				continue;
			}

			printf(
				'<tr class="non-confirmed">
					<td>%s</td>
					<td title="%s">%s</td>
					<td>%s</td>
					<td colspan="2"><em>%s</em></td>
				</tr>',
				sprintf(
					'<a href="https://plugins.trac.wordpress.org/browser/%s/tags/%s/">%s</a>',
					$plugin->post_name,
					esc_html( $tag_data['tag'] ),
					esc_html( $tag )
				),
				esc_attr( $tag_data['date'] ),
				esc_html( sprintf( __( '%s ago', 'wporg-plugins' ), human_time_diff( strtotime( $tag_data['date'] ) ) ) ),
				esc_html( $tag_data['author'] ),
				'Release confirmation not required.'
			);


		}

		echo '</table>';
	}

	static function get_approval_text( $plugin, $data ) {
		ob_start();

		$confirmations = $data['confirmations'] ?? [];

		if ( !empty( $data['confirmed'] ) || count( $confirmations ) >= $data['confirmations_required'] ) {
			_e( 'Release confirmed.', 'wporg-plugins' );
		} else if ( 1 == $data['confirmations_required'] ) {
			_e( 'Waiting for confirmation.', 'wporg-plugins' );
		} else {
			printf(
				__( '%s of %s required confirmations.', 'wporg-plugins' ),
				number_format_i18n( count( $confirmations ) ),
				number_format_i18n( $data['confirmations_required'] )
			);
		}

		echo '<div>';
		foreach ( $confirmations as $who => $time ) {
			$user = get_user_by( 'slug', $who );

			if ( $who === wp_get_current_user()->user_login ) {
				$approved_text = sprintf(
					/* translators: 1: '5 hours' */
					__( 'You approved this, %1$s ago.', 'wporg-plugins' ),
					human_time_diff( $time )
				);
			} else {
				$approved_text = sprintf(
					/* translators: 1: Username, 2: '5 hours' */
					__( 'Approved by %1$s, %2$s ago.', 'wporg-plugins' ),
					$user->display_name ?: $user->user_nicename,
					human_time_diff( $time )
				);
			}

			printf(
				'<span title="%s">%s</span><br>',
				esc_attr( gmdate( 'Y-m-d H:i:s', $time ) ),
				$approved_text
			);
		}
		echo '</div>';

		return ob_get_clean();
	}

	static function get_actions( $plugin, $data ) {
		$buttons = [];

		if ( $data['confirmations_required'] ) {
			$has_enough_confirmations = count( $data['confirmations'] ) >= $data['confirmations_required'];
			$current_user_confirmed   = isset( $data['confirmations'][ wp_get_current_user()->user_login ] );

			if ( ! $current_user_confirmed && ! $has_enough_confirmations ) {
				if ( self::can_access() ) {
					$class = 'button-primary';
					$url   = Template::get_release_confirmation_link( $data['tag'], $plugin );
				} else {
					$class = 'button-secondary disabled';
					$url   = '';
				}

				$buttons[] = sprintf(
					'<a href="%s" class="button approve-release %s">%s</a>',
					$url,
					$class,
					__( 'Confirm', 'wporg-plugins' )
				);
			} elseif ( $current_user_confirmed ) {
				$buttons[] = sprintf(
					'<a class="button approve-release button-secondary disabled">%s</a>',
					__( 'Confirmed', 'wporg-plugins' )
				);
			}
		}

		return implode( ' ', $buttons );
	}

	static function can_access() {
		// Must have an access token..
		if ( ! is_user_logged_in() || empty( $_COOKIE[ self::COOKIE ] ) ) {
			return false;
		}

		if ( false !== wp_verify_nonce( $_COOKIE[ self::COOKIE ], self::NONCE ) ) {
			return true;
		}

		setcookie( self::COOKIE, false, time() - DAY_IN_SECONDS );

		return false;
	}

	static function generate_access_url( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$current_user = wp_get_current_user()->ID;
		wp_set_current_user( $user->ID );

		$url = wp_nonce_url(
			home_url( '/developers/releases/' ), // TODO: Hardcoded url.
			self::NONCE,
			self::URL_PARAM
		);

		wp_set_current_user( $current_user );

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
}
