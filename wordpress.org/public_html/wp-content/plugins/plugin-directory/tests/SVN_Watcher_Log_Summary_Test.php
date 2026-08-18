<?php
/**
 * Tests for SVN_Watcher::summarize_plugin_changes().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\SVN_Watcher;

/**
 * Tests that SVN log entries are attributed to the right plugin.
 *
 * @group cli
 */
class SVN_Watcher_Log_Summary_Test extends TestCase {

	/**
	 * Invoke the protected SVN_Watcher::summarize_plugin_changes() helper.
	 *
	 * @param array $logs A list of log entries, as returned by SVN::log()'s 'log' key.
	 * @return array The per-plugin change data.
	 */
	private function summarize( array $logs ): array {
		$method = new ReflectionMethod( SVN_Watcher::class, 'summarize_plugin_changes' );
		$method->setAccessible( true );

		return $method->invoke( new SVN_Watcher(), $logs );
	}

	/**
	 * Build a log entry in the shape SVN::log() returns.
	 *
	 * @param int    $revision The revision number.
	 * @param array  $paths    Map of path => action, or a list of paths (assumed modified).
	 * @param string $author   The commit author.
	 * @param string $message  The commit message.
	 */
	private function log_entry( int $revision, array $paths, string $author = 'committer', string $message = '' ): array {
		$actions = array();

		foreach ( $paths as $key => $value ) {
			if ( is_int( $key ) ) {
				$actions[ $value ] = 'M';
			} else {
				$actions[ $key ] = $value;
			}
		}

		return array(
			'revision' => $revision,
			'author'   => $author,
			'date'     => 0,
			'paths'    => array_keys( $actions ),
			'actions'  => $actions,
			'message'  => $message,
		);
	}

