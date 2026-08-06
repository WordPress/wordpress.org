<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * Handles interfacing with the api.WordPress.org/plugin/update-check/ API.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class API_Update_Updater {

	/**
	 * The cron job to ensure all plugins in the `update_source` table are up-to-date.
	 * This cron is a backup in the event that the import doesn't trigger it correctly.
	 */
	public static function cron_trigger() {
		global $wpdb;

		// Note: `left( pm.meta_value, 128 )` is due to the short `version` field length and some plugins with absurdly long version strings.
		$out_of_date_plugins = $wpdb->get_col(
			"SELECT p.post_name
			FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}update_source u ON p.ID = u.plugin_id
				LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'version'
				LEFT JOIN {$wpdb->postmeta} pm_stable ON p.ID = pm_stable.post_id AND pm_stable.meta_key = 'stable_tag'
				LEFT JOIN {$wpdb->postmeta} pm_closed ON p.ID = pm_closed.post_id AND pm_closed.meta_key = 'plugin_closed_date'
			WHERE
				p.post_type = 'plugin'
				AND (
					p.post_status IN( 'publish', 'disabled', 'closed' ) OR
					u.plugin_id IS NOT NULL
				)
				AND (
					u.plugin_id IS NULL OR
					u.last_updated != p.post_modified OR
					( u.version != pm.meta_value AND u.version != left( pm.meta_value, 128 ) ) OR
					( u.stable_tag != pm_stable.meta_value AND u.stable_tag != left( pm_stable.meta_value, 128 ) ) OR
					( u.available = 1 AND p.post_status NOT IN( 'publish', 'disabled' ) ) OR
					( u.available = 0 AND p.post_status IN( 'publish', 'disabled' ) ) OR
					(
						pm_closed.meta_value IS NOT NULL AND (
							u.meta NOT LIKE '%closed_at%' OR
							(
								u.meta NOT LIKE '%closed_reason%' AND
								DATE_ADD( pm_closed.meta_value, INTERVAL 60 DAY ) <= NOW()
							)
						)
					)
				)"
		);

		if ( ! $out_of_date_plugins ) {
			return;
		}

		foreach ( $out_of_date_plugins as $plugin_slug ) {
			if ( ! self::update_single_plugin( $plugin_slug ) ) {
				// If the update failed, but yet we know the DB data differs, clear cached data and try again.
				$post = Plugin_Directory::get_plugin_post( $plugin_slug );
				clean_post_cache( $post->ID );
				self::update_single_plugin( $plugin_slug );
			}
		}
	}

	/**
	 * Updates a single plugins `update_source` data.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return bool
	 */
	public static function update_single_plugin( $plugin_slug ) {
		global $wpdb;
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );

		if ( ! $post || ! in_array( $post->post_status, array( 'publish', 'disabled', 'closed' ) ) ) {
			$wpdb->delete( $wpdb->prefix . 'update_source', compact( 'plugin_slug' ) );
			wp_clear_scheduled_hook( "release_to_update_api:{$plugin_slug}" );
			return true;
		}

		$version          = get_post_meta( $post->ID, 'version', true );
		$requires_plugins = get_post_meta( $post->ID, 'requires_plugins', true );
		$release          = Plugin_Directory::get_release( $post, $version );
		$release_time     = self::compute_release_time( $post, $release );
		$existing_version = self::get_served_version( $post->post_name );

		$release_delay = (int) ( $release['release_delay'] ?? 0 );

		/*
		 * Defer new versions still inside the cooldown; the existing `update_source` row keeps
		 * serving the previous version meanwhile. The deferred cron fires at exactly
		 * $cooldown_until, so this gate is always false when called from cron_trigger_release().
		 */
		if ( $release_delay && $existing_version !== (string) $version ) {
			$cooldown_until = $release_time + $release_delay;
			if ( $cooldown_until > time() ) {
				self::queue_release_to_update_api( $post->post_name, $cooldown_until );
				return true;
			}
		}

		// Anchor to now: phased_rollout()'s 24h window measures from public availability, not the commit.
		if ( $existing_version !== (string) $version ) {
			$release_time = time();
		}

		$meta = array(
			'release_time'    => $release_time,
			'last_version'    => $post->last_version ?? '',
			'last_stable_tag' => $post->last_stable_tag ?? '',
		);

		if ( in_array( $post->post_status, array( 'disabled', 'closed' ) ) ) {
			$closed_data = Template::get_close_data( $post );
			if ( $closed_data ) {
				// Close date is sometimes unknown, only include the Day of closure.
				$meta['closed_at'] = $closed_data['date'] ? gmdate( 'Y-m-d', strtotime( $closed_data['date'] ) ) : false;
				if ( $closed_data['public'] ) {
					$meta['closed_reason'] = $closed_data['reason'] ?: 'unknown';
				}
			}
		}

		// Add phased rollout strategy data if needed.
		if ( $release && ! empty( $release['rollout_strategy'] ) ) {
			$meta['rollout'] = array(
				'strategy' => $release['rollout_strategy'],
			);
		}

		// Clear any leftover deferred schedule so the cron table doesn't grow.
		wp_clear_scheduled_hook( "release_to_update_api:{$post->post_name}" );

		$data = array(
			'plugin_id'        => $post->ID,
			'plugin_slug'      => $post->post_name,
			'available'        => (int) in_array( $post->post_status, array( 'publish', 'disabled' ) ),
			'version'          => $version,
			'stable_tag'       => get_post_meta( $post->ID, 'stable_tag', true ),
			'plugin_name'      => strip_tags( get_post_meta( $post->ID, 'header_name', true ) ),
			'plugin_name_san'  => sanitize_title_with_dashes( strip_tags( get_post_meta( $post->ID, 'header_name', true ) ) ),
			'plugin_author'    => strip_tags( get_post_meta( $post->ID, 'header_author', true ) ),
			'tested'           => get_post_meta( $post->ID, 'tested', true ),
			'requires'         => get_post_meta( $post->ID, 'requires', true ),
			'requires_php'     => get_post_meta( $post->ID, 'requires_php', true ),
			'requires_plugins' => $requires_plugins ? serialize( $requires_plugins ) : '',
			'upgrade_notice'   => get_post_meta( $post->ID, 'upgrade_notice', true )[ $version ] ?? '',
			'assets'           => serialize( self::get_plugin_assets( $post ) ),
			'meta'             => $meta ? serialize( $meta ) : '',
			'last_updated'     => $post->post_modified,
		);

		if (
			! $wpdb->update( $wpdb->prefix . 'update_source', $data, array( 'plugin_slug' => $post->post_name ) ) &&
			! $wpdb->get_var( $wpdb->prepare( "SELECT `plugin_slug` FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s", $post->post_name ) )
		) {
			if ( ! $wpdb->insert( $wpdb->prefix . 'update_source', $data ) ) {
				return false;
			}
		}

		// ~34char prefix, Memcache limit of 255char per key.
		$plugin_details_cache_key = 'plugin_details:' . ( strlen( $plugin_slug ) > 200 ? 'md5:' . md5( $plugin_slug ) : $plugin_slug );
		wp_cache_delete( $plugin_details_cache_key, 'update-check-3' );

		// Clear plugin info caches also
		if ( defined( 'GLOTPRESS_LOCALES_PATH' ) && GLOTPRESS_LOCALES_PATH ) {
			require_once GLOTPRESS_LOCALES_PATH;

			$locales = array_filter( array_values( wp_list_pluck( \GP_Locales::locales(), 'wp_locale' ) ) );

			foreach ( $locales as $locale ) {
				$cache_key = 'plugin_information:'
					. ( strlen( $plugin_slug ) > 200 ? 'md5:' . md5( $plugin_slug ) : $plugin_slug )
					. ":{$locale}";
				wp_cache_delete( $cache_key, 'plugin_api_info' );
			}
		}

		// Sync the latest version to Stats.
		if ( function_exists( '\WordPressdotorg\Stats\sync_latest_version' ) ) {
			\WordPressdotorg\Stats\sync_latest_version(
				'plugin', 
				array(
					$plugin_slug => $version
				)
			);
		}

		return true;
	}

	/**
	 * The version currently served from `update_source`.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return string The served version, or '' when the plugin isn't in `update_source`.
	 */
	public static function get_served_version( $plugin_slug ) {
		global $wpdb;

		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT version FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s",
				$plugin_slug
			)
		);
	}

	/**
	 * Determine the release timestamp for a plugin version.
	 *
	 * Falls back through the commit timestamp on the plugin post, and is replaced by the
	 * latest committer-confirmation time when release confirmations are required (the
	 * version isn't really "released" until the last confirmation lands).
	 *
	 * @param \WP_Post   $post    The plugin post.
	 * @param array|bool $release The release row from Plugin_Directory::get_release(), or false.
	 * @return int Unix timestamp.
	 */
	public static function compute_release_time( $post, $release ) {
		$release_time = strtotime( $post->version_date ? $post->version_date : $post->post_modified );

		if (
			$release &&
			$release['confirmations_required'] &&
			$release['confirmations']
		) {
			$release_time = max( $release['confirmations'] );
		}

		return $release_time;
	}

	/**
	 * Schedule a deferred release-to-update-api cron event for a plugin, replacing
	 * any earlier event so a follow-up commit fully resets the cooldown window.
	 *
	 * @param string $plugin_slug    The plugin slug.
	 * @param int    $cooldown_until Unix timestamp when the deferred event should fire.
	 */
	public static function queue_release_to_update_api( $plugin_slug, $cooldown_until ) {
		wp_clear_scheduled_hook( "release_to_update_api:{$plugin_slug}" );
		wp_schedule_single_event( $cooldown_until, "release_to_update_api:{$plugin_slug}" );
	}

	/**
	 * Cron handler for `release_to_update_api:{slug}`. Fires when the cooldown
	 * expires; writes the new version to `update_source` immediately. The slug
	 * is recovered from the dynamic hook name so no args need flow through cron.
	 */
	public static function cron_trigger_release() {
		list( , $plugin_slug ) = explode( ':', current_filter(), 2 );
		self::update_single_plugin( $plugin_slug );
	}

	/**
	 * Write a version to `update_source` now, without waiting out its release delay. Used by
	 * reviewers force-releasing from wp-admin and by a clean Gandalf scan; what separates the
	 * two is $reason, not the behaviour.
	 *
	 * $version must still be the plugin's current version — callers evaluated it earlier, and
	 * serving a stale one would skip the wait on a release nobody looked at. Authentication
	 * and capability checks are the caller's.
	 *
	 * @param string   $plugin_slug The plugin slug.
	 * @param string   $version     The version that was evaluated.
	 * @param string   $reason      Free-text reason recorded in the audit log.
	 * @param \WP_User $user        The acting user. Defaults to the current user.
	 * @return bool True when the version was served.
	 */
	public static function serve_release_now( $plugin_slug, $version, $reason, $user = false ) {
		if ( ! $version || ! $reason ) {
			return false;
		}

		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		// A newer release landed since the caller evaluated this version.
		$current_version = (string) get_post_meta( $post->ID, 'version', true );
		if ( $current_version !== (string) $version ) {
			return false;
		}

		$release = Plugin_Directory::get_release( $post, $version );
		if ( ! $release ) {
			return false;
		}

		// Nothing to skip: no delay was captured at release creation.
		$release_delay = (int) ( $release['release_delay'] ?? 0 );
		if ( ! $release_delay ) {
			return false;
		}

		// Already served: the delay elapsed on its own; leave `update_source` and its `release_time` untouched.
		if ( self::get_served_version( $post->post_name ) === (string) $version ) {
			return false;
		}

		Tools::audit_log(
			sprintf(
				'Served version %1$s immediately, skipping the %2$d-hour release delay. Reason: %3$s',
				$version,
				(int) round( $release_delay / HOUR_IN_SECONDS ),
				$reason
			),
			$post,
			$user
		);

		Plugin_Directory::add_release(
			$post,
			array(
				'tag'           => $release['tag'],
				'release_delay' => 0,
			)
		);

		return self::update_single_plugin( $plugin_slug );
	}

	static function get_plugin_assets( $post ) {
		$icons = $banners = $banners_rtl = array();

		$raw_icons   = Template::get_plugin_icon( $post, 'raw' );
		$raw_banners = Template::get_plugin_banner( $post, 'raw_with_rtl' );

		// Banners
		if ( !empty( $raw_banners['banner_2x'] ) ) {
			$banners['2x'] = $raw_banners['banner_2x'];
		}
		if ( !empty( $raw_banners['banner'] ) ) {
			$banners['1x'] = $raw_banners['banner'];
		}

		// RTL Banners (get_plugin_banner 'raw_with_rtl' returns these)
		if ( !empty( $raw_banners['banner_2x_rtl'] ) ) {
			$banners_rtl['2x'] = $raw_banners['banner_2x_rtl'];
		}
		if ( !empty( $raw_banners['banner_rtl'] ) ) {
			$banners_rtl['1x'] = $raw_banners['banner_rtl'];
		}

		// Icons.
		if ( !empty( $raw_icons['icon_2x'] ) ) {
			$icons['2x'] = $raw_icons['icon_2x'];
		}
		if ( !empty( $raw_icons['icon'] ) ) {
			$icons['1x'] = $raw_icons['icon'];
		}
		if ( !empty( $raw_icons['svg'] ) ) {
			$icons['svg'] = $raw_icons['svg'];
		}
		if ( !empty( $raw_icons['generated'] ) ) {
			// Geopattern SVG will be in 'icon':
			$icons['default'] = $raw_icons['icon'];

			// Don't set the `1x` field when it's a geopattern icon
			unset( $icons['1x'] );
		}

		return (object) compact( 'icons', 'banners', 'banners_rtl' );
	}

}

