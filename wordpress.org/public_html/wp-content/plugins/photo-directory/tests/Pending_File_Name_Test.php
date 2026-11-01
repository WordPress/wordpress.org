<?php
/**
 * Tests the check for a pending submission with the same file name.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Photo_Directory\Registrations;
use WordPressdotorg\Photo_Directory\Uploads;
use WordPressdotorg\Photo_Directory\User;

/**
 * Tests for `User::has_pending_file_name()` and the upload check that uses it.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Pending_File_Name_Test extends TestCase {

	/**
	 * The user uploading the photo.
	 *
	 * @var int
	 */
	private int $user_id = 0;

	/**
	 * Another user with pending submissions.
	 *
	 * @var int
	 */
	private int $other_user_id = 0;

	/**
	 * Submissions created for the test.
	 *
	 * @var int[]
	 */
	private array $post_ids = array();

	/**
	 * `$_FILES` before the test.
	 *
	 * @var array
	 */
	private array $files = array();

	/**
	 * `$_COOKIE` before the test.
	 *
	 * @var array
	 */
	private array $cookie = array();

	/**
	 * Creates the users and logs in the uploader.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->files  = $_FILES; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Saved to restore after the test.
		$this->cookie = $_COOKIE;

		$this->user_id       = $this->create_user( 'pending-name-uploader' );
		$this->other_user_id = $this->create_user( 'pending-name-other' );
		wp_set_current_user( $this->user_id );
	}

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
		wp_set_current_user( 0 );
		wp_delete_user( $this->user_id );
		wp_delete_user( $this->other_user_id );

		$_FILES  = $this->files;
		$_COOKIE = $this->cookie;

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
	 * Adds a submission with its original file name stored the way
	 * `Uploads::store_original_file_info()` stores it.
	 *
	 * @param int    $author    User ID of the submitter.
	 * @param string $file_name Original name of the uploaded file.
	 * @param string $status    Optional. Post status. Default 'pending'.
	 * @return void
	 */
	private function add_submission( int $author, string $file_name, string $status = 'pending' ): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Registrations::get_post_type(),
				'post_status' => $status,
				'post_author' => $author,
				'post_title'  => 'Submission',
			)
		);
		$this->assertIsInt( $post_id );
		update_post_meta( $post_id, Registrations::get_meta_key( 'original_filename' ), sanitize_file_name( $file_name ) );

		$this->post_ids[] = $post_id;
	}

	/**
	 * Counts all photo submissions.
	 *
	 * @return int
	 */
	private function count_submissions(): int {
		return count(
			get_posts(
				array(
					'post_type'   => Registrations::get_post_type(),
					'post_status' => 'any',
					'numberposts' => -1,
					'fields'      => 'ids',
				)
			)
		);
	}

	/**
	 * File names of a pending submission and of a new upload that has the same name.
	 *
	 * @return array<string, array{string, string}>
	 */
	public static function duplicate_file_names(): array {
		return array(
			'spaces'                  => array( 'Sunset at the beach.jpg', 'Sunset at the beach.jpg' ),
			'parentheses'             => array( 'IMG_1234 (1).jpg', 'IMG_1234 (1).jpg' ),
			'accent'                  => array( 'Café.jpg', 'Café.jpg' ),
			'apostrophe'              => array( "O'Brien.jpg", "O'Brien.jpg" ),
			'extra dots'              => array( 'my.photo.final.jpg', 'my.photo.final.jpg' ),
			'unchanged by sanitizing' => array( 'IMG_1234.JPG', 'IMG_1234.JPG' ),
			'already sanitized'       => array( 'Sunset at the beach.jpg', 'Sunset-at-the-beach.jpg' ),
		);
	}

	/**
	 * Finds a pending submission with the same name, including names that sanitizing changes.
	 *
	 * @param string $pending  Original file name of the pending submission.
	 * @param string $uploaded Name of the newly uploaded file.
	 * @return void
	 */
	#[DataProvider( 'duplicate_file_names' )]
	public function test_finds_pending_submission_with_same_file_name( string $pending, string $uploaded ): void {
		$this->add_submission( $this->user_id, $pending );

		$this->assertTrue( User::has_pending_file_name( $uploaded ) );
	}

	/**
	 * Accepts a different file name.
	 *
	 * @return void
	 */
	public function test_accepts_different_file_name(): void {
		$this->add_submission( $this->user_id, 'IMG_1234.JPG' );
		$this->add_submission( $this->user_id, 'Sunset at the beach.jpg' );

		$this->assertFalse( User::has_pending_file_name( 'IMG_1235.JPG' ) );
		$this->assertFalse( User::has_pending_file_name( 'Sunset at the lake.jpg' ) );
	}

	/**
	 * Ignores another user's pending submission and the user's own published photo.
	 *
	 * @return void
	 */
	public function test_ignores_other_users_and_published_photos(): void {
		$this->add_submission( $this->other_user_id, 'Sunset at the beach.jpg' );
		$this->add_submission( $this->user_id, 'IMG_1234.JPG', 'publish' );

		$this->assertFalse( User::has_pending_file_name( 'Sunset at the beach.jpg' ) );
		$this->assertFalse( User::has_pending_file_name( 'IMG_1234.JPG' ) );
	}

	/**
	 * Does not treat names that sanitize to nothing useful as duplicates.
	 *
	 * `###.jpg` and `$$$.jpg` both become core's generic `unnamed-file.jpg`,
	 * and `%%%` becomes an empty name.
	 *
	 * @return void
	 */
	public function test_ignores_empty_and_generic_names(): void {
		$this->add_submission( $this->user_id, '$$$.jpg' );
		$this->add_submission( $this->user_id, 'unnamed-file.jpg' );
		$this->add_submission( $this->user_id, '%%%' );

		$this->assertSame( 'unnamed-file.jpg', sanitize_file_name( '###.jpg' ) );
		$this->assertSame( '', sanitize_file_name( '%%%' ) );
		$this->assertFalse( User::has_pending_file_name( '###.jpg' ) );
		$this->assertFalse( User::has_pending_file_name( '%%%' ) );
		$this->assertFalse( User::has_pending_file_name( '' ) );
		$this->assertFalse( User::has_pending_file_name( ' ' ) );
		$this->assertFalse( User::has_pending_file_name( array( 'Sunset at the beach.jpg' ) ) );
	}

	/**
	 * Compares a file that is actually named like the generic name.
	 *
	 * A literal `unnamed-file.jpg` also matches a pending submission whose name had
	 * no letters or numbers, since both are stored as `unnamed-file.jpg`. The
	 * browser check behaves the same way.
	 *
	 * @return void
	 */
	public function test_compares_literal_unnamed_file_names(): void {
		$this->add_submission( $this->user_id, 'unnamed-file.jpg' );
		$this->add_submission( $this->user_id, 'unnamed-file-1.jpg' );
		$this->add_submission( $this->user_id, 'unnamed-file-camera.jpg' );

		$this->assertTrue( User::has_pending_file_name( 'unnamed-file.jpg' ) );
		$this->assertTrue( User::has_pending_file_name( 'unnamed-file-1.jpg' ) );
		$this->assertTrue( User::has_pending_file_name( 'unnamed-file-camera.jpg' ) );
		$this->assertFalse( User::has_pending_file_name( 'unnamed-file-2.jpg' ) );
	}

	/**
	 * Matches a literal `unnamed-file.jpg` against a pending name that had no letters or numbers.
	 *
	 * @return void
	 */
	public function test_literal_unnamed_file_matches_generic_pending_name(): void {
		$this->add_submission( $this->user_id, '$$$.jpg' );

		$this->assertTrue( User::has_pending_file_name( 'unnamed-file.jpg' ) );
	}

	/**
	 * Finds nothing for a logged-out visitor.
	 *
	 * @return void
	 */
	public function test_logged_out_visitor_has_no_pending_file_names(): void {
		$this->add_submission( $this->user_id, 'IMG_1234.JPG' );
		wp_set_current_user( 0 );

		$this->assertFalse( User::has_pending_file_name( 'IMG_1234.JPG' ) );
	}

	/**
	 * Rejects the upload form before the file is read or a submission is created,
	 * with the reason the response notice turns into a message.
	 *
	 * @return void
	 */
	public function test_upload_form_is_rejected_with_message(): void {
		$this->add_submission( $this->user_id, 'Sunset at the beach.jpg' );
		$posts_before = $this->count_submissions();

		// The temporary file does not exist: the check must return before the file is read.
		$_FILES = array(
			'files' => array(
				'name'     => array( 'Sunset at the beach.jpg' ),
				'type'     => array( 'image/jpeg' ),
				'tmp_name' => array( '/tmp/photo-directory-test-not-read.jpg' ),
				'error'    => array( UPLOAD_ERR_OK ),
				'size'     => array( 0 ),
			),
		);

		$reason = ( new ReflectionMethod( Uploads::class, 'validate_upload_form' ) )->invoke( null );

		$this->assertSame( 'duplicate-pending-file-name', $reason );
		$this->assertSame( $posts_before, $this->count_submissions() );

		// `can_proceed_with_upload()` stores the reason in this cookie for the response notice.
		$_COOKIE['wporg_photos_reject_reason'] = $reason;

		$notices = Uploads::customize_fu_response_notices( array( 'fu-post-sent' => array( 'text' => '' ) ) );

		$this->assertSame( 'You already have a pending submission with this file name.', $notices['fu-spam']['text'] );
	}
}
