<?php
/**
 * Tests that job descriptions keep shortcode, block and embed syntax as text.
 *
 * Descriptions arrive through the public form and are stored with a few allowed
 * tags. `Jobs_Dot_WP` encodes the characters do_shortcode(), do_blocks() and
 * autoembed() key on, once when a job is saved and again on output, so neither
 * new rows nor rows stored before the encoding existed are parsed as post markup.
 *
 * @package jobswp
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for `Jobs_Dot_WP::store_job_content_escaped()` and `Jobs_Dot_WP::escape_job_content_syntax()`.
 *
 * @group jobswp
 */
#[Group( 'jobswp' )]
class Description_Syntax_Test extends TestCase {

	/**
	 * A description holding each kind of syntax the_content would otherwise parse.
	 *
	 * @var string
	 */
	private const SUBMITTED = "Hiring.\n<!-- wp:jobswp-test/block /-->\n[jobswp_test]\nhttps://example.org/apply\nText with [brackets] and <strong>bold</strong>.";

	/**
	 * The same description as it is stored.
	 *
	 * @var string
	 */
	private const STORED = "Hiring.\n&lt;!-- wp:jobswp-test/block /-->\n&#91;jobswp_test]\nhttps&#58;//example.org/apply\nText with &#91;brackets] and <strong>bold</strong>.";

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	private array $post_ids = array();

	/**
	 * Registers a shortcode and a dynamic block whose output shows they ran.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		add_shortcode( 'jobswp_test', array( $this, 'render_marker' ) );
		register_block_type( 'jobswp-test/block', array( 'render_callback' => array( $this, 'render_marker' ) ) );
		add_filter( 'jobswp_require_captcha', '__return_false' );

		if ( ! get_user_by( 'login', 'jobposter' ) ) {
			wp_insert_user(
				array(
					'user_login' => 'jobposter',
					'user_pass'  => wp_generate_password(),
					'role'       => 'subscriber',
				)
			);
		}
	}

	/**
	 * Removes the fixtures and the request state a submission leaves behind.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		remove_shortcode( 'jobswp_test' );
		unregister_block_type( 'jobswp-test/block' );
		remove_filter( 'jobswp_require_captcha', '__return_false' );

		// create_job() swaps the kses profile for the rest of the request.
		remove_filter( 'content_save_pre', 'wp_filter_kses' );
		if ( ! has_filter( 'content_save_pre', 'wp_filter_post_kses' ) ) {
			add_filter( 'content_save_pre', 'wp_filter_post_kses' );
		}

		$_POST    = array();
		$_REQUEST = array();

		$GLOBALS['post'] = null;

		parent::tearDown();
	}

	/**
	 * Renders a marker that only appears when a parser ran.
	 *
	 * @return string
	 */
	public function render_marker(): string {
		return 'PARSER RAN';
	}

	/**
	 * Submits a job through the public form's save path.
	 *
	 * @param string $description The job description.
	 * @return WP_Post The job it created.
	 */
	private function submit_job( string $description ): WP_Post {
		$title = 'Job ' . uniqid();

		$_POST = array(
			'postjob'           => '1',
			'verify'            => '1',
			'accept'            => '1',
			'first_name'        => 'Rose',
			'last_name'         => 'Carter',
			'email'             => 'rose@example.org',
			'company'           => 'Example Corp',
			'howtoapply_method' => 'email',
			'howtoapply'        => 'apply@example.org',
			'job_title'         => $title,
			'category'          => 'development',
			'jobtype'           => 'ft',
			'job_description'   => $description,
		);

		$_REQUEST['_wpnonce'] = wp_create_nonce( 'jobswppostjob' );

		Jobs_Dot_WP::get_instance()->save_job();

		$jobs = get_posts(
			array(
				'post_type'   => 'job',
				'post_status' => 'draft',
				'title'       => $title,
			)
		);

		$this->assertCount( 1, $jobs, 'The submission should have created one draft job.' );
		$this->post_ids[] = $jobs[0]->ID;

		return $jobs[0];
	}