/*
CREATE TABLE `{$prefix}_update_source` (
  `plugin_id` bigint(20) unsigned NOT NULL,
  `plugin_slug` varchar(255) NOT NULL DEFAULT '',
  `available` tinyint(4) NOT NULL,
  `version` varchar(128) NOT NULL DEFAULT '0.0',
  `stable_tag` varchar(128) NOT NULL DEFAULT 'trunk',
  `plugin_name` varchar(255) NOT NULL DEFAULT '',
  `plugin_name_san` varchar(255) NOT NULL DEFAULT '',
  `plugin_author` varchar(255) NOT NULL DEFAULT '',
  `tested` varchar(128) NOT NULL DEFAULT '',
  `requires` varchar(128) NOT NULL DEFAULT '',
  `requires_php` varchar(128) NOT NULL DEFAULT '',
  `requires_plugins` text NOT NULL DEFAULT '',
  `upgrade_notice` text,
  `assets` text DEFAULT NULL,
  `meta` text DEFAULT NULL,
  `last_updated` datetime NOT NULL,
  PRIMARY KEY (`plugin_id`),
  UNIQUE KEY `plugin_slug` (`plugin_slug`),
  KEY `plugin_name` (`plugin_name`),
  KEY `plugin_name_san` (`plugin_name_san`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;
*/
