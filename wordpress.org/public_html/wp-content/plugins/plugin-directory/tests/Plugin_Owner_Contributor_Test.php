<?php
/**
 * Tests that an ownership change moves the owner's contributor term.
 *
 * Author queries search only the `plugin_contributors` taxonomy, so a plugin's
 * owner has to be in it for the plugin to be found under their name.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Plugin_Directory::update_owner_contributor()`.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Plugin_Owner_Contributor_Test extends TestCase {

	/**
	 * Nicenames of the users created for the test.
	 *
	 * @var string[]
	 */
	const NICENAMES = array( 'owner-contrib-previous', 'owner-contrib-new', 'owner-contrib-aa-readme' );

	/**
	 * The plugin post under test.
	 *
	 * @var int
	 */
	protected int $plugin_id = 0;

	/**
	 * User IDs created for the test, keyed by nicename.
	 *
	 * @var int[]
	 */
	protected array $user_ids = array();

	/**
	 * Creates the users and the plugin, owned by the first user.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( self::NICENAMES as $nicename ) {
			$user_id = wp_insert_user(
				array(
					'user_login'    => $nicename,
					'user_nicename' => $nicename,
					'user_pass'     => wp_generate_password(),
					'user_email'    => $nicename . '@example.org',
				)
			);
			$this->assertIsInt( $user_id );
			$this->user_ids[ $nicename ] = $user_id;
		}

		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_title'        => 'Owner Contributor Fixture',
				'post_name'         => 'owner-contributor-fixture',
				'post_status'       => 'publish',
				'post_author'       => $this->user_ids['owner-contrib-previous'],
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);
		$this->assertIsInt( $plugin_id );
		$this->plugin_id = $plugin_id;
	}

	/**
	 * Removes the plugin, users and contributor terms the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		wp_delete_post( $this->plugin_id, true );
		wp_cache_delete( 'owner-contributor-fixture', 'plugin-slugs' );

		foreach ( $this->user_ids as $nicename => $user_id ) {
			wp_delete_user( $user_id );

			$term = get_term_by( 'slug', $nicename, 'plugin_contributors' );
			if ( $term ) {
				wp_delete_term( $term->term_id, 'plugin_contributors' );
			}
		}
		$this->user_ids = array();

		parent::tearDown();
	}

	/**
	 * The new owner replaces the previous one and is listed first, and readme contributors stay.
	 *
	 * @return void
	 */
	public function test_ownership_change_moves_owner_contributor_term(): void {
		wp_set_object_terms( $this->plugin_id, array( 'owner-contrib-aa-readme', 'owner-contrib-previous' ), 'plugin_contributors' );

		wp_update_post(
			array(
				'ID'          => $this->plugin_id,
				'post_author' => $this->user_ids['owner-contrib-new'],
			)
		);

		$contributors = wp_get_object_terms(
			$this->plugin_id,
			'plugin_contributors',
			array(
				'orderby' => 'term_order',
				'fields'  => 'slugs',
			)
		);

		$this->assertSame( array( 'owner-contrib-new', 'owner-contrib-aa-readme' ), $contributors );
	}
}
