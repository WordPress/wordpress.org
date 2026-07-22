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

		if ( self::is_release_blocked( $release ) && $existing_version !== (string) $version ) {
			wp_clear_scheduled_hook( "release_to_update_api:{$post->post_name}" );
			self::sync_availability( $post );

			return true;
		}

		/*
		 * Defer the write for new versions still inside the cooldown window. While
		 * deferred, the existing `update_source` row (carrying the previous version)
		 * continues to be served by the update API. Reviewers force-release by setting
		 * `release_delay = 0` on the release meta.
		 *
		 * The deferred cron fires at exactly $cooldown_until, so by definition this
		 * gate is false when called from cron_trigger_release() and no explicit bypass
		 * is needed.
		 */
		if ( $release_delay && $existing_version !== (string) $version ) {
			$cooldown_until = $release_time + $release_delay;
			if ( $cooldown_until > time() ) {
				self::queue_release_to_update_api( $post->post_name, $cooldown_until );
				self::sync_availability( $post );

				return true;
			}
		}

		// When publishing a new version under an active cooldown, anchor `release_time`
		// to now — that's the moment the version is actually available to sites. Keeps
		// phased_rollout()'s `manual-updates-24hr` window measuring from public availability,
		// even if the commit/confirmation was long ago because the cooldown deferred the write.
		if ( $release_delay && $existing_version !== (string) $version ) {
			$release_time = time();
		}

		$meta = array(
			'release_time'    => $release_time,
			'last_version'    => $post->last_version ?? '',
			'last_stable_tag' => $post->last_stable_tag ?? '',
		);

		$meta = array_merge( $meta, self::get_closed_meta( $post ) );

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
	 * The closure fields recorded in `update_source`'s `meta` column.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return array Empty for a plugin that is neither closed nor disabled.
	 */
	protected static function get_closed_meta( $post ) {
		if ( ! in_array( $post->post_status, array( 'disabled', 'closed' ) ) ) {
			return array();
		}

		$closed_data = Template::get_close_data( $post );
		if ( ! $closed_data ) {
			return array();
		}

		// Close date is sometimes unknown, only include the Day of closure.
		$meta = array(
			'closed_at' => $closed_data['date'] ? gmdate( 'Y-m-d', strtotime( $closed_data['date'] ) ) : false,
		);

		if ( $closed_data['public'] ) {
			$meta['closed_reason'] = $closed_data['reason'] ?: 'unknown';
		}

		return $meta;
	}

	/**
	 * Apply the plugin's availability and closure state to the `update_source` row without
	 * disturbing the version it serves.
	 *
	 * Whenever a new version is held back — by the release cooldown or by a block —
	 * update_single_plugin() returns early, because the row has to keep serving the previous
	 * version and the version-specific columns are all derived from post meta describing the
	 * held one. A status change still has to reach sites immediately though: closing a plugin
	 * whose next version is on hold must stop the current version being offered. So the
	 * availability and closure fields are written on their own, and the rest of the row is
	 * left as it is.
	 *
	 * @param \WP_Post $post The plugin post.
	 */
	protected static function sync_availability( $post ) {
		global $wpdb;

		// Fetched as a row, not a single value: `meta` is nullable, so a null there would be
		// indistinguishable from the plugin having no row at all.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT `meta` FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s",
				$post->post_name
			),
			ARRAY_A
		);

		// Nothing is being served, so there's no availability to correct.
		if ( ! $row ) {
			return;
		}

		$meta = $row['meta'] ? (array) maybe_unserialize( $row['meta'] ) : array();

		// Rebuild rather than merge, so re-opening a plugin drops the closure fields again.
		unset( $meta['closed_at'], $meta['closed_reason'] );
		$meta = array_merge( $meta, self::get_closed_meta( $post ) );

		$wpdb->update(
			$wpdb->prefix . 'update_source',
			array(
				'available' => (int) in_array( $post->post_status, array( 'publish', 'disabled' ) ),
				'meta'      => $meta ? serialize( $meta ) : '',
			),
			array( 'plugin_slug' => $post->post_name )
		);
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
	 * Whether a release is being held out of `update_source` by a block.
	 *
	 * A block is set by a reviewer, and cleared by a force-release.
	 *
	 * @param array|bool $release The release row from Plugin_Directory::get_release(), or false.
	 * @return bool True when the release is being held out of `update_source`.
	 */
	public static function is_release_blocked( $release ) {
		return is_array( $release ) && ! empty( $release['release_block'] );
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

		// A force-release also overrides a block; note that in the audit trail.
		if ( self::is_release_blocked( $release ) ) {
			Tools::audit_log(
				sprintf(
					'Force-released version %1$s, overriding the release block. Reason: %2$s',
					$version,
					$reason
				),
				$post
			);
		} else {
			Tools::audit_log(
				sprintf(
					'Force-released version %s, bypassing the %d-hour release cooldown. Reason: %s',
					$version,
					(int) ( $release['release_delay'] ?? 0 ) / HOUR_IN_SECONDS,
					$reason
				),
				$post
			);
		}

		Plugin_Directory::add_release(
			$post,
			array(
				'tag'           => $release['tag'],
				'release_delay' => 0,
				// Clear any release block so update_single_plugin() serves the version.
				'unblock'       => true,
			)
		);

		return self::update_single_plugin( $plugin_slug );
	}

	/**
	 * Hold a plugin's current version out of `update_source` until it's force-released.
	 *
	 * The counterpart to force_release(). Callers apply their own preconditions first; this
	 * only refuses when there's nothing left to hold.
	 *
	 * Capability checks must be performed by the caller.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  $block       The block to record: 'reason' and 'blocked_by'.
	 * @return bool True when the version was held, false when there was nothing to hold.
	 */
	public static function block_release( $plugin_slug, $block ) {
		$post = Plugin_Directory::get_plugin_post( $plugin_slug );
		if ( ! $post ) {
			return false;
		}

		$version = get_post_meta( $post->ID, 'version', true );
		$release = Plugin_Directory::get_release( $post, $version );

		if ( ! $release ) {
			return false;
		}

		// Already live: the version is being served, so there's nothing left to hold back.
		if ( self::get_served_version( $plugin_slug ) === (string) $version ) {
			return false;
		}

		$block['blocked_at'] = time();

		Plugin_Directory::add_release(
			$post,
			array(
				'tag'           => $release['tag'],
				'release_block' => $block,
			)
		);

		Tools::audit_log(
			sprintf(
				'Blocked version %1$s from being served to sites. Reason: %2$s',
				$version,
				$block['reason']
			),
			$post
		);

		// Re-run so a version scheduled to serve at cooldown-end is held now instead.
		self::update_single_plugin( $plugin_slug );

		return true;
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
