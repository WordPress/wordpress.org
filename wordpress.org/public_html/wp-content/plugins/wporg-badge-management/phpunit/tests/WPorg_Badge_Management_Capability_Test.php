<?php
/**
 * Tests for the badge manager capability.
 *
 * @package wporg-badge-management
 */

defined( 'ABSPATH' ) || die();

use const WordPressdotorg\Make\Badge_Management\MANAGE_BADGES_CAP;
use function WordPressdotorg\Make\Badge_Management\current_user_can_change_required_cap;
use function WordPressdotorg\Make\Badge_Management\get_required_cap;

/**
 * Covers who the required capability setting lets manage badges on a site.
 */
class WPorg_Badge_Management_Capability_Test extends WPorg_Badge_Management_TestCase {

	/**
	 * Resets the setting to its default before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		delete_option( 'wporg_profile_badge_required_cap' );
	}

	/**
	 * At the default setting, site administrators can manage badges.
	 */
	public function test_default_setting_allows_administrators() {
		$administrator = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$editor        = $this->factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertTrue( user_can( $administrator, MANAGE_BADGES_CAP ) );
		$this->assertFalse( user_can( $editor, MANAGE_BADGES_CAP ) );
	}

	/**
	 * Lowering the setting to publish_posts admits members who hold that capability through their role.
	 */
	public function test_publish_posts_setting_allows_members_who_can_publish() {
		update_option( 'wporg_profile_badge_required_cap', 'publish_posts' );

		$author      = $this->factory()->user->create( array( 'role' => 'author' ) );
		$contributor = $this->factory()->user->create( array( 'role' => 'contributor' ) );
		$subscriber  = $this->factory()->user->create( array( 'role' => 'subscriber' ) );

		$this->assertTrue( user_can( $author, MANAGE_BADGES_CAP ) );
		$this->assertFalse( user_can( $contributor, MANAGE_BADGES_CAP ) );
		$this->assertFalse( user_can( $subscriber, MANAGE_BADGES_CAP ) );
	}

	/**
	 * A user without a role on the site cannot manage badges, whichever setting is in place.
	 *
	 * The o2 Posting Access grant of publish_posts to non-members satisfies a
	 * bare capability check, so the test first confirms it is in play.
	 */
	public function test_non_member_cannot_manage_badges() {
		$non_member = $this->create_non_member();

		$this->assertTrue( user_can( $non_member, 'publish_posts' ), 'The o2 Posting Access grant should be in play for this test to mean anything.' );

		foreach ( array( 'publish_posts', 'manage_options', 'manage_network' ) as $setting ) {
			update_option( 'wporg_profile_badge_required_cap', $setting );

			$this->assertFalse( user_can( $non_member, MANAGE_BADGES_CAP ), "Non-member should not qualify under the {$setting} setting." );
		}
	}

	/**
	 * Super admins can manage badges without holding a role on the site.
	 */
	public function test_super_admin_can_manage_badges_without_a_role() {
		$super_admin = $this->create_non_member();
		$this->assertTrue( grant_super_admin( $super_admin ) );

		foreach ( array( 'publish_posts', 'manage_options', 'manage_network' ) as $setting ) {
			update_option( 'wporg_profile_badge_required_cap', $setting );

			$this->assertTrue( user_can( $super_admin, MANAGE_BADGES_CAP ), "Super admin should qualify under the {$setting} setting." );
		}
	}

	/**
	 * The manage_network setting excludes site administrators.
	 */
	public function test_manage_network_setting_excludes_administrators() {
		update_option( 'wporg_profile_badge_required_cap', 'manage_network' );

		$administrator = $this->factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertFalse( user_can( $administrator, MANAGE_BADGES_CAP ) );
	}

	/**
	 * Logged out visitors cannot manage badges.
	 */
	public function test_logged_out_visitor_cannot_manage_badges() {
		update_option( 'wporg_profile_badge_required_cap', 'publish_posts' );

		wp_set_current_user( 0 );

		$this->assertFalse( current_user_can( MANAGE_BADGES_CAP ) );
	}

	/**
	 * A stored value outside the offered choices falls back to the default.
	 */
	public function test_unknown_setting_falls_back_to_manage_options() {
		update_option( 'wporg_profile_badge_required_cap', 'edit_posts' );

		$administrator = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$editor        = $this->factory()->user->create( array( 'role' => 'editor' ) );

		$this->assertSame( 'manage_options', get_required_cap() );
		$this->assertTrue( user_can( $administrator, MANAGE_BADGES_CAP ) );
		$this->assertFalse( user_can( $editor, MANAGE_BADGES_CAP ) );

		update_option( 'wporg_profile_badge_required_cap', array( 'publish_posts' ) );

		$this->assertSame( 'manage_options', get_required_cap() );
	}

	/**
	 * Only administrators who can manage badges themselves can change the required capability.
	 */
	public function test_required_cap_is_changed_by_administrators_who_can_manage_badges() {
		$administrator = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$author        = $this->factory()->user->create( array( 'role' => 'author' ) );
		$super_admin   = $this->create_non_member();
		$this->assertTrue( grant_super_admin( $super_admin ) );

		update_option( 'wporg_profile_badge_required_cap', 'publish_posts' );

		wp_set_current_user( $author );
		$this->assertFalse( current_user_can_change_required_cap(), 'An author can manage badges but is not an administrator.' );

		wp_set_current_user( $administrator );
		$this->assertTrue( current_user_can_change_required_cap() );

		update_option( 'wporg_profile_badge_required_cap', 'manage_network' );

		wp_set_current_user( $administrator );
		$this->assertFalse( current_user_can_change_required_cap(), 'A setting that excludes administrators is not theirs to change.' );

		wp_set_current_user( $super_admin );
		$this->assertTrue( current_user_can_change_required_cap() );
	}

	/**
	 * Other capabilities pass through the mapping untouched.
	 */
	public function test_mapping_leaves_other_capabilities_alone() {
		$author = $this->factory()->user->create( array( 'role' => 'author' ) );

		$this->assertTrue( user_can( $author, 'publish_posts' ) );
		$this->assertFalse( user_can( $author, 'manage_options' ) );
	}
}
