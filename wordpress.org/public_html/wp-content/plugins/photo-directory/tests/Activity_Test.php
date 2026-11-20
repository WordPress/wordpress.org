<?php
/**
 * Tests the profile activity sent when a photo is moderated.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Photo_Directory\Activity;
use WordPressdotorg\Photo_Directory\Registrations;

/**
 * Tests for the `Activity` class.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Activity_Test extends TestCase {

	/**
	 * The contributor of the photo.
	 *
	 * @var int
	 */
	private int $author_id = 0;

	/**
	 * The moderator of the photo.
	 *
	 * @var int
	 */
	private int $moderator_id = 0;

	/**
	 * The photo post.
	 *
	 * @var WP_Post
	 */
	private WP_Post $photo;

	/**
	 * Creates a contributor, a moderator and a photo awaiting moderation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->author_id    = $this->create_user( 'activity-author' );
		$this->moderator_id = $this->create_user( 'activity-moderator' );

		$post_id = wp_insert_post(
			array(
				'post_type'   => Registrations::get_post_type(),
				'post_status' => 'pending',
				'post_title'  => '6766ab68f5',
				'post_author' => $this->author_id,
			)
		);
		$this->assertIsInt( $post_id );

		$this->photo = get_post( $post_id );
	}

	/**
	 * Removes what the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		wp_delete_post( $this->photo->ID, true );
		wp_delete_user( $this->author_id );
		wp_delete_user( $this->moderator_id );

		parent::tearDown();
	}

	/**
	 * Creates a user.
	 *
	 * @param string $login User login.
	 * @return int User ID.
	 */
	private function create_user( string $login ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_pass'  => wp_generate_password(),
				'user_email' => $login . '@example.org',
			)
		);
		$this->assertIsInt( $user_id );

		return $user_id;
	}

	/**
	 * Approving a photo is a review and an approval, credited to the moderator.
	 *
	 * @return void
	 */
	public function test_approval(): void {
		$this->assertTrue( Activity::is_review( 'publish', 'pending', $this->photo ) );
		$this->assertTrue( Activity::is_approval( 'publish', 'pending', $this->photo ) );
		$this->assertSame( $this->moderator_id, Activity::get_reviewer_id( 'publish', 'pending', $this->photo, $this->moderator_id ) );
	}

	/**
	 * Rejecting a photo is a review credited to the moderator, but not an approval.
	 *
	 * @return void
	 */
	public function test_rejection(): void {
		$this->assertTrue( Activity::is_review( 'reject', 'pending', $this->photo ) );
		$this->assertFalse( Activity::is_approval( 'reject', 'pending', $this->photo ) );
		$this->assertSame( $this->moderator_id, Activity::get_reviewer_id( 'reject', 'pending', $this->photo, $this->moderator_id ) );
	}

	/**
	 * A photo published without a current user, like from WP-CLI, is still an approval, but no one is credited with the review.
	 *
	 * @return void
	 */
	public function test_approval_without_moderator(): void {
		$this->assertTrue( Activity::is_approval( 'publish', 'pending', $this->photo ) );
		$this->assertSame( 0, Activity::get_reviewer_id( 'publish', 'pending', $this->photo, 0 ) );
	}

	/**
	 * Publishing one's own photo is an approval, but isn't credited as a review.
	 *
	 * @return void
	 */
	public function test_own_photo_is_not_credited_as_review(): void {
		$this->assertTrue( Activity::is_approval( 'publish', 'pending', $this->photo ) );
		$this->assertSame( 0, Activity::get_reviewer_id( 'publish', 'pending', $this->photo, $this->author_id ) );
	}

	/**
	 * Changes that don't approve or reject a photo for the first time are neither reviews nor approvals.
	 *
	 * @return void
	 */
	public function test_other_transitions(): void {
		$transitions = array(
			array( 'publish', 'publish' ),
			array( 'reject', 'publish' ),
			array( 'publish', 'reject' ),
			array( 'pending', 'new' ),
			array( 'trash', 'pending' ),
		);

		foreach ( $transitions as list( $new_status, $old_status ) ) {
			$label = "$old_status to $new_status";

			$this->assertFalse( Activity::is_review( $new_status, $old_status, $this->photo ), $label );
			$this->assertFalse( Activity::is_approval( $new_status, $old_status, $this->photo ), $label );
			$this->assertSame( 0, Activity::get_reviewer_id( $new_status, $old_status, $this->photo, $this->moderator_id ), $label );
		}
	}

	/**
	 * Other post types are neither reviews nor approvals.
	 *
	 * @return void
	 */
	public function test_other_post_types(): void {
		$post = clone $this->photo;

		$post->post_type = 'post';

		$this->assertFalse( Activity::is_review( 'publish', 'pending', $post ) );
		$this->assertFalse( Activity::is_approval( 'publish', 'pending', $post ) );
		$this->assertSame( 0, Activity::get_reviewer_id( 'publish', 'pending', $post, $this->moderator_id ) );
	}

	/**
	 * The published photo entry links to the photo, by its name.
	 *
	 * @return void
	 */
	public function test_published_args(): void {
		wp_update_post(
			array(
				'ID'          => $this->photo->ID,
				'post_status' => 'publish',
			)
		);
		$photo     = get_post( $this->photo->ID );
		$permalink = get_permalink( $photo );
		$args      = Activity::get_published_args( $photo );

		$this->assertSame( $photo->ID, $args['item_id'] );
		$this->assertSame( $permalink, $args['primary_link'] );
		$this->assertSame(
			'Published photo <a href="' . esc_url( $permalink ) . '">6766ab68f5</a> on <a href="https://wordpress.org/photos/">wordpress.org/photos/</a>.',
			$args['message']
		);
	}
}
