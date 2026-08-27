<?php
/**
 * Tests for the security scan committer email notifications.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Scan_Gandalf;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Tests that completed security scan callbacks email the plugin committers.
 *
 * Extends the plain PHPUnit TestCase: WP_UnitTestCase is not compatible with
 * the PHPUnit 11 runner used by this suite. Isolation comes from giving every
 * test its own plugin post and committer instead of per-test transactions.
 *
 * The group is declared as an attribute as well as `@group`: PHPUnit 11 ignores
 * a class-level `@group` docblock, while older runners ignore the attribute.
 *
 * @group jobs
 */
#[Group( 'jobs' )]
class Security_Scan_Notification_Test extends TestCase {

	/** The scan ID used for the pending scan fixture. */
	private const SCAN_ID = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';

	/** A second scan ID, for retries of the same verdict. */
	private const SECOND_SCAN_ID = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';

	/** The version and release ref used for the pending scan fixture. */
	private const VERSION = '1.4.4';

	/**
	 * Counter to give every test plugin and committer a unique slug.
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
	 * The committer of the plugin under test.
	 *
	 * @var \WP_User
	 */
	private \WP_User $committer;

	/**
	 * The emails captured from wp_mail().
	 *
	 * @var array
	 */
	private array $emails = array();

	/**
	 * The pre_wp_mail callback capturing emails, removed on teardown.
	 *
	 * @var callable
	 */
	private $mail_filter;

	/**
	 * The notification threshold filter of the current test, removed on teardown.
	 *
	 * @var callable|null
	 */
	private $threshold_filter = null;

	/**
	 * The threshold filter pinning the suite to 8.0, removed on teardown.
	 *
	 * @var callable
	 */
	private $threshold_pin;

	/**
	 * The REMOTE_ADDR the test overwrote, restored on teardown.
	 *
	 * @var string|null
	 */
	private ?string $remote_addr = null;

	/**
	 * Create a published plugin with a pending security scan and one committer.
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_cache_flush();

		// Pin the thresholds the suite was written against; the shipped defaults disable both.
		$this->threshold_pin = static function (): float {
			return 8.0;
		};
		add_filter( 'wporg_plugins_security_scan_block_risk_score', $this->threshold_pin );
		add_filter( 'wporg_plugins_security_scan_notify_risk_score', $this->threshold_pin );

		// Tools::audit_log() reads it unguarded.
		$this->remote_addr      = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Captured verbatim to restore in tearDown, not used as input.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'notify-test-' . ( ++self::$plugin_count ),
				'post_title'  => 'Scan Notification Test Plugin',
				'post_status' => 'publish',
			)
		);

		$this->assertInstanceOf( \WP_Post::class, $plugin );
		$this->plugin = $plugin;

		/*
		 * The stub update_source table survives across runs — the WP test
		 * installer only drops core tables — so clear leftovers that would
		 * collide with this run's plugin ID or read as a served version.
		 */
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_id' => $this->plugin->ID ) );
		$wpdb->delete( $wpdb->prefix . 'update_source', array( 'plugin_slug' => $this->plugin->post_name ) );

		update_post_meta( $this->plugin->ID, 'version', self::VERSION );
		update_post_meta( $this->plugin->ID, 'stable_tag', self::VERSION );
		$this->add_pending_scan( self::SCAN_ID );

		$login   = 'scan-committer-' . self::$plugin_count;
		$user_id = wp_create_user( $login, wp_generate_password(), $login . '@example.com' );
		$this->assertIsInt( $user_id );
		$this->committer = new \WP_User( $user_id );

		$this->prime_committers( array( $login ) );

		$this->emails      = array();
		$this->mail_filter = function ( $short_circuit, $atts ) {
			$this->emails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $this->mail_filter, 10, 2 );

