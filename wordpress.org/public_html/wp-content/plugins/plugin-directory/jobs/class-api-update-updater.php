<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API;
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
		$existing_row     = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT version, meta FROM {$wpdb->prefix}update_source WHERE plugin_slug = %s",
				$post->post_name
			)
		);
		$existing_version = (string) ( $existing_row->version ?? '' );

		$release_delay = (int) ( $release['release_delay'] ?? 0 );

		/*
		 * Hold a blocked version out of the row: the previously served version keeps
		 * being served, and the deferred serve is cancelled rather than postponed.
		 * Status changes still reach the row right away.
		 */
		if ( self::is_release_blocked( $release ) && $existing_version !== (string) $version ) {
			wp_clear_scheduled_hook( "release_to_update_api:{$post->post_name}" );

			if ( $existing_row ) {
				self::update_row_availability( $post, $existing_row->meta );
			}

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
		 *
		 * Only the version bump waits for the cooldown: a status change made
		 * mid-cooldown (a closure, a reopen) reaches the existing row right away,
		 * while it keeps serving the previous release's data. Until the cooldown
		 * expires, cron_trigger() keeps re-selecting the plugin and this write
		 * repeats as a no-op.
		 */
		if ( $release_delay && $existing_version !== (string) $version ) {
			$cooldown_until = $release_time + $release_delay;
			if ( $cooldown_until > time() ) {
				self::queue_release_to_update_api( $post->post_name, $cooldown_until );

				if ( $existing_row ) {
					self::update_row_availability( $post, $existing_row->meta );
				}

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

		$meta = array_merge( $meta, self::get_close_meta( $post ) );

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
			'available'        => (int) self::is_available( $post ),
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

		self::clear_plugin_caches( $plugin_slug );

		// Sync the latest version to Stats.
		if ( function_exists( '\WordPressdotorg\Stats\sync_latest_version' ) ) {
			\WordPressdotorg\Stats\sync_latest_version(
				'plugin',
				array(
					$plugin_slug => $version,
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
	 * Whether a release is being held out of `update_source` by a block.
	 *
	 * Blocks are recorded on the release meta as `release_block`, and cleared by
	 * Plugin_Directory::add_release() with `unblock => true`.
	 *
	 * @param array|bool $release The release row from Plugin_Directory::get_release(), or false.
	 * @return bool True when the release is being held out of `update_source`.
	 */
	public static function is_release_blocked( $release ) {
		return is_array( $release ) && ! empty( $release['release_block'] );
	}

	/**
	 * Hold a plugin's current version out of `update_source` until it's force-released.
	 *
	 * The counterpart to force_release(). It refuses when there is nothing to
	 * hold: no plugin, no release, the version already being served, or a hold
	 * already recorded against it. Capability checks and audit logging are the
	 * caller's.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @param array  $block       The block to record; 'blocked_at' is added here.
	 * @return bool Whether the version is held as a result.
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

		// Already live: a block can't un-ship a served version.
		if ( self::get_served_version( $plugin_slug ) === (string) $version ) {
			return false;
		}

		// Already held; recording a second block would merge it into the first.
		if ( self::is_release_blocked( $release ) ) {
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

		// Cancel a serve scheduled for cooldown-end; the row keeps the previous version.
		self::update_single_plugin( $plugin_slug );

		return true;
	}

	/**
	 * Sync the status-dependent `update_source` fields for a plugin whose
	 * version bump is deferred by a release cooldown.
	 *
	 * The row keeps serving the previous release's data; only its availability
	 * and closure meta follow the plugin's current status. `version` and
	 * `last_updated` are deliberately left untouched: the stale freshness
	 * marker keeps the plugin matching cron_trigger()'s out-of-date query, so
	 * the backup recovery path survives even when the version clauses are
	 * blinded by their 128-character truncation allowance.
	 *
	 * @param \WP_Post    $post     The plugin post.
	 * @param string|null $row_meta The row's current `meta` column value.
	 * @return bool Whether the row changed.
	 */
	protected static function update_row_availability( $post, $row_meta ) {
		global $wpdb;

		$meta = maybe_unserialize( $row_meta );
		$meta = is_array( $meta ) ? $meta : array();
		unset( $meta['closed_at'], $meta['closed_reason'] );
		$meta = array_merge( $meta, self::get_close_meta( $post ) );

		$updated = $wpdb->update(
			$wpdb->prefix . 'update_source',
			array(
				'available' => (int) self::is_available( $post ),
				// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Matches the update_source meta format.
				'meta'      => $meta ? serialize( $meta ) : '',
			),
			array( 'plugin_slug' => $post->post_name )
		);

		if ( $updated ) {
			self::clear_plugin_caches( $post->post_name );
		}

		return (bool) $updated;
	}

	/**
	 * Whether a plugin's `update_source` row should be marked available.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return bool
	 */
	protected static function is_available( $post ) {
		return in_array( $post->post_status, array( 'publish', 'disabled' ), true );
	}

	/**
	 * Return the closure fields for a plugin's `update_source` meta.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return array Empty for plugins that are not disabled or closed.
	 */
	protected static function get_close_meta( $post ) {
		$meta = array();

		if ( in_array( $post->post_status, array( 'disabled', 'closed' ), true ) ) {
			$closed_data = Template::get_close_data( $post );
			if ( $closed_data ) {
				// Close date is sometimes unknown, only include the Day of closure.
				$meta['closed_at'] = $closed_data['date'] ? gmdate( 'Y-m-d', strtotime( $closed_data['date'] ) ) : false;
				if ( $closed_data['public'] ) {
					$meta['closed_reason'] = $closed_data['reason'] ? $closed_data['reason'] : 'unknown';
				}
			}
		}

		return $meta;
	}

	/**
	 * Clear the update-check and plugin information caches for a plugin.
	 *
	 * @param string $plugin_slug The plugin slug.
	 */
	protected static function clear_plugin_caches( $plugin_slug ) {
		// ~34char prefix, Memcache limit of 255char per key.
		$plugin_details_cache_key = 'plugin_details:' . ( strlen( $plugin_slug ) > 200 ? 'md5:' . md5( $plugin_slug ) : $plugin_slug );
		wp_cache_delete( $plugin_details_cache_key, 'update-check-3' );

		// Clear plugin info caches also.
		Plugins_Info_API::flush_plugin_information_cache( $plugin_slug );
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

		Tools::audit_log(
			sprintf(
				'Force-released version %s, bypassing the %d-hour release cooldown. Reason: %s',
				$version,
				(int) ( $release['release_delay'] ?? 0 ) / HOUR_IN_SECONDS,
				$reason
			),
			$post
		);

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
