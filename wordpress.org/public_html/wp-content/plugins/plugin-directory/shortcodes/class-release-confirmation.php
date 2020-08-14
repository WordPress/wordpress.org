<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The [release-confirmation] shortcode handler.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Release_Confirmation {

	const SHORTCODE = 'release-confirmation';
	const COOKIE    = 'release_confirmation_access_token';

	/**
	 * @return string
	 */
	static function display() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		ob_start();
		self::display_for_user( wp_get_current_user() );
		return ob_get_clean();
	}

	static function display_for_user( $user ) {
		$plugins = Tools::get_users_write_access_plugins( $user );

		$plugins = array_map( function( $slug ) {
			return Plugin_Directory::get_plugin_post( $slug );
		}, $plugins );

		usort( $plugins, function( $a, $b ) {
			return strtotime( $b->last_updated ) <=> strtotime( $a->last_updated );
		} );

		if ( ! $plugins ) {
			printf(
				'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
				// TODO text.
				__( 'You have no plugins.', 'wporg-plugins')
			);
			// TODO? wp_safe_redirect( home_url() ); ?
		} else if ( ! self::can_access() ) {
			printf(
				'<div class="plugin-notice notice notice-info notice-alt"><p>%s</p></div>',
				sprintf(
					/* translators: %s: URL */
					__( 'Check your email for an access link, or <a href="%s">request a new email</a> to perform actions.', 'wporg-plugins'),
					self::generate_access_url() // TODO..
				)
			);

			// Hide the actions columns, as they'll be empty.
			echo '<style>table.plugin-releases-listing tr > .actions { display: none; }</style>';
		}

		$not_enabled = [];
		foreach ( $plugins as $plugin ) {
			if ( ! self::single_plugin_row( $plugin ) ) {
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
	}

	static function single_plugin_row( $plugin, $include_header = true ) {
		$confirmations_required = $plugin->release_confirmation;
		$confirmed_releases     = get_post_meta( $plugin->ID, 'confirmed_releases', true ) ?: [];

		if ( ! $confirmations_required && ! $confirmed_releases ) {
			return false;
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
				<th class="actions">Actions</th>
		</thead>';

		if ( ! $confirmed_releases ) {
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
					<td class="actions">%s</td>
				</tr>',
				sprintf(
					'<a href="https://plugins.trac.wordpress.org/browser/%s/tags/%s/">%s</a>',
					$plugin->post_name,
					esc_html( $tag ), esc_html( $data['version'] )
				),
				esc_attr( gmdate( 'Y-m-d H:i:s', $data['date'] ) ),
				esc_html( sprintf( __( '%s ago', 'wporg-plugins' ), human_time_diff( $data['date'] ) ) ),
				esc_html( implode( ', ', (array) $data['committer'] ) ),
				self::get_approval_text( $plugin, $data ),
				self::get_actions( $plugin, $data )
			);
		}
		echo '</table>';

		// So we know this output something.
		return true;
	}

	static function not_authorized() {
		return 'Please login and follow link in your emailz.';
	}

	static function get_approval_text( $plugin, $data ) {
		ob_start();

		$confirmations = $data['confirmations'] ?? [];

		foreach ( $confirmations as $who => $time ) {
			$user = get_user_by( 'slug', $who );
			printf(
				'<span title="%s">%s</span><br>',
				esc_attr( gmdate( 'Y-m-d H:i:s', $time ) ),
				sprintf(
					/* translators: 1: Username, 2: '5 hours' */
					__( 'Approved by %1$s, %2$s ago', 'wporg-plugins' ),
					$user->display_name ?: $user->user_nicename,
					human_time_diff( $time )
				)
			);
		}

		if ( !empty( $data['confirmed'] ) || count( $confirmations ) >= $data['confirmations_required'] ) {
			_e( 'Release confirmed.', 'wporg-plugins' );
		} else {
			printf(
				'%s of %s required confirmations received.',
				count( $confirmations ),
				$data['confirmations_required']
			);
		}

		return ob_get_clean();
	}

	static function get_actions( $plugin, $data ) {
		$buttons = [];

		if ( ! self::can_access() ) {
			// User is not accessing with a valid token.
			return '';
		}


		if ( $data['confirmations_required'] && count( $data['confirmations'] ) < $data['confirmations_required'] ) {
			if ( isset( $data['confirmations'][ wp_get_current_user()->user_login ] ) ) {
				$buttons[] = sprintf(
					'<button class="button button-secondary disabled approve-release">%s</button>',
					'Already confirmed'
				);
			} else {
				$buttons[] = sprintf(
					'<a href="%s" class="button button-primary approve-release">%s</a>',
					Template::get_release_confirmation_link( $data['tag'] ),
					esc_attr( $plugin->post_name ),
					'Confirm'
				);
			}
		} else {
			$buttons[] = sprintf(
				'<button class="button button-secondary disabled approve-release">%s</button>',
				'Already confirmed'
			);
		}

		return implode( ' ', $buttons );
	}

	static function can_access() {
		static $can_access = null;
		if ( ! is_null( $can_access ) ) {
			return $can_access;
		}

		// Assume no access.
		$can_access = false;

		// Must be logged in..
		if ( ! is_user_logged_in() ) {
			return false;
		}

		// Must have an access token..
		$access_token = '';
		if ( isset( $_REQUEST['access_token'] ) ) {
			$access_token = base64_decode( $_REQUEST['access_token'] );
		} else if ( isset( $_COOKIE[ self::COOKIE ] ) ) {
			$access_token = $_COOKIE[ self::COOKIE ];
		}

		if ( ! $access_token ) {
			return false;
		}

		$user  = wp_get_current_user();
		list( $user_id, $hash, $expire ) = explode( ',', $access_token, 3 );

		if ( $user_id != $user->ID || $expire < time() ) {
			setcookie( self::COOKIE, false, time() - DAY_IN_SECONDS );

			return false;
		}

		$expected_hash = self::generate_access_token( $user, $expire );

		if ( ! hash_equals( $expected_hash, $hash ) ) {
			return false;
		}

		// Convert GET tokens to Cookie tokens.
		if ( isset( $_REQUEST['access_token'] ) ) {
			setcookie( self::COOKIE, $access_token, $expire, '/plugins/', 'wordpress.org', true, true );

			wp_safe_redirect( remove_query_arg( 'access_token' ) );
			die();
		}

		return $can_access = true;
	}

	static function generate_access_token( $user, $valid_until ) {
		$pass_frag     = substr( $user->user_pass, 8, 4 );
		$key           = wp_hash( $user->ID . '|' . $pass_frag . '|' . $valid_until );
		$expected_hash = hash_hmac( 'sha256', $user->ID . '|' . $valid_until, $key );

		return $expected_hash;
	}

	static function generate_access_url( $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}
		if ( ! $user || ! $user->exists() ) {
			return false;
		}

		$expire = time() + HOUR_IN_SECONDS; // TODO DAY
		$hash   = self::generate_access_token( $user, $expire );

		$token  = base64_encode( "{$user->ID},{$hash},{$expire}" );

		// TODO: hardcoded url..
		return add_query_arg( 'access_token', $token, home_url( '/developers/releases/' ) );
	}

	static function template_redirect() {
		$post = get_post();
		if ( ! $post || ! is_page() || ! has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			return;
		}

		// This page requires login.
		if ( ! is_user_logged_in() ) {
			// Migrate any request token to a cookie.
			if ( isset( $_REQUEST['access_token'] ) ) {
				setcookie( self::COOKIE, $_REQUEST['access_token'], $valid_until, '/plugins/', 'wordpress.org', true, true );
			}

			wp_safe_redirect( wp_login_url( get_permalink() ?: home_url() ) );
			exit;
		}

		// Check auth this will set the static var for later, and it might also perform a redirect.
		self::can_access();

		// A page with this shortcode has no need to be indexed.
		add_filter( 'wporg_noindex_request', '__return_true' );
	}
}
