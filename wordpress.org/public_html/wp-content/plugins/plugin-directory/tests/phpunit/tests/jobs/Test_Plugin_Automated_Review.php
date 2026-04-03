<?php // phpcs:disable WordPress.Files.FileName.NotHyphenatedLowercase -- PHPUnit requires class name to match filename.
/**
 * Tests for Plugin_Automated_Review job.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use WordPressdotorg\Plugin_Directory\Jobs\Plugin_Automated_Review;

/**
 * Unit tests for Plugin_Automated_Review.
 *
 * @group jobs
 * @group automated-review
 */
class Test_Plugin_Automated_Review extends Yoast\PHPUnitPolyfills\TestCases\XTestCase {

	/**
	 * Temporary directory for file-based tests.
	 *
	 * @var string
	 */
	private string $temp_dir = '';

	/**
	 * Clean up the temporary directory after each test.
	 */
	protected function tearDownFixtures(): void {
		if ( $this->temp_dir && is_dir( $this->temp_dir ) ) {
			exec( 'rm -rf ' . escapeshellarg( $this->temp_dir ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_exec
		}
	}

	/**
	 * Create a temporary directory with plugin files for testing.
	 *
	 * @param array $files Map of relative path => content.
	 * @return string Path to the temp directory.
	 */
	private function create_plugin_dir( array $files ): string {
		$this->temp_dir = sys_get_temp_dir() . '/plugin-review-test-' . uniqid();
		mkdir( $this->temp_dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		foreach ( $files as $path => $content ) {
			$full_path = $this->temp_dir . '/' . $path;
			$dir       = dirname( $full_path );
			if ( ! is_dir( $dir ) ) {
				mkdir( $dir, 0755, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
			}
			file_put_contents( $full_path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		return $this->temp_dir;
	}

	/**
	 * Test that blockers produce a reject verdict.
	 */
	public function test_determine_verdict_reject_when_blockers_present(): void {
		$review = array(
			'blockers' => array( array( 'title' => 'SQL injection' ) ),
			'warnings' => array(),
			'info'     => array(),
		);

		$this->assertSame( 'reject', Plugin_Automated_Review::determine_verdict( $review ) );
	}

	/**
	 * Test that warnings without blockers produce needs_changes.
	 */
	public function test_determine_verdict_needs_changes_when_warnings_only(): void {
		$review = array(
			'blockers' => array(),
			'warnings' => array( array( 'title' => 'Missing prefix' ) ),
			'info'     => array(),
		);

		$this->assertSame( 'needs_changes', Plugin_Automated_Review::determine_verdict( $review ) );
	}

	/**
	 * Test that no findings produce approve.
	 */
	public function test_determine_verdict_approve_when_clean(): void {
		$review = array(
			'blockers' => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		$this->assertSame( 'approve', Plugin_Automated_Review::determine_verdict( $review ) );
	}

	/**
	 * Test fallback result with empty batches.
	 */
	public function test_build_fallback_result_empty_batches(): void {
		$result = Plugin_Automated_Review::build_fallback_result( array() );

		$this->assertSame( '', $result['verdict'] );
		$this->assertEmpty( $result['blockers'] );
		$this->assertEmpty( $result['warnings'] );
		$this->assertEmpty( $result['info'] );
	}

	/**
	 * Test fallback result buckets findings by severity.
	 */
	public function test_build_fallback_result_buckets_findings_by_severity(): void {
		$batch_results = array(
			array(
				'batch_id' => 1,
				'files'    => array( 'main.php' ),
				'findings' => array(
					array(
						'severity'    => 'blocker',
						'title'       => 'SQL injection',
						'description' => 'Unsafe query',
						'locations'   => array( 'main.php:10' ),
						'category'    => 'security',
					),
					array(
						'severity'    => 'warning',
						'title'       => 'Missing prefix',
						'description' => 'Unprefixed function',
						'locations'   => array( 'main.php:20' ),
						'category'    => 'prefix',
					),
					array(
						'severity'    => 'info',
						'title'       => 'Minified JS',
						'description' => 'No source map',
						'locations'   => array( 'js/app.min.js' ),
						'category'    => 'structure',
					),
				),
			),
		);

		$result = Plugin_Automated_Review::build_fallback_result( $batch_results );

		$this->assertCount( 1, $result['blockers'] );
		$this->assertCount( 1, $result['warnings'] );
		$this->assertCount( 1, $result['info'] );
		$this->assertSame( 'SQL injection', $result['blockers'][0]['title'] );
		$this->assertSame( 'Missing prefix', $result['warnings'][0]['title'] );
		$this->assertSame( 'Minified JS', $result['info'][0]['title'] );
	}

	/**
	 * Test fallback result strips severity and category from findings.
	 */
	public function test_build_fallback_result_strips_severity_and_category(): void {
		$batch_results = array(
			array(
				'findings' => array(
					array(
						'severity'    => 'blocker',
						'title'       => 'Test',
						'description' => 'Desc',
						'locations'   => array(),
						'category'    => 'security',
					),
				),
			),
		);

		$result = Plugin_Automated_Review::build_fallback_result( $batch_results );

		$this->assertArrayNotHasKey( 'severity', $result['blockers'][0] );
		$this->assertArrayNotHasKey( 'category', $result['blockers'][0] );
	}

	/**
	 * Test fallback result defaults missing finding fields.
	 */
	public function test_build_fallback_result_defaults_missing_fields(): void {
		$batch_results = array(
			array(
				'findings' => array(
					array( 'severity' => 'warning' ),
				),
			),
		);

		$result = Plugin_Automated_Review::build_fallback_result( $batch_results );

		$this->assertSame( 'Untitled', $result['warnings'][0]['title'] );
		$this->assertSame( '', $result['warnings'][0]['description'] );
		$this->assertSame( array(), $result['warnings'][0]['locations'] );
	}

	/**
	 * Test fallback result aggregates findings across multiple batches.
	 */
	public function test_build_fallback_result_aggregates_across_batches(): void {
		$batch_results = array(
			array(
				'batch_id' => 1,
				'files'    => array( 'a.php' ),
				'findings' => array(
					array(
						'severity'    => 'blocker',
						'title'       => 'Issue in batch 1',
						'description' => 'Desc',
						'locations'   => array( 'a.php:1' ),
						'category'    => 'security',
					),
				),
			),
			array(
				'batch_id' => 2,
				'files'    => array( 'b.php' ),
				'findings' => array(
					array(
						'severity'    => 'warning',
						'title'       => 'Issue in batch 2',
						'description' => 'Desc',
						'locations'   => array( 'b.php:1' ),
						'category'    => 'prefix',
					),
				),
			),
		);

		$result = Plugin_Automated_Review::build_fallback_result( $batch_results );

		$this->assertCount( 1, $result['blockers'] );
		$this->assertCount( 1, $result['warnings'] );
		$this->assertSame( 'Issue in batch 1', $result['blockers'][0]['title'] );
		$this->assertSame( 'Issue in batch 2', $result['warnings'][0]['title'] );
	}

	/**
	 * Test unknown severity values are routed to info.
	 */
	public function test_build_fallback_result_unknown_severity_goes_to_info(): void {
		$batch_results = array(
			array(
				'findings' => array(
					array(
						'severity'    => 'note',
						'title'       => 'Something unusual',
						'description' => 'Desc',
						'locations'   => array(),
						'category'    => 'guidelines',
					),
				),
			),
		);

		$result = Plugin_Automated_Review::build_fallback_result( $batch_results );

		$this->assertEmpty( $result['blockers'] );
		$this->assertEmpty( $result['warnings'] );
		$this->assertCount( 1, $result['info'] );
		$this->assertSame( 'Something unusual', $result['info'][0]['title'] );
	}

	/**
	 * Test empty file priorities normalize to an empty array.
	 */
	public function test_normalize_file_priorities_empty(): void {
		$this->assertSame( array(), Plugin_Automated_Review::normalize_file_priorities( array() ) );
	}

	/**
	 * Test map-form priorities pass through unchanged.
	 */
	public function test_normalize_file_priorities_map_passthrough(): void {
		$map = array(
			'main.php'  => 'critical',
			'style.css' => 'low',
		);

		$this->assertSame( $map, Plugin_Automated_Review::normalize_file_priorities( $map ) );
	}

	/**
	 * Test array-of-objects priorities are normalized to map.
	 */
	public function test_normalize_file_priorities_array_of_objects(): void {
		$input = array(
			array(
				'path'     => 'main.php',
				'priority' => 'critical',
			),
			array(
				'path'     => 'style.css',
				'priority' => 'low',
			),
		);

		$expected = array(
			'main.php'  => 'critical',
			'style.css' => 'low',
		);

		$this->assertSame( $expected, Plugin_Automated_Review::normalize_file_priorities( $input ) );
	}

	/**
	 * Test malformed priority entries are skipped.
	 */
	public function test_normalize_file_priorities_skips_malformed_entries(): void {
		$input = array(
			array(
				'path'     => 'main.php',
				'priority' => 'critical',
			),
			array( 'path' => 'broken.php' ), // Missing priority.
			array( 'priority' => 'low' ),     // Missing path.
		);

		$result = Plugin_Automated_Review::normalize_file_priorities( $input );

		$this->assertCount( 1, $result );
		$this->assertSame( 'critical', $result['main.php'] );
	}

	/**
	 * Test PHP files get critical priority.
	 */
	public function test_build_default_triage_php_files_are_critical(): void {
		$source_files = array(
			array(
				'path' => 'main.php',
				'size' => 1000,
				'ext'  => 'php',
			),
		);

		$result = Plugin_Automated_Review::build_default_triage( $source_files, array() );

		$this->assertSame( 'critical', $result['file_priorities']['main.php'] );
	}

	/**
	 * Test JS/TS files get normal priority.
	 */
	public function test_build_default_triage_js_files_are_normal(): void {
		$source_files = array(
			array(
				'path' => 'app.js',
				'size' => 500,
				'ext'  => 'js',
			),
			array(
				'path' => 'app.tsx',
				'size' => 500,
				'ext'  => 'tsx',
			),
		);

		$result = Plugin_Automated_Review::build_default_triage( $source_files, array() );

		$this->assertSame( 'normal', $result['file_priorities']['app.js'] );
		$this->assertSame( 'normal', $result['file_priorities']['app.tsx'] );
	}

	/**
	 * Test translation files get skip priority.
	 */
	public function test_build_default_triage_translation_files_are_skipped(): void {
		$source_files = array(
			array(
				'path' => 'lang/plugin.pot',
				'size' => 2000,
				'ext'  => 'pot',
			),
			array(
				'path' => 'lang/plugin-de.po',
				'size' => 3000,
				'ext'  => 'po',
			),
		);

		$result = Plugin_Automated_Review::build_default_triage( $source_files, array() );

		$this->assertSame( 'skip', $result['file_priorities']['lang/plugin.pot'] );
		$this->assertSame( 'skip', $result['file_priorities']['lang/plugin-de.po'] );
	}

	/**
	 * Test PCP errors promote files to critical priority.
	 */
	public function test_build_default_triage_pcp_errors_promote_to_critical(): void {
		$source_files = array(
			array(
				'path' => 'style.css',
				'size' => 500,
				'ext'  => 'css',
			),
		);
		$pcp_results  = array(
			array(
				'type'    => 'ERROR',
				'file'    => 'my-plugin/style.css',
				'line'    => 1,
				'message' => 'Issue',
			),
		);

		$result = Plugin_Automated_Review::build_default_triage( $source_files, $pcp_results );

		$this->assertSame( 'critical', $result['file_priorities']['style.css'] );
	}

	/**
	 * Test other file types get low priority.
	 */
	public function test_build_default_triage_other_files_are_low(): void {
		$source_files = array(
			array(
				'path' => 'readme.md',
				'size' => 100,
				'ext'  => 'md',
			),
		);

		$result = Plugin_Automated_Review::build_default_triage( $source_files, array() );

		$this->assertSame( 'low', $result['file_priorities']['readme.md'] );
	}

	/**
	 * Test CSS files without PCP errors get low priority.
	 */
	public function test_build_default_triage_css_without_pcp_is_low(): void {
		$source_files = array(
			array(
				'path' => 'style.css',
				'size' => 500,
				'ext'  => 'css',
			),
		);

		$result = Plugin_Automated_Review::build_default_triage( $source_files, array() );

		$this->assertSame( 'low', $result['file_priorities']['style.css'] );
	}

	/**
	 * Test empty source files produce no batches.
	 */
	public function test_build_batches_empty_source(): void {
		$triage = array( 'file_priorities' => array() );

		$this->assertSame( array(), Plugin_Automated_Review::build_batches( array(), $triage ) );
	}

	/**
	 * Test skip-priority files are excluded from batches.
	 */
	public function test_build_batches_skips_skip_priority(): void {
		$source_files = array(
			array(
				'path' => 'plugin.pot',
				'size' => 100,
				'ext'  => 'pot',
			),
		);
		$triage       = array( 'file_priorities' => array( 'plugin.pot' => 'skip' ) );

		$this->assertSame( array(), Plugin_Automated_Review::build_batches( $source_files, $triage ) );
	}

	/**
	 * Test multiple skip-priority files still return empty batches.
	 */
	public function test_build_batches_all_skip_priority_returns_empty(): void {
		$source_files = array(
			array(
				'path' => 'lang/plugin.pot',
				'size' => 2000,
				'ext'  => 'pot',
			),
			array(
				'path' => 'lang/plugin-de.po',
				'size' => 3000,
				'ext'  => 'po',
			),
			array(
				'path' => 'lang/plugin-fr.po',
				'size' => 3000,
				'ext'  => 'po',
			),
		);
		$triage       = array(
			'file_priorities' => array(
				'lang/plugin.pot'   => 'skip',
				'lang/plugin-de.po' => 'skip',
				'lang/plugin-fr.po' => 'skip',
			),
		);

		$this->assertSame( array(), Plugin_Automated_Review::build_batches( $source_files, $triage ) );
	}

	/**
	 * Test oversized files get their own batch.
	 */
	public function test_build_batches_oversized_files_get_own_batch(): void {
		$source_files = array(
			array(
				'path' => 'huge.php',
				'size' => Plugin_Automated_Review::BATCH_SIZE_TARGET + 1,
				'ext'  => 'php',
			),
			array(
				'path' => 'small.php',
				'size' => 100,
				'ext'  => 'php',
			),
		);
		$triage       = array( 'file_priorities' => array() );

		$batches = Plugin_Automated_Review::build_batches( $source_files, $triage );

		$this->assertCount( 2, $batches );

		// Find each batch by its contents, not by position.
		$batch_paths = array();
		foreach ( $batches as $batch ) {
			$batch_paths[] = array_column( $batch['files'], 'path' );
		}

		$this->assertContains( array( 'small.php' ), $batch_paths );
		$this->assertContains( array( 'huge.php' ), $batch_paths );
	}

	/**
	 * Test batches sort critical files first.
	 */
	public function test_build_batches_sorts_critical_first(): void {
		$source_files = array(
			array(
				'path' => 'low.css',
				'size' => 100,
				'ext'  => 'css',
			),
			array(
				'path' => 'critical.php',
				'size' => 100,
				'ext'  => 'php',
			),
			array(
				'path' => 'normal.js',
				'size' => 100,
				'ext'  => 'js',
			),
		);
		$triage       = array(
			'file_priorities' => array(
				'low.css'      => 'low',
				'critical.php' => 'critical',
				'normal.js'    => 'normal',
			),
		);

		$batches = Plugin_Automated_Review::build_batches( $source_files, $triage );

		$this->assertCount( 1, $batches );
		$paths = array_column( $batches[0]['files'], 'path' );
		$this->assertSame( 'critical.php', $paths[0] );
		$this->assertSame( 'normal.js', $paths[1] );
		$this->assertSame( 'low.css', $paths[2] );
	}

	/**
	 * Test batches split when combined size exceeds target.
	 */
	public function test_build_batches_respects_batch_size_target(): void {
		$half_batch = (int) ( Plugin_Automated_Review::BATCH_SIZE_TARGET * 0.6 );

		$source_files = array(
			array(
				'path' => 'a.php',
				'size' => $half_batch,
				'ext'  => 'php',
			),
			array(
				'path' => 'b.php',
				'size' => $half_batch,
				'ext'  => 'php',
			),
			array(
				'path' => 'c.php',
				'size' => $half_batch,
				'ext'  => 'php',
			),
		);
		$triage       = array( 'file_priorities' => array() );

		$batches = Plugin_Automated_Review::build_batches( $source_files, $triage );

		// 3 files at 60% of target each = 180% total, should produce at least 2 batches.
		$this->assertGreaterThanOrEqual( 2, count( $batches ) );
	}

	/**
	 * Test basic file collection.
	 */
	public function test_collect_files_basic(): void {
		$dir = $this->create_plugin_dir(
			array(
				'my-plugin/main.php'  => '<?php // Plugin.',
				'my-plugin/style.css' => 'body {}',
				'my-plugin/image.png' => 'binary',
			)
		);

		$result = Plugin_Automated_Review::collect_files( $dir );

		$this->assertCount( 3, $result['all'] );
		$this->assertCount( 2, $result['source'] );

		$source_paths = array_column( $result['source'], 'path' );
		$this->assertContains( 'my-plugin/main.php', $source_paths );
		$this->assertContains( 'my-plugin/style.css', $source_paths );
		$this->assertNotContains( 'my-plugin/image.png', $source_paths );
	}

	/**
	 * Test vendor/node_modules are excluded from source but included in all_files.
	 */
	public function test_collect_files_skips_vendor_in_nested_dir(): void {
		$dir = $this->create_plugin_dir(
			array(
				'my-plugin/main.php'                      => '<?php // Plugin.',
				'my-plugin/vendor/autoload.php'           => '<?php // Autoload.',
				'my-plugin/node_modules/package/index.js' => 'module.exports = {};',
			)
		);

		$result = Plugin_Automated_Review::collect_files( $dir );

		$source_paths = array_column( $result['source'], 'path' );
		$this->assertContains( 'my-plugin/main.php', $source_paths );
		$this->assertNotContains( 'my-plugin/vendor/autoload.php', $source_paths );
		$this->assertNotContains( 'my-plugin/node_modules/package/index.js', $source_paths );

		// But they should still be in all_files.
		$this->assertContains( 'my-plugin/vendor/autoload.php', $result['all'] );
		$this->assertContains( 'my-plugin/node_modules/package/index.js', $result['all'] );
	}

	/**
	 * Test empty PCP results produce a zeroed summary.
	 */
	public function test_summarize_pcp_results_empty(): void {
		$result = Plugin_Automated_Review::summarize_pcp_results( array() );

		$this->assertSame( 0, $result['errors'] );
		$this->assertSame( 0, $result['warnings'] );
		$this->assertEmpty( $result['error_files'] );
		$this->assertSame( '', $result['formatted'] );
	}

	/**
	 * Test PCP results count errors and warnings correctly.
	 */
	public function test_summarize_pcp_results_counts_correctly(): void {
		$pcp = array(
			array(
				'type'    => 'ERROR',
				'file'    => 'main.php',
				'line'    => 10,
				'message' => 'Issue 1',
			),
			array(
				'type'    => 'ERROR',
				'file'    => 'main.php',
				'line'    => 20,
				'message' => 'Issue 2',
			),
			array(
				'type'    => 'WARNING',
				'file'    => 'helper.php',
				'line'    => 5,
				'message' => 'Warning 1',
			),
		);

		$result = Plugin_Automated_Review::summarize_pcp_results( $pcp );

		$this->assertSame( 2, $result['errors'] );
		$this->assertSame( 1, $result['warnings'] );
		$this->assertContains( 'main.php', $result['error_files'] );
		$this->assertNotContains( 'helper.php', $result['error_files'] );
	}

	/**
	 * Test PCP summary formatted output contains file names and counts.
	 */
	public function test_summarize_pcp_results_formatted_contains_file_list(): void {
		$pcp = array(
			array(
				'type'    => 'ERROR',
				'file'    => 'main.php',
				'line'    => 10,
				'message' => 'Issue',
			),
			array(
				'type'    => 'ERROR',
				'file'    => 'main.php',
				'line'    => 20,
				'message' => 'Another issue',
			),
		);

		$result = Plugin_Automated_Review::summarize_pcp_results( $pcp );

		$this->assertStringContainsString( 'main.php', $result['formatted'] );
		$this->assertStringContainsString( '2 errors', $result['formatted'] );
	}

	/**
	 * Test approve verdict note formatting.
	 */
	public function test_format_as_note_approve(): void {
		$review = array(
			'verdict'  => 'approve',
			'summary'  => 'Clean plugin.',
			'blockers' => array(),
			'warnings' => array(),
			'info'     => array(),
		);

		$note = Plugin_Automated_Review::format_as_note( $review );

		$this->assertStringContainsString( '✅ APPROVE', $note );
		$this->assertStringContainsString( 'Clean plugin.', $note );
		$this->assertStringContainsString( 'Blockers:</strong> 0', $note );
	}

	/**
	 * Test reject verdict with blocker note formatting.
	 */
	public function test_format_as_note_reject_with_blockers(): void {
		$review = array(
			'verdict'  => 'reject',
			'summary'  => 'Security issues found.',
			'blockers' => array(
				array(
					'title'       => 'SQL Injection',
					'description' => 'Unsafe wpdb query.',
					'locations'   => array( 'main.php:42' ),
				),
			),
			'warnings' => array(),
			'info'     => array(),
		);

		$note = Plugin_Automated_Review::format_as_note( $review );

		$this->assertStringContainsString( '❌ REJECT', $note );
		$this->assertStringContainsString( 'SQL Injection', $note );
		$this->assertStringContainsString( 'main.php:42', $note );
	}

	/**
	 * Test note formatting with missing fields defaults gracefully.
	 */
	public function test_format_as_note_defaults_missing_fields(): void {
		$note = Plugin_Automated_Review::format_as_note( array() );

		$this->assertStringContainsString( 'UNKNOWN', $note );
		$this->assertStringContainsString( 'Blockers:</strong> 0', $note );
	}

	/**
	 * Test PCP formatting returns empty for non-matching files.
	 */
	public function test_format_pcp_for_file_no_match(): void {
		$pcp = array(
			array(
				'file'    => 'other.php',
				'type'    => 'ERROR',
				'line'    => 1,
				'message' => 'Issue',
			),
		);

		$this->assertSame( '', Plugin_Automated_Review::format_pcp_for_file( 'main.php', $pcp ) );
	}

	/**
	 * Test PCP formatting includes matching findings.
	 */
	public function test_format_pcp_for_file_matches(): void {
		$pcp = array(
			array(
				'file'    => 'my-plugin/main.php',
				'type'    => 'ERROR',
				'code'    => 'SNIFF.Check',
				'line'    => 10,
				'message' => 'Bad code',
			),
		);

		$output = Plugin_Automated_Review::format_pcp_for_file( 'main.php', $pcp );

		$this->assertStringContainsString( 'PCP findings for main.php', $output );
		$this->assertStringContainsString( 'LINE 10', $output );
		$this->assertStringContainsString( 'Bad code', $output );
	}

	/**
	 * Test PCP file matching works with reverse suffix direction.
	 */
	public function test_format_pcp_for_file_reverse_suffix_match(): void {
		$pcp = array(
			array(
				'file'    => 'main.php',
				'type'    => 'ERROR',
				'code'    => 'SNIFF.Check',
				'line'    => 5,
				'message' => 'Found issue',
			),
		);

		$output = Plugin_Automated_Review::format_pcp_for_file( 'my-plugin/main.php', $pcp );

		$this->assertStringContainsString( 'PCP findings for my-plugin/main.php', $output );
		$this->assertStringContainsString( 'Found issue', $output );
	}

	/**
	 * Test readme.txt is found at the root.
	 */
	public function test_find_readme_content_finds_readme_txt(): void {
		$dir = $this->create_plugin_dir(
			array( 'readme.txt' => '=== My Plugin ===' )
		);

		$source_files = array(
			array(
				'path' => 'readme.txt',
				'size' => 18,
				'ext'  => 'txt',
			),
		);

		$this->assertSame( '=== My Plugin ===', Plugin_Automated_Review::find_readme_content( $dir, $source_files ) );
	}

	/**
	 * Test nested README.md is found.
	 */
	public function test_find_readme_content_finds_nested_readme(): void {
		$dir = $this->create_plugin_dir(
			array( 'my-plugin/README.md' => '# My Plugin' )
		);

		$source_files = array(
			array(
				'path' => 'my-plugin/README.md',
				'size' => 11,
				'ext'  => 'md',
			),
		);

		$this->assertSame( '# My Plugin', Plugin_Automated_Review::find_readme_content( $dir, $source_files ) );
	}

	/**
	 * Test empty string returned when no readme exists.
	 */
	public function test_find_readme_content_returns_empty_when_absent(): void {
		$source_files = array(
			array(
				'path' => 'main.php',
				'size' => 100,
				'ext'  => 'php',
			),
		);

		$this->assertSame( '', Plugin_Automated_Review::find_readme_content( '/nonexistent', $source_files ) );
	}
}
