<?php
/**
 * Tests the Support Forums policy for forum-role audit notes.
 *
 * @package support-forums
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use WordPressdotorg\Forums\User_Notes;

/** Preserve the note policy while using bbPress's actual previous forum role. */
#[Group( 'ms-required' )]
class Role_Change_Audit_Test extends WP_UnitTestCase {

	/** @var array Original request context. */
	private $old_server;

	/** @var int User making the role changes. */
	private $actor_id;

	/** @var int User passed to the note-text filter. */
	private $filtered_user_id;

	/** Set up a moderator and deterministic request context. */
	public function setUp(): void {
		$this->old_server = $_SERVER;
		parent::setUp();
		$this->skipWithoutMultisite();

		$this->actor_id = $this->create_user( 'bbp_moderator' );
		wp_set_current_user( $this->actor_id );
	}

	/** Restore request context and temporary filters. */
	public function tearDown(): void {
		remove_filter( 'wporg_bbp_forum_role_changed_note_text', array( $this, 'add_reason' ) );
		$_SERVER = $this->old_server;

		parent::tearDown();
	}

	/**
	 * Keep Core's annotation lookup compatible with PHPUnit 11.
	 *
	 * This class uses attributes and has no annotation-based expectations.
	 *
	 * @return array Empty annotation groups expected by Core's test framework.
	 */
	public function getAnnotations(): array {
		return array(
			'class'  => array(),
			'method' => array(),
		);
	}

	/**
	 * Set the starting role after bbPress applies its registration default.
	 *
	 * @param string|false $role Initial forum role, or false for no role.
	 * @return int User ID.
	 */
	private function create_user( $role ) {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		get_userdata( $user_id )->set_role( $role ? $role : 'subscriber' );
		return $user_id;
	}

