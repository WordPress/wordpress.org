<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;
use const WordPressdotorg\Plugin_Directory\RELEASE_COOL_DOWN_DELAY;

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
	 * @param string $plugin_slug     The plugin slug.
	 * @param bool   $bypass_cooldown Whether to bypass the release cooldown gate. True when called
	 *                                from the deferred release cron, the reviewer force-release
	 *                                action, or from contexts that must publish immediately (status
	 *                                transitions, rebuild scripts). When true, `release_time` in the
	 *                                stored meta is anchored to the moment of the write rather than
	 *                                the original commit time, so the phased-rollout
	 *                                `manual-updates-24hr` window measures from public availability.
	 * @return bool
	 */
	public static function update_single_plugin( $plugin_slug, $bypass_cooldown = false ) {
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
		$existing_version = (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT version FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s",
				$post->post_name
			)
		);

		/*
		 * Defer the write for new versions still inside the cooldown window. While
		 * deferred, the existing `update_source` row (carrying the previous version)
		 * continues to be served by the update API. Callers that need immediate writes
		 * (status transitions, reviewer force-release, the deferred event firing, meta
		 * sync, rebuild) pass $bypass_cooldown = true.
		 *
		 * Skipped entirely when RELEASE_COOL_DOWN_DELAY is 0 — that's the feature-flag
		 * off switch, callers see the original commit/confirmation release_time.
		 */
		if (
			RELEASE_COOL_DOWN_DELAY &&
			! $bypass_cooldown &&
			empty( $release['force_released'] ) &&
			$existing_version !== (string) $version
		) {
			$cooldown_until = $release_time + RELEASE_COOL_DOWN_DELAY;
			if ( $cooldown_until > time() ) {
				self::queue_release_to_update_api( $post->post_name, $cooldown_until );
				return true;
			}
		}

		// When the cooldown is enabled and this write is publishing a new version, anchor
		// `release_time` to now — that's the moment the version is actually available to
		// sites. Keeps phased_rollout()'s `manual-updates-24hr` window measuring from public
		// availability, even if the commit/confirmation was long ago because the cooldown
		// deferred the write. With the cooldown disabled, keep the original semantics.
		if ( RELEASE_COOL_DOWN_DELAY && $existing_version !== (string) $version ) {
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

		// The deferred event (if any) has either fired or been pre-empted by a force-release
		// or status change. Clear any leftover schedule so the cron table doesn't grow.
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
	 * Schedule a deferred release-to-update-api cron event for a plugin.
	 *
	 * If an event is already scheduled at the desired time, this is a no-op. If a
	 * different time is scheduled (e.g. an earlier commit's cooldown), the event is
	 * rescheduled to the later time so a follow-up commit fully resets the window.
	 *
	 * @param string $plugin_slug    The plugin slug.
	 * @param int    $cooldown_until Unix timestamp when the deferred event should fire.
	 */
	public static function queue_release_to_update_api( $plugin_slug, $cooldown_until ) {
		$hook = "release_to_update_api:{$plugin_slug}";

		$existing = wp_next_scheduled( $hook, array( $plugin_slug ) );
		if ( $existing === $cooldown_until ) {
			return;
		}

		if ( $existing ) {
			wp_unschedule_event( $existing, $hook, array( $plugin_slug ) );
		}

		wp_schedule_single_event( $cooldown_until, $hook, array( $plugin_slug ) );
	}

	/**
	 * Cron handler for `release_to_update_api:{slug}`. Fires when the cooldown
	 * expires; writes the new version to `update_source` immediately.
	 *
	 * @param string $plugin_slug The plugin slug.
	 */
	public static function cron_trigger_release( $plugin_slug ) {
		self::update_single_plugin( $plugin_slug, true );
	}

	/**
	 * Reviewer force-release: clear the cooldown for a plugin's current version and
	 * write it to `update_source` immediately. Logs the action with the supplied reason.
	 *
	 * Capability checks must be performed by the caller.
	 *
	 * @param string   $plugin_slug The plugin slug.
	 * @param string   $reason      Free-text reason recorded in the audit log.
	 * @param \WP_User $user        The acting user. Defaults to the current user.
	 * @return bool True on success.
	 */
	public static function force_release( $plugin_slug, $reason, $user = null ) {
		if ( ! $user ) {
			$user = wp_get_current_user();
		}

		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$version = get_post_meta( $post->ID, 'version', true );
		$release = Plugin_Directory::get_release( $post, $version );

		if ( ! $release ) {
			return false;
		}

		Plugin_Directory::add_release(
			$post,
			array(
				'tag'                   => $release['tag'],
				'force_released'        => true,
				'force_released_by'     => $user->ID,
				'force_released_at'     => time(),
				'force_released_reason' => $reason,
			)
		);

		Tools::audit_log(
			sprintf(
				'Force-released version %s, bypassing the %d-hour release cooldown. Reason: %s',
				$version,
				RELEASE_COOL_DOWN_DELAY / HOUR_IN_SECONDS,
				$reason
			),
			$post
		);

		wp_clear_scheduled_hook( "release_to_update_api:{$plugin_slug}" );

		return self::update_single_plugin( $plugin_slug, true );
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