		/*
		 * The HTML email resets the mailer after wp_mail(); with wp_mail()
		 * short-circuited nothing initializes the global, so stub it.
		 */
		$GLOBALS['phpmailer'] = new class() {
			/**
			 * The plain-text alternative body.
			 *
			 * @var string
			 */
			public $AltBody = ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

			/**
			 * Toggle HTML mode.
			 *
			 * @param bool $is_html Whether to send HTML.
			 */
			public function IsHTML( bool $is_html = true ): void {} // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		};
	}

	/**
	 * Remove the filters and globals the tests installed.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', $this->mail_filter, 10 );
		remove_filter( 'wporg_plugins_security_scan_block_risk_score', $this->threshold_pin );
		remove_filter( 'wporg_plugins_security_scan_notify_risk_score', $this->threshold_pin );

		if ( $this->threshold_filter ) {
			remove_filter( 'wporg_plugins_security_scan_notify_risk_score', $this->threshold_filter, 10 );
			$this->threshold_filter = null;
		}

		unset( $GLOBALS['bot_accounts'], $GLOBALS['nologin_accounts'], $GLOBALS['phpmailer'] );

		if ( null === $this->remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->remote_addr;
		}

		parent::tearDown();
	}

	/**
	 * Register a pending scan on the plugin fixture.
	 *
	 * @param string $scan_id The scan ID to register.
	 */
	private function add_pending_scan( string $scan_id ): void {
		$pending = get_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, true );
		$pending = is_array( $pending ) ? $pending : array();

		$pending[ $scan_id ] = array(
			'version'      => self::VERSION,
			'release_ref'  => self::VERSION,
			'requested_at' => time(),
		);