	/**
	 * Inserts a job as it was stored before descriptions were encoded on save.
	 *
	 * @param string $content The raw description.
	 * @return WP_Post
	 */
	private function insert_legacy_job( string $content ): WP_Post {
		$store = array( Jobs_Dot_WP::get_instance(), 'store_job_content_escaped' );

		remove_filter( 'wp_insert_post_data', $store, PHP_INT_MAX );
		$job_id = wp_insert_post(
			array(
				'post_type'    => 'job',
				'post_status'  => 'publish',
				'post_title'   => 'Legacy job ' . uniqid(),
				'post_content' => wp_slash( $content ),
			),
			true
		);
		add_filter( 'wp_insert_post_data', $store, PHP_INT_MAX );

		$this->assertIsInt( $job_id );
		$this->post_ids[] = $job_id;

		return get_post( $job_id );
	}

	/**
	 * Renders content the way a template does: with the post as the global post.
	 *
	 * @param WP_Post $post The post the content belongs to.
	 * @return string
	 */
	private function render( WP_Post $post ): string {
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		return apply_filters( 'the_content', $post->post_content );
	}

	/**
	 * A submitted description is stored with its syntax encoded.
	 *
	 * @return void
	 */
	public function test_submission_is_stored_with_syntax_as_text(): void {
		$job = $this->submit_job( self::SUBMITTED );

		$this->assertSame( self::STORED, $job->post_content );
	}

	/**
	 * Saving a job again keeps the description encoded once.
	 *
	 * WordPress's kses decodes entities on every save, so the encoding has to be
	 * applied after it and must not stack when the stored form comes back around.
	 *
	 * @return void
	 */
	public function test_resave_keeps_the_syntax_encoded_once(): void {
		$job = $this->submit_job( self::SUBMITTED );

		wp_update_post(
			array(
				'ID'           => $job->ID,
				'post_content' => wp_slash( $job->post_content ),
			)
		);

		$content = get_post_field( 'post_content', $job->ID, 'raw' );

		$this->assertStringContainsString( '&#91;jobswp_test]', $content );
		$this->assertStringContainsString( '&lt;!-- wp:jobswp-test/block', $content );
		$this->assertStringContainsString( 'https&#58;//example.org/apply', $content );
		$this->assertStringNotContainsString( '&amp;', $content );
		$this->assertStringNotContainsString( '<!--', $content );
		$this->assertStringNotContainsString( '[', $content );
		$this->assertStringNotContainsString( '://', $content );
	}

	/**
	 * Output renders a stored description without running any parser over it.
	 *
	 * @return void
	 */
	public function test_output_keeps_the_syntax_as_text(): void {
		$output = $this->render( $this->submit_job( self::SUBMITTED ) );

		$this->assertStringNotContainsString( 'PARSER RAN', $output );
		$this->assertStringContainsString( '&#91;jobswp_test]', $output );
		$this->assertStringContainsString( 'https&#58;//example.org/apply', $output );
		$this->assertStringContainsString( '<strong>bold</strong>', $output );
	}

	/**
	 * Output covers descriptions stored before they were encoded on save.
	 *
	 * @return void
	 */
	public function test_output_covers_rows_stored_before_encoding(): void {
		$output = $this->render( $this->insert_legacy_job( self::SUBMITTED ) );

		$this->assertStringNotContainsString( 'PARSER RAN', $output );
		$this->assertStringNotContainsString( '<!-- wp:', $output );
		$this->assertStringContainsString( '&#91;jobswp_test]', $output );
		$this->assertStringContainsString( 'https&#58;//example.org/apply', $output );
	}

	/**
	 * Other post types keep their shortcodes and blocks.
	 *
	 * @return void
	 */
	public function test_other_post_types_are_left_alone(): void {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_title'   => 'Post ' . uniqid(),
				'post_content' => '<!-- wp:jobswp-test/block /-->[jobswp_test]',
			),
			true
		);

		$this->assertIsInt( $post_id );
		$this->post_ids[] = $post_id;

		$this->assertSame( 'PARSER RANPARSER RAN', trim( $this->render( get_post( $post_id ) ) ) );
	}
}
