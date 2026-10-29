<?php
/**
 * Tests for the handbook watchlist writer.
 *
 * @package handbook
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Tests that watchlist updates only ever affect the calling user and only
 * apply to handbook pages.
 */
class WPorg_Handbook_Email_Post_Changes_Test extends WPorg_Handbook_TestCase {

	/**
	 * A published handbook page carrying the watchlist under test.
	 *
	 * @var int
	 */
	protected int $handbook_id;

	/**
	 * The user whose subscription must never be removed by someone else.
	 *
	 * @var int
	 */
	protected int $victim_id;

	/**
	 * The acting subscriber, who holds no role on the handbook.
	 *
	 * @var int
	 */
	protected int $attacker_id;

	/**
	 * The meta key that stores a page's watchlist.
	 *
	 * @var string
	 */
	const META_KEY = '_wporg_watchlist';

	/**
	 * Creates a handbook page and the two users used throughout.
	 */
	public function setUp(): void {
		parent::setUp();

		WPorg_Handbook_Init::init();

		$this->handbook_id = $this->factory->post->create(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
				'post_title'  => 'Team Handbook',
			)
		);

		$this->victim_id   = $this->factory->user->create( array( 'role' => 'subscriber' ) );
		$this->attacker_id = $this->factory->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Discards the handbook objects registered for this test.
	 */
	public function tearDown(): void {
		parent::tearDown();

		WPorg_Handbook_Init::reset( true );
	}

	/**
	 * Invokes the watchlist handler for the current user, standing in for the
	 * admin-post request without letting its redirect end the test.
	 *
	 * @param int    $post_id The post being watched or unwatched.
	 * @param bool   $watch   Whether to watch (true) or unwatch (false).
	 * @param string $nonce   The nonce to submit. Default '' mints a valid one.
	 *
	 * @throws Exception Re-thrown if the handler raises anything other than the
	 *                   sentinel used to intercept its redirect.
	 */
	protected function submit( int $post_id, bool $watch, string $nonce = '' ): void {
		if ( '' === $nonce ) {
			$nonce = wp_create_nonce( ( $watch ? 'watch-' : 'unwatch-' ) . $post_id );
		}

		$_GET = array(
			'post_id'  => (string) $post_id,
			'_wpnonce' => $nonce,
		);
		if ( $watch ) {
			$_GET['watch'] = '1';
		}

		$stop = function () {
			throw new Exception( 'wporg-handbook-test-redirect' );
		};
		add_filter( 'wp_redirect', $stop );

		try {
			WPorg_Handbook_Email_Post_Changes::update_watchlist();
		} catch ( Exception $e ) {
			if ( 'wporg-handbook-test-redirect' !== $e->getMessage() ) {
				throw $e;
			}
		} finally {
			remove_filter( 'wp_redirect', $stop );
		}
	}

	/**
	 * Returns the raw watchlist stored for a post.
	 *
	 * @param int $post_id The post to read.
	 * @return array
	 */
	protected function watchlist( int $post_id ): array {
		return (array) get_post_meta( $post_id, self::META_KEY, true );
	}

	/**
	 * A replayed unwatch from a user who isn't on the list must not remove the
	 * remaining subscriber. This is the array_search()-as-index regression: a
	 * `false` result would delete $users[0].
	 */
	public function test_unwatch_by_non_subscriber_does_not_remove_another_user(): void {
		update_post_meta( $this->handbook_id, self::META_KEY, array( $this->victim_id ) );

		wp_set_current_user( $this->attacker_id );
		$this->submit( $this->handbook_id, false );

		$this->assertSame(
			array( $this->victim_id ),
			$this->watchlist( $this->handbook_id ),
			'A user not on the watchlist removed another subscriber by unwatching.'
		);
	}

	/**
	 * The ordinary case still works: unwatching removes the caller and leaves
	 * everyone else in place.
	 */
	public function test_unwatch_removes_only_the_calling_user(): void {
		update_post_meta( $this->handbook_id, self::META_KEY, array( $this->victim_id, $this->attacker_id ) );

		wp_set_current_user( $this->attacker_id );
		$this->submit( $this->handbook_id, false );

		$this->assertSame( array( $this->victim_id ), $this->watchlist( $this->handbook_id ) );
	}

	/**
	 * Watching twice does not add a duplicate entry.
	 */
	public function test_watch_does_not_duplicate_an_existing_subscriber(): void {
		update_post_meta( $this->handbook_id, self::META_KEY, array( $this->attacker_id ) );

		wp_set_current_user( $this->attacker_id );
		$this->submit( $this->handbook_id, true );

		$this->assertSame( array( $this->attacker_id ), $this->watchlist( $this->handbook_id ) );
	}

	/**
	 * The handler refuses to write a watchlist onto a non-handbook post.
	 */
	public function test_watch_is_ignored_for_a_non_handbook_post(): void {
		$post_id = $this->factory->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		wp_set_current_user( $this->attacker_id );
		$this->submit( $post_id, true );

		$this->assertSame( '', get_post_meta( $post_id, self::META_KEY, true ) );
	}

	/**
	 * A logged-out request cannot change a watchlist.
	 */
	public function test_watch_is_ignored_for_a_logged_out_user(): void {
		$nonce = wp_create_nonce( 'watch-' . $this->handbook_id );

		wp_set_current_user( 0 );
		$this->submit( $this->handbook_id, true, $nonce );

		$this->assertSame( '', get_post_meta( $this->handbook_id, self::META_KEY, true ) );
	}
}