		update_post_meta( $this->plugin->ID, Plugin_Scan_Gandalf::PENDING_META_KEY, $pending );
	}

	/**
	 * Prime the committer cache Tools::get_plugin_committers() reads.
	 *
	 * @param array $logins The committer logins.
	 */
	private function prime_committers( array $logins ): void {
		wp_cache_set( $this->plugin->post_name, $logins, 'plugin-committers', HOUR_IN_SECONDS );
	}

	/**
	 * Filter the notification threshold for the current test.
	 *
	 * @param float $threshold The notification threshold to use.
	 */
	private function set_notify_threshold( float $threshold ): void {
		$this->threshold_filter = static function () use ( $threshold ): float {
			return $threshold;
		};
		add_filter( 'wporg_plugins_security_scan_notify_risk_score', $this->threshold_filter );
	}

	/**
	 * Register a release for the scanned version, still inside its cooldown window.
	 */
	private function stage_release(): void {
		update_post_meta(
			$this->plugin->ID,
			'releases',
			array(
				array(
					'date'                     => time(),
					'tag'                      => self::VERSION,
					'version'                  => self::VERSION,
					'zips_built'               => true,
					'zips_built_from_revision' => 0,
					'confirmations'            => array(),
					'confirmed'                => true,
					'confirmations_required'   => 0,
					'committer'                => array(),
					'revision'                 => array(),
					'release_delay'            => DAY_IN_SECONDS,
				),
			)
		);
	}

	/**
	 * Stage an update_source row serving the scanned version, so a verdict
	 * that can't un-ship anything stays advisory.
	 */
	private function stage_served_release(): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'update_source',
			array(
				'plugin_id'        => $this->plugin->ID,
				'plugin_slug'      => $this->plugin->post_name,
				'available'        => 1,
				'version'          => self::VERSION,
				'stable_tag'       => self::VERSION,
				'plugin_name'      => $this->plugin->post_title,
				'requires_plugins' => '',
				'last_updated'     => $this->plugin->post_modified,
			)
		);

		$this->stage_release();
	}

	/**
	 * Build a finding entry matching the callback contract.
	 *
	 * @param float $risk_score The finding risk score.
	 * @param array $overrides  Fields to override.
	 * @return array The finding.
	 */
	private function finding( float $risk_score, array $overrides = array() ): array {
		return array_merge(
			array(
				'id'            => 'finding-' . md5( (string) $risk_score ),
				'ref'           => 'prompt-security.supply_chain.remote_controlled_code',
				'title'         => 'Remote response controls a PHP callable <script>alert(1)</script>',
				'severity'      => 'error',
				'file_path'     => 'includes/class-admin.php',
				'line'          => 688,
				'code_snippet'  => '$clean = $this->write;',
				'explanation'   => 'The response body reaches a callable.',
				'risk_score'    => $risk_score,
				'investigation' => array(
					'status'  => 'completed',
					'result'  => 'reproduced',
					'summary' => 'The unauthenticated probe reached the sink.',
				),
			),
			$overrides
		);
	}

	/**
	 * Build a completed callback matching the pending scan fixture.
	 *
	 * @param array $overrides Fields to override.
	 * @return array The callback data.
	 */
	private function completed_callback( array $overrides = array() ): array {
		$defaults = array(
			'status'          => 'completed',
			'scan_id'         => self::SCAN_ID,
			'subject_type'    => 'plugin',
			'slug'            => $this->plugin->post_name,
			'version'         => self::VERSION,
			'release_ref'     => self::VERSION,
			'completed_at'    => time(),
			'verdict_hash'    => 'f71c3d944050095a4e2e20f9ee8a7c9a',
			'findings_count'  => 1,
			'findings'        => array( $this->finding( 9.8 ) ),
			'max_risk_score'  => 9.8,
			'severity_counts' => array( 'error' => 1 ),
			'scanner_version' => '0.3.0',
			'report_url'      => 'https://scanner.example/runs/' . self::SCAN_ID,
		);

		return array_merge( $defaults, $overrides );
	}

	/**
	 * A blocked release emails the committers, reflecting the block.
	 */
	public function test_blocked_scan_emails_committers(): void {
		$this->stage_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->assertCount( 1, $this->emails );
		$email = $this->emails[0];

		$this->assertSame( $this->committer->user_email, $email['to'] );
		$this->assertStringContainsString( 'has been blocked due to security findings', $email['subject'] );
		$this->assertStringContainsString( self::VERSION, $email['subject'] );

		$this->assertStringContainsString( 'block this version from being offered as an update', $email['message'] );
		$this->assertStringContainsString( '9.8', $email['message'] );
		$this->assertStringContainsString( 'Remote response controls a PHP callable', $email['message'] );
		// The relative path links to the file in the Trac browser.
		$this->assertStringContainsString(
			sprintf( 'https://plugins.trac.wordpress.org/browser/%s/tags/%s/includes/class-admin.php#L688', $this->plugin->post_name, self::VERSION ),
			$email['message']
		);
		$this->assertStringContainsString( '>includes/class-admin.php:688</a>', $email['message'] );

		// The code snippet renders escaped in a code block; the explanation as prose.
		$this->assertStringContainsString( '$clean = $this-&gt;write;', $email['message'] );
		$this->assertStringContainsString( '<code>', $email['message'] );
		$this->assertStringContainsString( 'The response body reaches a callable.', $email['message'] );
	}

	/**
	 * Hostile finding strings do not reach the email as markup.
	 *
	 * The HTML variant renders Markdown, so both HTML tags and Markdown link
	 * syntax must be neutralized.
	 */
	public function test_email_neutralizes_hostile_findings(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings_count' => 2,
				'findings'       => array(
					$this->finding( 9.8 ),
					$this->finding(
						9.7,
						array(
							'title'        => '[Appeal this decision](https://evil.example/appeal)',
							'code_snippet' => '<script>alert(2)</script>',
						)
					),
				),
			)
		);

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback );

		$this->assertCount( 1, $this->emails );
		$message = $this->emails[0]['message'];

		// Markup in a title renders as inert, escaped text.
		$this->assertStringNotContainsString( '<script>', $message );
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $message );

		// The Markdown link must not survive as a masquerading anchor.
		$this->assertStringContainsString( 'Appeal this decision', $message );
		$this->assertStringNotContainsString( '[Appeal', $message );
		$this->assertStringNotContainsString( '>Appeal this decision</a>', $message );

		// A hostile snippet renders escaped inside its code block.
		$this->assertStringContainsString( '&lt;script&gt;alert(2)&lt;/script&gt;', $message );
	}

	/**
	 * An author-controlled version header does not reach the email as markup.
	 *
	 * The version is interpolated into the Markdown intro, so HTML and link
	 * syntax in it must be neutralized like any other untrusted string.
	 */
	public function test_email_neutralizes_hostile_version(): void {
		$version = '9.9 <script>alert(7)</script>';

		update_post_meta( $this->plugin->ID, 'version', $version );
		update_post_meta( $this->plugin->ID, 'stable_tag', $version );
		update_post_meta(
			$this->plugin->ID,
			Plugin_Scan_Gandalf::PENDING_META_KEY,
			array(
				self::SCAN_ID => array(
					'version'      => $version,
					'release_ref'  => $version,
					'requested_at' => time(),
				),
			)
		);

		$callback = $this->completed_callback(
			array(
				'version'     => $version,
				'release_ref' => $version,
			)
		);

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback );

		$this->assertCount( 1, $this->emails );
		$message = $this->emails[0]['message'];

		$this->assertStringNotContainsString( '<script>alert(7)', $message );
		$this->assertStringContainsString( '9.9 &lt;script&gt;alert(7)', $message );
	}

	/**
	 * Indented snippets render outdented, keeping their relative indentation.
	 */
	public function test_snippet_outdents_common_indentation(): void {
		$callback = $this->completed_callback(
			array(
				'findings_count' => 2,
				'findings'       => array(
					$this->finding( 9.8, array( 'code_snippet' => "        if ( \$x ) {\n\n            do_thing();\n        }" ) ),
					$this->finding( 9.7, array( 'code_snippet' => "function f() {\n    return 1;\n}" ) ),
				),
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$this->assertCount( 1, $this->emails );
		$message = $this->emails[0]['message'];

		// The shared indent is gone, the nested line keeps its relative step, the blank line survives.
		$this->assertStringContainsString( "if ( \$x ) {\n\n    do_thing();\n}", $message );

		// A snippet already at the margin is untouched.
		$this->assertStringContainsString( "function f() {\n    return 1;\n}", $message );
	}

	/**
	 * A lone carriage return in a snippet cannot break a line out of the code block.
	 *
	 * The Markdown processor maps a bare \r to a newline; if the snippet is not
	 * normalized first, the split-out line loses its indent and renders as live
	 * HTML instead of escaped code.
	 */
	public function test_snippet_carriage_return_stays_in_code_block(): void {
		$callback = $this->completed_callback(
			array(
				'findings_count' => 1,
				'findings'       => array(
					$this->finding( 9.8, array( 'code_snippet' => "safe_line\r<script>alert(4)</script>" ) ),
				),
			)
		);

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback );

		$message = $this->emails[0]['message'];

		$this->assertStringContainsString( '&lt;script&gt;alert(4)&lt;/script&gt;', $message );
		$this->assertStringNotContainsString( '<script>alert(4)', $message );
	}

	/**
	 * Line-leading markers in an explanation cannot forge headings or rules.
	 *
	 * A carriage return is a line break to the Markdown processor, so it can
	 * smuggle a marker to the start of a line as well.
	 */
	public function test_explanation_cannot_forge_markdown_blocks(): void {
		$callback = $this->completed_callback(
			array(
				'findings_count' => 1,
				'findings'       => array(
					$this->finding(
						9.8,
						array( 'explanation' => "# Forged heading\n\n---\n\ntext\r### smuggled" )
					),
				),
			)
		);

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback );

		$message = $this->emails[0]['message'];

		// The forged markers render as text, not as heading or rule elements.
		$this->assertStringContainsString( '&#35; Forged heading', $message );
		$this->assertStringContainsString( '&#45;--', $message );
		$this->assertStringNotContainsString( '<h1', $message );

		// A carriage-return-smuggled marker is neutralized too.
		$this->assertStringContainsString( '&#35;## smuggled', $message );
		$this->assertStringNotContainsString( 'smuggled</h3>', $message );
	}

	/**
	 * An explanation cannot forge lists, tables, definition lists, or fences.
	 */
	public function test_explanation_cannot_forge_structured_blocks(): void {
		$callback = $this->completed_callback(
			array(
				'findings_count' => 1,
				'findings'       => array(
					$this->finding(
						9.8,
						array(
							'code_snippet' => '',
							'explanation'  => "+ bullet\n\n1. numbered\n\n| a | b |\n| - | - |\n| 1 | 2 |\n\nterm\n:   def\n\n~~~\nfenced\n~~~",
						)
					),
				),
			)
		);

		Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback );

		$message = $this->emails[0]['message'];

		// None of the block openers produce a real structured element.
		$this->assertStringNotContainsString( '<ul', $message );
		$this->assertStringNotContainsString( '<ol', $message );
		$this->assertStringNotContainsString( '<table', $message );
		$this->assertStringNotContainsString( '<dl', $message );
		$this->assertStringNotContainsString( '<pre>', $message );

		// The text still reaches the reader.
		$this->assertStringContainsString( 'bullet', $message );
		$this->assertStringContainsString( 'fenced', $message );
	}

	/**
	 * The plain-text body decodes entities without reconstructing live markup.
	 *
	 * The pre_wp_mail harness captures only the HTML variant, so the plain-text
	 * body() is exercised directly here.
	 */
	public function test_plain_text_body_is_inert(): void {
		$record = array(
			'scan_id'         => self::SCAN_ID,
			'version'         => self::VERSION,
			'release_ref'     => self::VERSION,
			'completed_at'    => time(),
			'verdict_hash'    => 'f71c3d944050095a4e2e20f9ee8a7c9a',
			'findings_count'  => 1,
			'severity_counts' => array( 'error' => 1 ),
			'max_risk_score'  => 9.8,
			'report_url'      => 'https://scanner.example/runs/' . self::SCAN_ID,
			'action'          => 'advisory',
			'findings'        => array( $this->finding( 9.8, array( 'explanation' => '# heading text' ) ) ),
		);

		$email = new \WordPressdotorg\Plugin_Directory\Email\Security_Scan_Findings(
			$this->plugin,
			array( $this->committer ),
			array( 'record' => $record )
		);

		$body = $email->body();

		// The HTML entities are decoded back to plain characters for the text/plain part.
		$this->assertStringContainsString( '<script>alert(1)</script>', $body );
		$this->assertStringContainsString( '# heading text', $body );

		// The HTML variant keeps them escaped and neutralized.
		$html = $email->html();
		$this->assertStringContainsString( '&lt;script&gt;alert(1)&lt;/script&gt;', $html );
		$this->assertStringContainsString( '&#35; heading text', $html );
	}

	/**
	 * A high-risk verdict that could not block still emails, as advisory.
	 */
	public function test_advisory_high_risk_scan_emails_committers(): void {
		$this->stage_served_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->assertCount( 1, $this->emails );
		$this->assertStringContainsString( 'Security scan findings in', $this->emails[0]['subject'] );
		$this->assertStringContainsString( 'Please review the findings and address them in an upcoming release.', $this->emails[0]['message'] );
		$this->assertStringNotContainsString( 'blocked', $this->emails[0]['message'] );
	}

	/**
	 * Scans below the notification threshold do not email the committers.
	 */
	public function test_below_threshold_sends_no_email(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings'       => array( $this->finding( 7.9 ) ),
				'max_risk_score' => 7.9,
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );
		$this->assertCount( 0, $this->emails );
	}

	/**
	 * Lowering the threshold emails advisory results below the block threshold.
	 */
	public function test_threshold_filter_lowers_notification_bar(): void {
		$this->set_notify_threshold( 5.0 );

		$callback = $this->completed_callback(
			array(
				'findings'       => array( $this->finding( 5.2 ) ),
				'max_risk_score' => 5.2,
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$this->assertCount( 1, $this->emails );
		$this->assertStringContainsString( 'Security scan findings in', $this->emails[0]['subject'] );
	}

	/**
	 * A threshold above 10 disables the emails, even for a blocked release.
	 */
	public function test_threshold_filter_disables_notifications(): void {
		$this->set_notify_threshold( 11.0 );
		$this->stage_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->assertTrue( API_Update_Updater::is_release_blocked( Plugin_Directory::get_release( $this->plugin, self::VERSION ) ) );
		$this->assertCount( 0, $this->emails );
	}

	/**
	 * The same advisory verdict is emailed once, even across scans.
	 */
	public function test_same_advisory_verdict_is_emailed_once(): void {
		$this->stage_served_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->add_pending_scan( self::SECOND_SCAN_ID );
		$retry = $this->completed_callback( array( 'scan_id' => self::SECOND_SCAN_ID ) );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $retry ) );

		$this->assertCount( 1, $this->emails );
	}

	/**
	 * A blocked release always emails, even for an already-emailed verdict.
	 */
	public function test_blocked_verdict_always_emails(): void {
		$this->stage_release();

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->add_pending_scan( self::SECOND_SCAN_ID );
		$retry = $this->completed_callback( array( 'scan_id' => self::SECOND_SCAN_ID ) );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( get_post( $this->plugin->ID ), $retry ) );

		$this->assertCount( 2, $this->emails );
		$this->assertStringContainsString( 'has been blocked due to security findings', $this->emails[1]['subject'] );
	}

	/**
	 * Bot committers are not emailed.
	 */
	public function test_bot_committers_are_not_emailed(): void {
		$this->stage_release();

		// A real account, so only the bot filter keeps it from being emailed.
		$bot_login = 'gandalf-bot-' . self::$plugin_count;
		$this->assertIsInt( wp_create_user( $bot_login, wp_generate_password(), $bot_login . '@example.com' ) );

		$this->prime_committers( array( $this->committer->user_login, $bot_login ) );
		$GLOBALS['bot_accounts'] = array( $bot_login );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->assertCount( 1, $this->emails );
		$this->assertSame( $this->committer->user_email, $this->emails[0]['to'] );
	}

	/**
	 * No-login committers are not emailed.
	 */
	public function test_nologin_committers_are_not_emailed(): void {
		$this->stage_release();

		// A real account, so only the no-login filter keeps it from being emailed.
		$nologin_login = 'gandalf-nologin-' . self::$plugin_count;
		$this->assertIsInt( wp_create_user( $nologin_login, wp_generate_password(), $nologin_login . '@example.com' ) );

		$this->prime_committers( array( $this->committer->user_login, $nologin_login ) );
		$GLOBALS['nologin_accounts'] = array( $nologin_login );

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $this->completed_callback() ) );

		$this->assertCount( 1, $this->emails );
		$this->assertSame( $this->committer->user_email, $this->emails[0]['to'] );
	}

	/**
	 * A finding carrying only the required risk_score still renders.
	 */
	public function test_minimal_finding_renders(): void {
		$this->stage_release();

		$callback = $this->completed_callback(
			array(
				'findings'       => array( array( 'risk_score' => 9.9 ) ),
				'max_risk_score' => 9.9,
			)
		);

		$this->assertTrue( Plugin_Scan_Gandalf::handle_callback( $this->plugin, $callback ) );

		$this->assertCount( 1, $this->emails );
		$message = $this->emails[0]['message'];

		// The absent title falls back, and the finding renders without a crash.
		$this->assertStringContainsString( '9.9', $message );
		$this->assertStringContainsString( '(no summary provided)', $message );
	}
}
