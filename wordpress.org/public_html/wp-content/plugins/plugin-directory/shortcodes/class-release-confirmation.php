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

	/**
	 * @return string
	 */
	static function display() {
		if ( ! self::can_access() ) {
			return self::not_authorized();
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

		echo '<table class="widefat">
		<thead>
			<tr>
				<th>Version</th>
				<th>Date</th>
				<th>Committer</th>
				<th>Approval</th>
				<th>Actions</th>
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
					<td>%s</td>
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

		if ( count( $confirmations ) >= $data['confirmations_required'] ) {
			printf(
				__( '%s confirmations recieved.', 'wporg-plugins' ),
				count( $confirmations )
			);
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
		// TODO: require timed url, etc.
		return is_user_logged_in();
	}
}
