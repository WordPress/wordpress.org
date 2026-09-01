<?php
/**
 * Tests for the Cron Job Logs metabox.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Cron_Logs;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

if ( ! class_exists( '\HM\Cavalcade\Plugin\Job' ) ) {
	require_once __DIR__ . '/fixtures/cavalcade/class-job.php';
}

/**
 * The metabox is registered on every plugin edit screen, so a reviewer opening a
 * plugin renders whatever the queued cron jobs carry. A job's `tags_touched` holds
 * committer-chosen values — SVN tag folder names, and the tag named by a
 * release-confirmation request — so they have to reach the page as text.
 *
 * Extends the plain PHPUnit TestCase: WP_UnitTestCase is not compatible with the
 * PHPUnit 11 runner used by this suite. Isolation comes from giving every test its
 * own plugin post instead of per-test transactions.
 *
 * The group is declared as an attribute as well as `@group`: PHPUnit 11 ignores a
 * class-level `@group` docblock, while older runners ignore the attribute.
 *
 * @group admin
 */
#[Group( 'admin' )]
class Cron_Logs_Metabox_Test extends TestCase {

	/**
	 * Counter to give every test plugin a unique slug.
	 *
	 * @var int
	 */
	private static int $plugin_count = 0;

	/**
	 * The plugin post under test.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $plugin;

	/**
	 * Create the plugin whose edit screen is being rendered.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'cron-logs-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Cron Logs Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		$GLOBALS['post'] = $plugin;

		/*
		 * The metabox always asks for logs, and the Cavalcade log table doesn't exist
		 * in the test install. The failed lookup is the same no-logs case as a job that
		 * hasn't run yet; suppress the error so it doesn't reach the output buffer.
		 */
		$GLOBALS['wpdb']->suppress_errors( true );
	}

	/**
	 * Reset the globals the metabox and its job source read.
	 */
	protected function tearDown(): void {
		\HM\Cavalcade\Plugin\Job::$jobs = array();

		$GLOBALS['wpdb']->suppress_errors( false );
		unset( $GLOBALS['post'] );

		parent::tearDown();
	}

	/**
	 * Queue a job against the plugin under test and render the metabox.
	 *
	 * @param array $args The first argument of the cron job.
	 * @return string The metabox markup.
	 */
	private function render_with_job_args( array $args ): string {
		\HM\Cavalcade\Plugin\Job::$jobs = array(
			(object) array(
				'id'      => 1234,
				'hook'    => 'import_plugin:' . $this->plugin->post_name,
				'args'    => array( $args ),
				'status'  => 'waiting',
				'start'   => time(),
				'nextrun' => time(),
			),
		);

		ob_start();
		Cron_Logs::display();

		return (string) ob_get_clean();
	}

	/**
	 * A tag carrying markup must render as text, not as an element.
	 *
	 * A committer reaches this by naming an SVN tag, or by confirming a release whose
	 * Version header became the release identity.
	 *
	 * @return void
	 */
	public function test_markup_in_a_touched_tag_is_not_rendered_as_html(): void {
		$html = $this->render_with_job_args(
			array( 'tags_touched' => array( 'trunk', '99.0<img src=x onerror=alert(1)>' ) )
		);

		$this->assertStringContainsString( 'Tags: trunk, 99.0&lt;img src=x onerror=alert(1)&gt;', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	/**
	 * The same applies when the argument is a scalar rather than a list.
	 *
	 * @return void
	 */
	public function test_markup_in_a_scalar_arg_is_not_rendered_as_html(): void {
		$html = $this->render_with_job_args(
			array( 'tags_touched' => '<script>alert(1)</script>' )
		);

		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
	}

	/**
	 * The hidden Job Args row stays escaped too.
	 *
	 * @return void
	 */
	public function test_the_job_args_row_is_not_rendered_as_html(): void {
		$html = $this->render_with_job_args(
			array( 'tags_touched' => array( '1.0<img src=x onerror=alert(1)>' ) )
		);

		$this->assertStringContainsString( 'Job Args:', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	/**
	 * Ordinary arguments still render, with the wrapping markup intact.
	 *
	 * @return void
	 */
	public function test_expected_args_are_rendered(): void {
		$html = $this->render_with_job_args(
			array(
				'revisions'    => array( 3145926, 3145927 ),
				'tags_touched' => array( 'trunk', '1.2.3' ),
			)
		);

		$this->assertStringContainsString( '<span>Revision: 3145926, 3145927</span>', $html );
		$this->assertStringContainsString( '<span>Tags: trunk, 1.2.3</span>', $html );
	}

	/**
	 * A plugin with no queued jobs renders the empty notice.
	 *
	 * @return void
	 */
	public function test_no_jobs_renders_the_empty_notice(): void {
		\HM\Cavalcade\Plugin\Job::$jobs = array();

		ob_start();
		Cron_Logs::display();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'No cron jobs found.', $html );
	}
}
