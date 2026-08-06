<?php
/**
 * Shared fixture for tests about holding a release out of `update_source`.
 *
 * Seeds a published plugin serving SERVED_VERSION with NEW_VERSION committed just now and
 * waiting out a 24-hour release delay — the state every block, force-release, and cooldown
 * test starts from — and provides the helpers for reading and writing what's actually served.
 *
 * Contains no tests of its own, so it's excluded from the suite in phpunit.xml and loaded on
 * demand by the test bootstrap's autoloader when a subclass extends it.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Base fixture for release-hold tests. Carries no tests of its own.
 *
 * @group jobs
 */
abstract class Release_Block_Test_Case extends TestCase {

	/**
	 * The delay captured on the release under test.
	 */
	const DELAY = DAY_IN_SECONDS;

	/**
	 * The plugin slug under test. Subclasses override this so their fixtures can't collide.
	 */
	const SLUG = 'release-block-test';

	/**
	 * The version already being served when a test starts.
	 */
	const SERVED_VERSION = '1.0';

	/**
	 * The new version waiting out its delay, and the one tests hold back.
	 */
	const NEW_VERSION = '2.0';

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	protected $plugin;

	/**
	 * A plugin serving SERVED_VERSION, with NEW_VERSION committed just now inside its delay.
	 */
	protected function setUp(): void {
		global $wpdb;

		parent::setUp();

		// Tools::audit_log() reads this unconditionally; CLI has no remote address.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		/*
		 * post_modified is required: Plugin_Directory::filter_wp_insert_post_data() copies it
		 * from $postarr, and wp_insert_post() doesn't default it, so the insert fails without.
		 */
		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_name'         => static::SLUG,
				'post_title'        => static::SLUG,
				'post_status'       => 'publish',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertNotInstanceOf( WP_Error::class, $plugin_id );

		$this->plugin = get_post( $plugin_id );

		update_post_meta( $plugin_id, 'version', static::NEW_VERSION );
		update_post_meta( $plugin_id, 'stable_tag', static::NEW_VERSION );
		update_post_meta( $plugin_id, 'header_name', static::SLUG );
		update_post_meta( $plugin_id, 'header_author', 'WordPress' );
		update_post_meta( $plugin_id, 'version_date', gmdate( 'Y-m-d H:i:s', time() ) );

		$this->set_releases( array( $this->release() ) );

		/*
		 * Scoped to this fixture's slug rather than truncated: `update_source` is shared, and
		 * TRUNCATE would both destroy other tests' rows and force an implicit commit, taking
		 * any enclosing WP_UnitTestCase transaction with it.
		 */
		$this->unserve();
		$this->serve( static::SERVED_VERSION );
	}

	/**
	 * Remove everything setUp() created: the plugin, its meta, any audit-log notes, the
	 * `update_source` row, the deferred cron event and the faked request state. There's no
	 * transaction to roll back without WP_UnitTestCase, so state would otherwise leak.
	 */
	protected function tearDown(): void {
		wp_clear_scheduled_hook( 'release_to_update_api:' . static::SLUG );

		// Removes every comment on the post; the fixture's are all audit-log notes.
		foreach ( get_comments( array( 'post_id' => $this->plugin->ID ) ) as $note ) {
			wp_delete_comment( $note->comment_ID, true );
		}

		$this->unserve();
		wp_delete_post( $this->plugin->ID, true );

		unset( $_SERVER['REMOTE_ADDR'] );

		parent::tearDown();
	}

	/**
	 * A complete release row for NEW_VERSION: confirmed, built, inside its delay.
	 * get_releases() reads keys beyond the ones under test.
	 *
	 * @param array $overrides Values to override on the default release.
	 * @return array
	 */
	protected function release( $overrides = array() ) {
		return array_merge(
			array(
				'date'                   => time(),
				'tag'                    => static::NEW_VERSION,
				'version'                => static::NEW_VERSION,
				'zips_built'             => true,
				'confirmations'          => array(),
				'confirmed'              => true,
				'confirmations_required' => 0,
				'committer'              => array(),
				'revision'               => array(),
				'release_delay'          => static::DELAY,
			),
			$overrides
		);
	}

	/**
	 * Seed the releases meta directly: get_releases() otherwise falls back to
	 * prefill_releases_meta(), which reaches out to SVN.
	 *
	 * @param array $releases The releases to store.
	 */
	protected function set_releases( $releases ) {
		update_post_meta( $this->plugin->ID, 'releases', $releases );
	}

	/**
	 * Put a version into `update_source`, standing in for the currently-served release.
	 *
	 * @param string $version The version to serve.
	 * @param array  $meta    Values for the serialized `meta` column. Empty leaves it NULL,
	 *                        as it is for a plugin with no rollout or closure data.
	 */
	protected function serve( $version, $meta = array() ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'    => $this->plugin->ID,
				'plugin_slug'  => static::SLUG,
				'available'    => 1,
				'version'      => $version,
				'meta'         => $meta ? serialize( $meta ) : null,
				'last_updated' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Drop this plugin's `update_source` row, leaving other tests' rows alone.
	 */
	protected function unserve() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress; there is no API for it.
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_slug' => static::SLUG ) );
	}

	/**
	 * The deserialized `meta` column of the served row.
	 *
	 * @return array Empty when the column is NULL or holds no data.
	 */
	protected function get_served_meta() {
		$row = $this->get_served_row();

		if ( ! $row || ! $row['meta'] ) {
			return array();
		}

		return (array) maybe_unserialize( $row['meta'] );
	}

	/**
	 * The version currently served from `update_source`.
	 *
	 * @return string|null
	 */
	protected function get_served_version() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress, and a cached read would defeat the assertion.
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `version` FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s",
				static::SLUG
			)
		);
	}

	/**
	 * The whole `update_source` row, for assertions about availability rather than version.
	 *
	 * @return array|null
	 */
	protected function get_served_row() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- `update_source` lives outside WordPress, and a cached read would defeat the assertion.
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$wpdb->prefix}update_source` WHERE `plugin_slug` = %s",
				static::SLUG
			),
			ARRAY_A
		);
	}

	/**
	 * Move the committed version outside its delay, so the next update run would serve it.
	 */
	protected function elapse_cooldown() {
		update_post_meta( $this->plugin->ID, 'version_date', gmdate( 'Y-m-d H:i:s', time() - ( static::DELAY * 2 ) ) );
	}

	/**
	 * The block recorded against a release, if any.
	 *
	 * @param string|null $tag The release tag. Defaults to NEW_VERSION.
	 * @return array|null The `release_block` value, or null when the release isn't held.
	 */
	protected function get_release_block( $tag = null ) {
		if ( null === $tag ) {
			$tag = static::NEW_VERSION;
		}

		foreach ( (array) get_post_meta( $this->plugin->ID, 'releases', true ) as $release ) {
			if ( $tag === $release['tag'] ) {
				return $release['release_block'] ?? null;
			}
		}

		return null;
	}
}
