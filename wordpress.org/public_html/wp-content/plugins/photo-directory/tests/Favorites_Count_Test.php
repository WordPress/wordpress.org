<?php
/**
 * Tests the favorites count of photos.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Photo_Directory\Favorites;
use WordPressdotorg\Photo_Directory\Registrations;

/**
 * Tests that a photo's favorites count follows its favorites.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Favorites_Count_Test extends TestCase {

	/**
	 * Users created for the test.
	 *
	 * @var int[]
	 */
	private array $user_ids = array();

	/**
	 * Photos created for the test.
	 *
	 * @var int[]
	 */
	private array $post_ids = array();

	/**
	 * Removes what the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

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
		$this->user_ids[] = $user_id;

		return $user_id;
	}

	/**
	 * Publishes a photo.
	 *
	 * @return int Post ID.
	 */
	private function create_photo(): int {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Registrations::get_post_type(),
				'post_status' => 'publish',
				'post_title'  => 'Photo',
			)
		);
		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		return $post_id;
	}

	/**
	 * Counts the users who favorited a photo, and caches that count for sorting as favorites change.
	 *
	 * @return void
	 */
	public function test_count_follows_favorites(): void {
		$photo  = $this->create_photo();
		$first  = $this->create_user( 'favorites-count-first' );
		$second = $this->create_user( 'favorites-count-second' );

		// The first user also favorites other photos, so their term count differs from the photo's.
		Favorites::favorite_photo( $this->create_photo(), $first );
		Favorites::favorite_photo( $this->create_photo(), $first );
		Favorites::favorite_photo( $photo, $first );
		Favorites::favorite_photo( $photo, $second );

		$this->assertSame( 2, Favorites::count_photo_favorites( $photo ) );
		$this->assertSame( '2', get_post_meta( $photo, Favorites::META_KEY_FAVORITES_COUNT, true ) );

		Favorites::unfavorite_photo( $photo, $first );
		$this->assertSame( '1', get_post_meta( $photo, Favorites::META_KEY_FAVORITES_COUNT, true ) );

		Favorites::unfavorite_photo( $photo, $second );
		$this->assertFalse( metadata_exists( 'post', $photo, Favorites::META_KEY_FAVORITES_COUNT ) );
	}
}