	/**
	 * A single commit can span several plugins — a "bump Tested up to" sweep across a
	 * committer's whole portfolio, for example. Each path has to be attributed to the
	 * plugin it actually belongs to, rather than every path being credited to whichever
	 * plugin happened to sort first in the commit.
	 */
	public function test_commit_spanning_multiple_plugins_is_split_per_plugin() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					3644696,
					array(
						'/autoclose/tags/3.1.2/readme.txt',
						'/autoclose/trunk/readme.txt',
						'/better-search/tags/4.4.0/readme.txt',
						'/better-search/trunk/readme.txt',
						'/top-10/tags/4.4.2/readme.txt',
					),
					'Ajay',
					'Update Tested up to: 7.1'
				),
			)
		);

		$this->assertEqualsCanonicalizing(
			array( 'autoclose', 'better-search', 'top-10' ),
			array_keys( $plugins ),
			'Every plugin in the commit should be queued for import.'
		);

		$this->assertEqualsCanonicalizing( array( 'trunk', '3.1.2' ), $plugins['autoclose']['tags_touched'] );
		$this->assertEqualsCanonicalizing( array( 'trunk', '4.4.0' ), $plugins['better-search']['tags_touched'] );
		$this->assertEqualsCanonicalizing( array( '4.4.2' ), $plugins['top-10']['tags_touched'] );

		// The regression: another plugin's tags must never leak into the first plugin of the commit.
		$this->assertNotContains( '4.4.0', $plugins['autoclose']['tags_touched'] );
		$this->assertNotContains( '4.4.2', $plugins['autoclose']['tags_touched'] );
	}

	/**
	 * The per-plugin flags describe that plugin's paths only, and shouldn't be set by
	 * what a different plugin in the same commit happened to change.
	 */
	public function test_flags_are_not_shared_between_plugins_in_one_commit() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					100,
					array(
						'/plugin-a/trunk/readme.txt',
						'/plugin-b/trunk/plugin-b.php',
						'/plugin-c/assets/banner-772x250.png',
					)
				),
			)
		);

		$this->assertTrue( $plugins['plugin-a']['readme_touched'] );
		$this->assertFalse( $plugins['plugin-a']['code_touched'] );
		$this->assertFalse( $plugins['plugin-a']['assets_touched'] );

		$this->assertFalse( $plugins['plugin-b']['readme_touched'] );
		$this->assertTrue( $plugins['plugin-b']['code_touched'] );
		$this->assertFalse( $plugins['plugin-b']['assets_touched'] );

		$this->assertFalse( $plugins['plugin-c']['readme_touched'] );
		$this->assertTrue( $plugins['plugin-c']['assets_touched'] );
		$this->assertSame( array(), $plugins['plugin-c']['tags_touched'] );
	}

	/**
	 * Each plugin should record the revision once, however many of its paths the commit touched.
	 */
	public function test_revisions_are_recorded_once_per_commit() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					200,
					array(
						'/plugin-a/trunk/plugin-a.php',
						'/plugin-a/trunk/readme.txt',
						'/plugin-a/tags/1.0/plugin-a.php',
					)
				),
			)
		);

		$this->assertSame( array( 200 ), $plugins['plugin-a']['revisions'] );
		$this->assertEqualsCanonicalizing( array( 'trunk', '1.0' ), $plugins['plugin-a']['tags_touched'] );
	}

	/**
	 * Revisions accumulate across commits, and plugins come back ordered by their earliest revision.
	 */
	public function test_multiple_commits_accumulate_and_sort_by_earliest_revision() {
		$plugins = $this->summarize(
			array(
				$this->log_entry( 300, array( '/plugin-b/trunk/readme.txt' ) ),
				$this->log_entry( 301, array( '/plugin-a/trunk/readme.txt' ) ),
				$this->log_entry( 302, array( '/plugin-b/tags/2.0/readme.txt' ) ),
			)
		);

		$this->assertSame( array( 'plugin-b', 'plugin-a' ), array_keys( $plugins ) );
		$this->assertSame( array( 300, 302 ), $plugins['plugin-b']['revisions'] );
		$this->assertEqualsCanonicalizing( array( 'trunk', '2.0' ), $plugins['plugin-b']['tags_touched'] );
	}

	/**
	 * Deleting a tag directory is a deletion; deleting a file inside a tag is just a change to it.
	 */
	public function test_tag_deletion_is_distinguished_from_a_file_deletion_within_a_tag() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					400,
					array(
						'/plugin-a/tags/1.0'           => 'D',
						'/plugin-a/tags/2.0/stale.php' => 'D',
					)
				),
			)
		);

		$this->assertSame( array( '1.0' ), $plugins['plugin-a']['tags_deleted'] );
		$this->assertSame( array( '2.0' ), $plugins['plugin-a']['tags_touched'] );
	}

	/**
	 * Deleting /trunk leaves nothing to export, so it mustn't be queued as a touched version.
	 *
	 * This is a real release pattern: the tag is added and trunk deleted in one commit, then
	 * trunk is re-created from that tag a few seconds later. An import queued from the first
	 * commit runs while trunk is missing, and the ZIP build fails against a URL that's gone.
	 */
	public function test_trunk_deletion_is_not_queued_as_a_touched_version() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					3653586,
					array(
						'/wp-better-permalinks/tags/4.3.1'            => 'A',
						'/wp-better-permalinks/tags/4.3.1/readme.txt' => 'A',
						'/wp-better-permalinks/trunk'                 => 'D',
					)
				),
			)
		);

		$this->assertSame( array( '4.3.1' ), $plugins['wp-better-permalinks']['tags_touched'] );
		$this->assertNotContains( 'trunk', $plugins['wp-better-permalinks']['tags_touched'] );

		// trunk has no release of its own, so there's nothing for the importer to remove either.
		$this->assertSame( array(), $plugins['wp-better-permalinks']['tags_deleted'] );
	}

	/**
	 * Deleting a file inside /trunk is a change to trunk, not the removal of it.
	 */
	public function test_file_deletion_within_trunk_still_touches_trunk() {
		$plugins = $this->summarize(
			array(
				$this->log_entry( 600, array( '/plugin-a/trunk/deprecated.php' => 'D' ) ),
			)
		);

		$this->assertSame( array( 'trunk' ), $plugins['plugin-a']['tags_touched'] );
	}

	/**
	 * Once trunk is back, it's a change like any other — including when both commits land in
	 * the same batch of revisions, where the deletion and the re-creation are seen together.
	 */
	public function test_recreated_trunk_is_queued_again() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					3653586,
					array(
						'/plugin-a/tags/1.0' => 'A',
						'/plugin-a/trunk'    => 'D',
					)
				),
				$this->log_entry( 3653587, array( '/plugin-a/trunk' => 'A' ) ),
			)
		);

		$this->assertEqualsCanonicalizing( array( '1.0', 'trunk' ), $plugins['plugin-a']['tags_touched'] );
		$this->assertSame( array( 3653586, 3653587 ), $plugins['plugin-a']['revisions'] );
	}

	/**
	 * Paths that don't name anything below the plugin root carry no importable change.
	 */
	public function test_bare_plugin_root_paths_are_ignored() {
		$plugins = $this->summarize(
			array(
				$this->log_entry(
					500,
					array(
						'/plugin-a'                  => 'A',
						'/plugin-b/trunk/readme.txt' => 'M',
					)
				),
			)
		);

		$this->assertSame( array( 'plugin-b' ), array_keys( $plugins ) );
	}
}
