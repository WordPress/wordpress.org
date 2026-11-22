<?php
/**
 * Tests the response status of user favorites pages.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Photo_Directory\Favorites;
use WordPressdotorg\Photo_Directory\Registrations;

/**
 * Tests for the 404 handling and template of `/favorites/{user}/` requests.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Favorites_Status_Test extends TestCase {

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
	 * Status codes sent through `status_header()` during the request.
	 *
	 * @var int[]
	 */
	private array $statuses = array();

	/**
	 * Directory with a `user-favorites.php` template, used as the theme directory.
	 *
	 * @var string
	 */
	private string $theme_dir = '';

	/**
	 * Captures status codes and provides a favorites template.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_favorites_user();

		$this->theme_dir = sys_get_temp_dir() . '/photo-directory-favorites-theme-' . getmypid();
		wp_mkdir_p( $this->theme_dir );
		file_put_contents( $this->theme_dir . '/user-favorites.php', '<?php // Test template.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary test template.

		add_filter( 'status_header', array( $this, 'capture_status' ), 10, 2 );
		add_filter( 'stylesheet_directory', array( $this, 'theme_dir' ) );
		add_filter( 'template_directory', array( $this, 'theme_dir' ) );
		// `locate_template()` reads the theme paths from globals set once per request.
		wp_set_template_globals();
	}

	/**
	 * Removes what the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		remove_filter( 'status_header', array( $this, 'capture_status' ) );
		remove_filter( 'stylesheet_directory', array( $this, 'theme_dir' ) );
		remove_filter( 'template_directory', array( $this, 'theme_dir' ) );
		wp_set_template_globals();

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}

		wp_delete_file( $this->theme_dir . '/user-favorites.php' );
		rmdir( $this->theme_dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Temporary test directory.

		$this->reset_favorites_user();
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];

		parent::tearDown();
	}

	/**
	 * Records a status code.
	 *
	 * @param string $status_header The HTTP status header.
	 * @param int    $code          The HTTP status code.
	 * @return string
	 */
	public function capture_status( $status_header, $code ) {
		$this->statuses[] = (int) $code;

		return $status_header;
	}

	/**
	 * Returns the test theme directory.
	 *
	 * @return string
	 */
	public function theme_dir(): string {
		return $this->theme_dir;
	}

	/**
	 * Clears the user memoized by `Favorites::pre_get_posts()`, which lives for one request.
	 *
	 * @return void
	 */
	private function reset_favorites_user(): void {
		( new ReflectionProperty( Favorites::class, 'favorites_user' ) )->setValue( null, null );
	}

	/**
	 * Creates a user.
	 *
	 * @param string $login User login, also used as the slug.
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
	 * Publishes a photo and adds it to a user's favorites.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	private function add_favorite( int $user_id ): void {
		$post_id = wp_insert_post(
			array(
				'post_type'   => Registrations::get_post_type(),
				'post_status' => 'publish',
				'post_title'  => 'Photo',
			)
		);
		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		Favorites::favorite_photo( $post_id, $user_id );
	}

	/**
	 * Runs the parts of `WP::main()` that decide the status, then picks the template.
	 *
	 * @param array $query_vars Query variables, as the favorites rewrite rules set them.
	 * @return string The template that would be loaded.
	 */
	private function request( array $query_vars ): string {
		$this->statuses          = array();
		$GLOBALS['wp_the_query'] = new WP_Query();
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$_SERVER['REQUEST_URI']  = '/';

		$wp = new WP();
		$wp->parse_request( $query_vars );
		$wp->query_posts();
		$wp->handle_404();

		return apply_filters( 'template_include', 'index.php' );
	}

	/**
	 * Sends a 404 for a user that does not exist, instead of showing the front page as their favorites.
	 *
	 * @return void
	 */
	public function test_unknown_user_is_404(): void {
		$this->add_favorite( $this->create_user( 'favorites-someone' ) );

		// Something for the front page query to find, as on the live site.
		$post_id = wp_insert_post(
			array(
				'post_status' => 'publish',
				'post_title'  => 'Front page post',
			)
		);
		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		$template = $this->request( array( Favorites::QUERY_VAR_USER_FAVORITES => 'favorites-nobody' ) );

		$this->assertSame( array( 404 ), $this->statuses );
		$this->assertTrue( is_404() );
		$this->assertSame( array(), $GLOBALS['wp_query']->posts );
		$this->assertSame( 'index.php', $template );
	}

	/**
	 * Shows an empty favorites page for a user who has not favorited any photos, without a 404.
	 *
	 * @return void
	 */
	public function test_user_without_favorites_is_not_404(): void {
		$this->create_user( 'favorites-empty' );

		$template = $this->request( array( Favorites::QUERY_VAR_USER_FAVORITES => 'favorites-empty' ) );

		$this->assertNotContains( 404, $this->statuses );
		$this->assertFalse( is_404() );
		$this->assertSame( array(), $GLOBALS['wp_query']->posts );
		$this->assertSame( $this->theme_dir . '/user-favorites.php', $template );
	}

	/**
	 * Shows the favorites of a user who has some.
	 *
	 * @return void
	 */
	public function test_user_with_favorites_is_shown(): void {
		$user_id = $this->create_user( 'favorites-some' );
		$this->add_favorite( $user_id );

		$template = $this->request( array( Favorites::QUERY_VAR_USER_FAVORITES => 'favorites-some' ) );

		$this->assertNotContains( 404, $this->statuses );
		$this->assertFalse( is_404() );
		$this->assertSame( $this->post_ids, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		$this->assertSame( $this->theme_dir . '/user-favorites.php', $template );
	}

	/**
	 * Leaves a page past the last one to core, which sends a 404.
	 *
	 * @return void
	 */
	public function test_page_past_the_last_is_404(): void {
		$this->create_user( 'favorites-paged' );

		$this->request(
			array(
				Favorites::QUERY_VAR_USER_FAVORITES => 'favorites-paged',
				'paged'                             => '2',
			)
		);

		$this->assertSame( array( 404 ), $this->statuses );
	}

	/**
	 * Does not change requests that are not for a favorites page.
	 *
	 * @return void
	 */
	public function test_other_requests_are_unchanged(): void {
		$template = $this->request( array() );

		$this->assertFalse( Favorites::prevent_404_for_empty_favorites( false, $GLOBALS['wp_the_query'] ) );
		$this->assertSame( 'index.php', $template );

		// A secondary query on a favorites page is left alone.
		$this->create_user( 'favorites-secondary' );
		$this->request( array( Favorites::QUERY_VAR_USER_FAVORITES => 'favorites-secondary' ) );

		$this->assertFalse( Favorites::prevent_404_for_empty_favorites( false, new WP_Query() ) );
	}
}
