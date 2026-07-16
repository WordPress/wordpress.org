<?php
/**
 * Tests for handling a failed Trac ticket creation during upload.
 *
 * `Trac::ticket_create()` returns false when the RPC call fails. The
 * auto-approval that follows it used to run regardless, passing that false
 * along to `Trac::ticket_update()`, which asked Trac for ticket `False`.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests that a failed ticket creation is not followed by an auto-approval.
 *
 * @group upload
 */
class Trac_Ticket_Creation_Failure_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * The cooldown filter callback, so it can be removed again.
	 *
	 * @var callable
	 */
	protected $cooldown_callback;

	/**
	 * Pins the release cooldown, which decides which auto-approval branch runs.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->cooldown_callback = function () {
			return 12 * HOUR_IN_SECONDS;
		};
		add_filter( 'wporg_themes_release_cooldown_delay', $this->cooldown_callback );
	}

	/**
	 * Removes the cooldown filter and the posts created during the test.
	 */
	protected function tearDown(): void {
		remove_filter( 'wporg_themes_release_cooldown_delay', $this->cooldown_callback );

		/*
		 * The plugin prevents repopackages from being deleted; detach that
		 * specific guard while cleaning up the fixture posts.
		 */
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );

		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Builds a stand-in for `Trac` that records calls instead of making them.
	 *
	 * @param bool|int $create_response The value `ticket_create()` should return.
	 * @return object
	 */
	protected function create_trac_double( $create_response ) {
		// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter -- The double mirrors Trac's signatures.
		return new class( $create_response ) {

			/**
			 * The value `ticket_create()` returns.
			 *
			 * @var bool|int
			 */
			public $create_response;

			/**
			 * The `$id` argument of every `ticket_update()` call.
			 *
			 * @var array
			 */
			public $updated_ids = array();

			/**
			 * Constructor.
			 *
			 * @param bool|int $create_response The value `ticket_create()` should return.
			 */
			public function __construct( $create_response ) {
				$this->create_response = $create_response;
			}

			/**
			 * Stands in for an unknown ticket; the tests never reach an existing one.
			 *
			 * @param int $id Trac ticket id.
			 * @return bool
			 */
			public function ticket_get( $id ) {
				return false;
			}

			/**
			 * Returns the canned response.
			 *
			 * @param string $subj Ticket subject line.
			 * @param string $desc Ticket description.
			 * @param array  $attr Ticket attributes.
			 * @return bool|int
			 */
			public function ticket_create( $subj, $desc, $attr = array() ) {
				return $this->create_response;
			}

			/**
			 * Records the ticket id it was called with.
			 *
			 * @param int    $id      Ticket number.
			 * @param string $comment Comment.
			 * @param array  $attr    Ticket attributes.
			 * @param bool   $notify  Whether to notify.
			 * @return bool
			 */
			public function ticket_update( $id, $comment, $attr = array(), $notify = false ) {
				$this->updated_ids[] = $id;

				return true;
			}
		};
		// phpcs:enable Generic.CodeAnalysis.UnusedFunctionParameter
	}

	/**
	 * Builds an upload primed to create a new ticket for a theme update.
	 *
	 * The theme post carries no `_status` meta, so there is no open ticket to
	 * update and a new one gets created.
	 *
	 * @param object $trac The Trac double to use.
	 * @return WPORG_Themes_Upload
	 */
	protected function create_upload( $trac ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_status' => 'publish',
				'post_title'  => 'Test Theme',
				'post_name'   => 'test-theme',
				'post_author' => 1,
			)
		);

		$this->post_ids[] = $post_id;

		$upload              = new WPORG_Themes_Upload();
		$upload->trac        = $trac;
		$upload->theme_slug  = 'test-theme';
		$upload->theme_post  = get_post( $post_id );
		$upload->author      = get_user_by( 'id', 1 );
		$upload->trac_ticket = (object) array(
			'summary'     => 'THEME: Test Theme – 1.0',
			'description' => 'Test description.',
			'keywords'    => array( 'theme-test-theme' ),
			'priority'    => 'theme update',
			'resolution'  => '',
		);

		return $upload;
	}

	/**
	 * A failed ticket creation should not be followed by an auto-approval
	 * update, as there is no ticket to update.
	 */
	public function test_failed_ticket_create_skips_auto_approval() {
		$trac   = $this->create_trac_double( false );
		$upload = $this->create_upload( $trac );

		$ticket_id = $upload->create_or_update_trac_ticket();

		$this->assertFalse( $ticket_id );
		$this->assertSame( array(), $trac->updated_ids );
	}

	/**
	 * A successful ticket creation should still be auto-approved, with the new
	 * ticket id passed along to the update.
	 */
	public function test_successful_ticket_create_is_auto_approved() {
		$trac   = $this->create_trac_double( 12345 );
		$upload = $this->create_upload( $trac );

		$ticket_id = $upload->create_or_update_trac_ticket();

		$this->assertSame( 12345, $ticket_id );
		$this->assertSame( array( 12345 ), $trac->updated_ids );
	}
}
