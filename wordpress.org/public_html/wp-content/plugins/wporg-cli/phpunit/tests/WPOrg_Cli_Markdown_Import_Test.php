<?php
/**
 * Tests for the Markdown source handling in WPOrg_Cli\Markdown_Import.
 *
 * @package wporg-cli
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WPOrg_Cli\Markdown_Import;

/**
 * Covers which Markdown sources the importer accepts, and who may set them.
 */
class WPOrg_Cli_Markdown_Import_Test extends TestCase {

	/**
	 * Post IDs created by a test, deleted afterwards.
	 *
	 * @var int[]
	 */
	private array $posts = array();

	/**
	 * User IDs created by a test, deleted afterwards.
	 *
	 * @var int[]
	 */
	private array $users = array();

	/**
	 * Removes the fixtures and global state a test set up.
	 */
	public function tearDown(): void {
		$_POST = array();
		wp_set_current_user( 0 );

		foreach ( $this->posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->posts = array();

		foreach ( $this->users as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->users = array();

		parent::tearDown();
	}

	/**
	 * Creates a user with the given role and makes it the current user.
	 *
	 * @param string $role Role to assign.
	 * @return int User ID.
	 */
	private function create_user( string $role ): int {
		$user_id = (int) wp_insert_user(
			array(
				'user_login' => 'wporg_cli_' . $role . '_' . wp_generate_password( 8, false ),
				'user_pass'  => wp_generate_password(),
				'role'       => $role,
			)
		);

		$this->users[] = $user_id;
		wp_set_current_user( $user_id );

		return $user_id;
	}

	/**
	 * Creates a published handbook post.
	 *
	 * @param int $author Author user ID.
	 * @return int Post ID.
	 */
	private function create_handbook_post( int $author ): int {
		$post_id = (int) wp_insert_post(
			array(
				'post_type'   => 'handbook',
				'post_status' => 'publish',
				'post_title'  => 'Handbook page',
				'post_author' => $author,
			)
		);

		$this->posts[] = $post_id;

		return $post_id;
	}

	/**
	 * URLs the importer is expected to accept.
	 *
	 * @return array[]
	 */
	public static function get_allowed_sources(): array {
		return array(
			'raw host'        => array( 'https://raw.githubusercontent.com/wp-cli/handbook/main/README.md' ),
			'blob URL'        => array( 'https://github.com/wp-cli/handbook/blob/main/README.md' ),
			'plain http'      => array( 'http://github.com/wp-cli/handbook/blob/main/README.md' ),
			'mixed case host' => array( 'https://GitHub.com/wp-cli/handbook/blob/main/README.md' ),
			'query string'    => array( 'https://github.com/wp-cli/handbook/blob/main/README.md?v=123' ),
		);
	}

	/**
	 * URLs the importer is expected to reject.
	 *
	 * @return array[]
	 */
	public static function get_disallowed_sources(): array {
		return array(
			'loopback'           => array( 'http://127.0.0.1/secrets' ),
			'loopback by name'   => array( 'http://localhost/secrets' ),
			'private range'      => array( 'http://10.0.0.5:8080/secrets' ),
			'link local'         => array( 'http://169.254.169.254/latest/meta-data/' ),
			'userinfo prefix'    => array( 'https://github.com@127.0.0.1/secrets' ),
			'suffix lookalike'   => array( 'https://github.com.example.org/secrets' ),
			'prefix lookalike'   => array( 'https://notgithub.com/secrets' ),
			'raw host lookalike' => array( 'https://raw.githubusercontent.com.example.org/secrets' ),
			'unrelated host'     => array( 'https://example.org/README.md' ),
			'file scheme'        => array( 'file:///etc/passwd' ),
			'ftp scheme'         => array( 'ftp://github.com/wp-cli/handbook' ),
			'empty string'       => array( '' ),
		);
	}

	/**
	 * The GitHub hosts the handbook is published from are accepted.
	 *
	 * @param string $url URL to check.
	 */
	#[DataProvider( 'get_allowed_sources' )]
	public function test_validate_markdown_source_allows_github_hosts( string $url ): void {
		$this->assertNotEmpty( Markdown_Import::validate_markdown_source( $url ) );
	}

	/**
	 * Every other host is rejected, including internal addresses.
	 *
	 * @param string $url URL to check.
	 */
	#[DataProvider( 'get_disallowed_sources' )]
	public function test_validate_markdown_source_rejects_other_hosts( string $url ): void {
		$this->assertSame( '', Markdown_Import::validate_markdown_source( $url ) );
	}

	/**
	 * A source stored before the check was added is not fetched.
	 */
	public function test_import_rejects_disallowed_source_without_making_a_request(): void {
		$post_id = $this->create_handbook_post( $this->create_user( 'editor' ) );

		update_post_meta( $post_id, 'wporg_cli_markdown_source', 'http://169.254.169.254/latest/meta-data/' );

		$requested = false;
		$spy       = function ( $preempt, $args, $url ) use ( &$requested ) {
			$requested = $url;
			return new WP_Error( 'unexpected-request', 'No request should have been made.' );
		};
		add_filter( 'pre_http_request', $spy, 10, 3 );

		$method = new ReflectionMethod( Markdown_Import::class, 'update_post_from_markdown_source' );
		$method->setAccessible( true );
		$result = $method->invoke( null, $post_id );

		remove_filter( 'pre_http_request', $spy, 10 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid-markdown-source', $result->get_error_code() );
		$this->assertFalse( $requested, 'An outbound request was made for a disallowed source.' );
	}

	/**
	 * Saving a disallowed source clears the stored one.
	 *
	 * The stored value is seeded first so that the assertion distinguishes a
	 * rejected source from `action_save_post()` never reaching the check, which
	 * would leave the meta unset and read back as an empty string either way.
	 */
	public function test_save_post_does_not_store_a_disallowed_source(): void {
		$post_id = $this->create_handbook_post( $this->create_user( 'editor' ) );

		update_post_meta( $post_id, 'wporg_cli_markdown_source', 'https://github.com/wp-cli/handbook/blob/main/README.md' );

		$_POST['wporg-cli-markdown-source']       = 'http://127.0.0.1/secrets';
		$_POST['wporg-cli-markdown-source-nonce'] = wp_create_nonce( 'wporg-cli-markdown-source' );

		Markdown_Import::action_save_post( $post_id );

		$this->assertSame( '', get_post_meta( $post_id, 'wporg_cli_markdown_source', true ) );
	}

	/**
	 * Saving an allowed source stores it unchanged.
	 */
	public function test_save_post_stores_an_allowed_source(): void {
		$post_id = $this->create_handbook_post( $this->create_user( 'editor' ) );
		$source  = 'https://github.com/wp-cli/handbook/blob/main/README.md';

		$_POST['wporg-cli-markdown-source']       = $source;
		$_POST['wporg-cli-markdown-source-nonce'] = wp_create_nonce( 'wporg-cli-markdown-source' );

		Markdown_Import::action_save_post( $post_id );

		$this->assertSame( $source, get_post_meta( $post_id, 'wporg_cli_markdown_source', true ) );
	}

	/**
	 * The slashes WordPress adds to `$_POST` do not survive into the stored source.
	 */
	public function test_save_post_stores_a_slashed_source_without_the_slashes(): void {
		$post_id = $this->create_handbook_post( $this->create_user( 'editor' ) );
		$source  = "https://github.com/wp-cli/handbook/blob/main/what's-new.md";

		$_POST['wporg-cli-markdown-source']       = wp_slash( $source );
		$_POST['wporg-cli-markdown-source-nonce'] = wp_create_nonce( 'wporg-cli-markdown-source' );

		Markdown_Import::action_save_post( $post_id );

		$this->assertSame( $source, get_post_meta( $post_id, 'wporg_cli_markdown_source', true ) );
	}

	/**
	 * Runs the private manifest importer against a single document.
	 *
	 * @param array $doc Manifest document.
	 * @return WP_Post|false The created post, or false if it was skipped.
	 */
	private function create_post_from_manifest_doc( array $doc ) {
		$method = new ReflectionMethod( Markdown_Import::class, 'create_post_from_manifest_doc' );

		return $method->invoke( null, $doc );
	}

	/**
	 * A manifest document with an allowed source is imported.
	 */
	public function test_manifest_doc_with_an_allowed_source_is_created(): void {
		$source = 'https://raw.githubusercontent.com/wp-cli/handbook/main/README.md';

		$post = $this->create_post_from_manifest_doc(
			array(
				'title'           => 'Readme',
				'slug'            => 'readme',
				'markdown_source' => $source,
			)
		);

		$this->assertInstanceOf( WP_Post::class, $post );
		$this->posts[] = $post->ID;

		$this->assertSame( $source, get_post_meta( $post->ID, 'wporg_cli_markdown_source', true ) );
	}

	/**
	 * A manifest document pointing somewhere else creates no post at all.
	 *
	 * Checking at fetch time alone would still leave a page behind that every
	 * scheduled import fails on.
	 */
	public function test_manifest_doc_with_a_disallowed_source_is_skipped(): void {
		$post = $this->create_post_from_manifest_doc(
			array(
				'title'           => 'Internal',
				'slug'            => 'internal',
				'markdown_source' => 'http://169.254.169.254/latest/meta-data/',
			)
		);

		$this->assertFalse( $post );
		$this->assertNull( get_page_by_path( 'internal', OBJECT, 'handbook' ) );
	}

	/**
	 * A manifest document without a source at all creates no post.
	 */
	public function test_manifest_doc_without_a_source_is_skipped(): void {
		$post = $this->create_post_from_manifest_doc(
			array(
				'title' => 'Sourceless',
				'slug'  => 'sourceless',
			)
		);

		$this->assertFalse( $post );
		$this->assertNull( get_page_by_path( 'sourceless', OBJECT, 'handbook' ) );
	}

	/**
	 * A user without edit rights on the post cannot change its source.
	 */
	public function test_save_post_ignores_a_user_who_cannot_edit_the_post(): void {
		$source  = 'https://github.com/wp-cli/handbook/blob/main/README.md';
		$post_id = $this->create_handbook_post( $this->create_user( 'editor' ) );

		update_post_meta( $post_id, 'wporg_cli_markdown_source', $source );

		$this->create_user( 'subscriber' );

		/*
		 * The nonce is generated as the subscriber so that it verifies, leaving the
		 * capability check as the only thing that can reject the save.
		 */
		$_POST['wporg-cli-markdown-source']       = 'https://raw.githubusercontent.com/wp-cli/handbook/main/OTHER.md';
		$_POST['wporg-cli-markdown-source-nonce'] = wp_create_nonce( 'wporg-cli-markdown-source' );

		Markdown_Import::action_save_post( $post_id );

		$this->assertSame( $source, get_post_meta( $post_id, 'wporg_cli_markdown_source', true ) );
	}
}