	/**
	 * All supported role assignments retain their exact note text and return value.
	 *
	 * @param string|false $old_role Initial forum role.
	 * @param string       $new_role Requested forum role.
	 * @param string|false $text     Expected note before its source context.
	 */
	#[DataProvider( 'role_changes' )]
	public function test_role_assignment_notes( $old_role, $new_role, $text ) {
		$user_id = $this->create_user( $old_role );

		$this->assertSame( $old_role, bbp_get_user_role( $user_id ) );
		$this->assertSame( $old_role === $new_role ? false : $new_role, bbp_set_user_role( $user_id, $new_role ) );
		$this->assertSame( $new_role, bbp_get_user_role( $user_id ) );

		$notes = get_user_meta( $user_id, User_Notes::META, true );
		if ( false === $text ) {
			$this->assertEmpty( $notes );
			return;
		}

		$this->assertCount( 1, $notes );
		$this->assertSame( $text . ' via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
		$this->assertSame( get_current_blog_id(), $notes[1]->site_id );
		$this->assertSame( wp_get_current_user()->user_nicename, $notes[1]->moderator );
	}

	/** @return array Role assignments and their established audit text. */
	public static function role_changes(): array {
		return array(
			'none to participant'        => array( false, 'bbp_participant', false ),
			'none to keymaster'          => array( false, 'bbp_keymaster', 'Forum role changed to bbp_keymaster' ),
			'none to moderator'          => array( false, 'bbp_moderator', 'Forum role changed to bbp_moderator' ),
			'none to spectator'          => array( false, 'bbp_spectator', 'Forum role changed to bbp_spectator' ),
			'none to blocked'            => array( false, 'bbp_blocked', 'Forum role changed to bbp_blocked' ),
			'participant to participant' => array( 'bbp_participant', 'bbp_participant', false ),
			'participant to keymaster'   => array( 'bbp_participant', 'bbp_keymaster', 'Forum role changed to bbp_keymaster' ),
			'participant to moderator'   => array( 'bbp_participant', 'bbp_moderator', 'Forum role changed to bbp_moderator' ),
			'participant to spectator'   => array( 'bbp_participant', 'bbp_spectator', 'Forum role changed to bbp_spectator' ),
			'participant to blocked'     => array( 'bbp_participant', 'bbp_blocked', 'Forum role changed to bbp_blocked' ),
			'keymaster to participant'   => array( 'bbp_keymaster', 'bbp_participant', 'Forum role changed to bbp_participant from bbp_keymaster' ),
			'keymaster to keymaster'     => array( 'bbp_keymaster', 'bbp_keymaster', false ),
			'keymaster to moderator'     => array( 'bbp_keymaster', 'bbp_moderator', 'Forum role changed to bbp_moderator from bbp_keymaster' ),
			'keymaster to spectator'     => array( 'bbp_keymaster', 'bbp_spectator', 'Forum role changed to bbp_spectator from bbp_keymaster' ),
			'keymaster to blocked'       => array( 'bbp_keymaster', 'bbp_blocked', 'Forum role changed to bbp_blocked from bbp_keymaster' ),
			'moderator to participant'   => array( 'bbp_moderator', 'bbp_participant', 'Forum role changed to bbp_participant from bbp_moderator' ),
			'moderator to keymaster'     => array( 'bbp_moderator', 'bbp_keymaster', 'Forum role changed to bbp_keymaster from bbp_moderator' ),
			'moderator to moderator'     => array( 'bbp_moderator', 'bbp_moderator', false ),
			'moderator to spectator'     => array( 'bbp_moderator', 'bbp_spectator', 'Forum role changed to bbp_spectator from bbp_moderator' ),
			'moderator to blocked'       => array( 'bbp_moderator', 'bbp_blocked', 'Forum role changed to bbp_blocked from bbp_moderator' ),
			'spectator to participant'   => array( 'bbp_spectator', 'bbp_participant', 'Forum role changed to bbp_participant from bbp_spectator' ),
			'spectator to keymaster'     => array( 'bbp_spectator', 'bbp_keymaster', 'Forum role changed to bbp_keymaster from bbp_spectator' ),
			'spectator to moderator'     => array( 'bbp_spectator', 'bbp_moderator', 'Forum role changed to bbp_moderator from bbp_spectator' ),
			'spectator to spectator'     => array( 'bbp_spectator', 'bbp_spectator', false ),
			'spectator to blocked'       => array( 'bbp_spectator', 'bbp_blocked', 'Forum role changed to bbp_blocked from bbp_spectator' ),
			'blocked to participant'     => array( 'bbp_blocked', 'bbp_participant', 'Forum role changed to bbp_participant from bbp_blocked' ),
			'blocked to keymaster'       => array( 'bbp_blocked', 'bbp_keymaster', 'Forum role changed to bbp_keymaster from bbp_blocked' ),
			'blocked to moderator'       => array( 'bbp_blocked', 'bbp_moderator', 'Forum role changed to bbp_moderator from bbp_blocked' ),
			'blocked to spectator'       => array( 'bbp_blocked', 'bbp_spectator', 'Forum role changed to bbp_spectator from bbp_blocked' ),
			'blocked to blocked'         => array( 'bbp_blocked', 'bbp_blocked', false ),
		);
	}

	/** Removing a forum role remains silent, including after a repeated assignment. */
	public function test_role_removals_and_empty_assignments_do_not_add_notes() {
		foreach ( array( 'bbp_participant', 'bbp_keymaster', 'bbp_moderator', 'bbp_spectator', 'bbp_blocked' ) as $role ) {
			$user_id = $this->create_user( $role );
			$this->assertFalse( bbp_set_user_role( $user_id, $role ) );
			$this->assertFalse( bbp_set_user_role( $user_id, '' ) );
			$this->assertTrue( bbp_remove_user_role( $user_id ) );
			$this->assertFalse( bbp_remove_user_role( $user_id ) );
			$this->assertEmpty( get_user_meta( $user_id, User_Notes::META, true ) );
		}
	}

	/** The reason filter still receives the changed user and affects the stored note. */
	public function test_reason_filter_is_preserved() {
		$user_id = $this->create_user( 'bbp_moderator' );
		add_filter( 'wporg_bbp_forum_role_changed_note_text', array( $this, 'add_reason' ), 10, 2 );

		$this->assertSame( 'bbp_blocked', bbp_set_user_role( $user_id, 'bbp_blocked' ) );
		$notes = get_user_meta( $user_id, User_Notes::META, true );
		$this->assertSame( $user_id, $this->filtered_user_id );
		$this->assertSame( 'A supplied reason.' . "\n\n" . 'Forum role changed to bbp_blocked from bbp_moderator via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
	}

	/** @return string Note with the caller's reason prepended. */
	public function add_reason( $text, $user ) {
		$this->filtered_user_id = $user->ID;
		return 'A supplied reason.' . "\n\n" . $text;
	}

	/** Repeated calls and changes to another user cannot leak a previous role. */
	public function test_consecutive_changes_preserve_note_merging_and_user_context() {
		$user_id  = $this->create_user( 'bbp_moderator' );
		$other_id = $this->create_user( 'bbp_spectator' );
		$source   = ' via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.';

		bbp_set_user_role( $user_id, 'bbp_blocked' );
		$this->assertFalse( bbp_set_user_role( $user_id, 'bbp_blocked' ) );
		bbp_set_user_role( $other_id, 'bbp_keymaster' );
		bbp_set_user_role( $user_id, 'bbp_participant' );

		$notes = get_user_meta( $user_id, User_Notes::META, true );
		$this->assertCount( 1, $notes );
		$this->assertSame( 'Forum role changed to bbp_blocked from bbp_moderator' . $source . "\n\n" . 'Forum role changed to bbp_participant from bbp_blocked' . $source, $notes[1]->text );
		$other_notes = get_user_meta( $other_id, User_Notes::META, true );
		$this->assertSame( 'Forum role changed to bbp_keymaster from bbp_spectator' . $source, $other_notes[1]->text );
	}

	/** Ordinary WordPress role events do not create forum audit notes. */
	public function test_wordpress_roles_do_not_create_forum_notes() {
		$user_id = $this->create_user( 'bbp_moderator' );
		$user    = get_userdata( $user_id );
		$user->add_role( 'editor' );
		$user->remove_role( 'editor' );

		$this->assertEmpty( get_user_meta( $user_id, User_Notes::META, true ) );
		bbp_set_user_role( $user_id, 'bbp_spectator' );
		$notes = get_user_meta( $user_id, User_Notes::META, true );
		$this->assertSame( 'Forum role changed to bbp_spectator from bbp_moderator via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
	}

	/** Switched-site changes retain their origin path and the destination site ID. */
	public function test_switched_site_preserves_the_request_source() {
		$user_id                = $this->create_user( false );
		$site_id                = self::factory()->blog->create();
		$_SERVER['HTTP_HOST']   = 'origin.example.org';
		$_SERVER['REQUEST_URI'] = '/tools/block/?reason=example';
		add_user_to_blog( $site_id, $user_id, 'bbp_moderator' );
		switch_to_blog( $site_id );

		try {
			$this->assertSame( 'bbp_moderator', bbp_get_user_role( $user_id ) );
			bbp_set_user_role( $user_id, 'bbp_blocked' );
			$notes = get_user_meta( $user_id, User_Notes::META, true );
			$this->assertCount( 1, $notes );
			$this->assertSame( 'Forum role changed to bbp_blocked from bbp_moderator via origin.example.org/tools/block/.', $notes[1]->text );
			$this->assertSame( $site_id, $notes[1]->site_id );
		} finally {
			restore_current_blog();
		}
	}

	/** A removed role must not leak into a later assignment from no role. */
	#[Group( 'role-audit-corrections' )]
	public function test_assignment_after_role_removal_uses_the_current_previous_role() {
		$user_id = $this->create_user( 'bbp_moderator' );
		$this->assertTrue( bbp_remove_user_role( $user_id ) );
		$this->assertSame( 'bbp_keymaster', bbp_set_user_role( $user_id, 'bbp_keymaster' ) );

		$notes = get_user_meta( $user_id, User_Notes::META, true );
		$this->assertCount( 1, $notes );
		$this->assertSame( 'Forum role changed to bbp_keymaster via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
	}

	/** Removing a WordPress role must not invent a previous forum role. */
	#[Group( 'role-audit-corrections' )]
	public function test_non_forum_role_removals_do_not_supply_the_previous_role() {
		$user_id = $this->create_user( false );
		$user    = get_userdata( $user_id );
		$user->add_role( 'editor' );
		$user->remove_role( 'editor' );
		$this->assertFalse( bbp_get_user_role( $user_id ) );
		bbp_set_user_role( $user_id, 'bbp_keymaster' );

		$notes = get_user_meta( $user_id, User_Notes::META, true );
		$this->assertSame( 'Forum role changed to bbp_keymaster via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
	}

	/** Blocking a user with multiple forum roles keeps bbPress's previous role. */
	#[Group( 'role-audit-corrections' )]
	public function test_blocking_multiple_forum_roles_uses_the_effective_previous_role() {
		$user_id = $this->create_user( 'bbp_moderator' );
		get_userdata( $user_id )->add_role( 'bbp_participant' );
		$this->assertSame( 'bbp_moderator', bbp_get_user_role( $user_id ) );
		bbp_set_user_role( $user_id, 'bbp_blocked' );

		$notes = get_user_meta( $user_id, User_Notes::META, true );
		$this->assertSame( 'Forum role changed to bbp_blocked from bbp_moderator via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
	}

	/** Nested core block actions retain the outer role and existing merge order. */
	#[Group( 'role-audit-corrections' )]
	public function test_cross_site_blocking_preserves_the_outer_previous_role() {
		$user_id                = $this->create_user( 'bbp_moderator' );
		$site_id                = self::factory()->blog->create();
		$_SERVER['HTTP_HOST']   = 'origin.example.org';
		$_SERVER['REQUEST_URI'] = '/tools/block/?reason=example';
		add_user_to_blog( $site_id, $user_id, 'bbp_moderator' );
		grant_super_admin( $this->actor_id );
		update_option( '_bbp_user_block_actions', array( 'block_sites' ) );

		try {
			bbp_set_user_role( $user_id, 'bbp_blocked' );
			$this->assertSame( 'bbp_blocked', bbp_get_user_role( $user_id ) );
			$this->assertSame( 'bbp_blocked', bbp_get_user_default_role_override( $user_id ) );
			$notes = get_user_meta( $user_id, User_Notes::META, true );
			$this->assertCount( 1, $notes );
			$this->assertSame( 'Forum role changed to bbp_blocked from bbp_moderator via origin.example.org/tools/block/.' . "\n\n" . 'Forum role changed to bbp_blocked from bbp_moderator via ' . wp_parse_url( home_url( '/' ), PHP_URL_HOST ) . '.', $notes[1]->text );
			$this->assertSame( $site_id, $notes[1]->site_id );
		} finally {
			delete_option( '_bbp_user_block_actions' );
			revoke_super_admin( $this->actor_id );
		}
	}
}
