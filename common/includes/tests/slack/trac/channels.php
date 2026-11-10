<?php
/**
 * Tests for routing Core Trac notifications to Slack channels.
 *
 * @package WordPressdotorg\Slack\Trac
 */

declare( strict_types = 1 );

require_once dirname( dirname( dirname( __DIR__ ) ) ) . '/slack/autoload.php';

use Dotorg\Slack\Trac\Trac;

/**
 * Tests for the component channels of Dotorg\Slack\Trac\Trac.
 */
class Test_Slack_Trac_Channels extends \PHPUnit\Framework\TestCase {

	/**
	 * Core commits post to the global channels and the component's channels.
	 *
	 * @dataProvider data_core_commit_channels
	 *
	 * @param string   $changed_files Changed file paths, one per line.
	 * @param string   $log           Commit message.
	 * @param string[] $expected      Expected channels.
	 */
	public function test_core_commit_channels( string $changed_files, string $log, array $expected ): void {
		$channels = Trac::get( 'core' )->get_commit_channels( $changed_files, $log );

		sort( $channels );
		sort( $expected );
		$this->assertSame( $expected, $channels );
	}

	/**
	 * Data provider for test_core_commit_channels().
	 *
	 * @return array[]
	 */
	public static function data_core_commit_channels(): array {
		$global = array( '#core', '#core-commits', '#core-firehose' );

		return array(
			'component prefix'                       => array(
				'trunk/src/wp-admin/import.php',
				"Import: Check the file type.\n\nFixes #12345.",
				array_merge( $global, array( '#core-importers-commits', '#core-importers-firehose' ) ),
			),
			'component without a discussion channel' => array(
				'trunk/src/wp-includes/class-wp-query.php',
				'Query: Cache the results.',
				array_merge( $global, array( '#core-query-commits', '#core-query-firehose' ) ),
			),
			'archived channel path filter'           => array(
				'trunk/src/wp-includes/sitemaps/class-wp-sitemaps.php',
				'Fix a typo.',
				array_merge( $global, array( '#core-sitemaps-commits', '#core-sitemaps-firehose' ) ),
			),
			'subcomponent prefix'                    => array(
				'trunk/src/wp-includes/embed.php',
				'Embeds: Fix the iframe title.',
				array_merge( $global, array( '#core-media-commits', '#core-media-firehose' ) ),
			),
			'bundled themes'                         => array(
				'trunk/src/wp-includes/theme.php',
				'Bundled Themes: Fix a typo.',
				array_merge( $global, array( '#core-themes-commits', '#core-themes-firehose' ) ),
			),
			'path filter'                            => array(
				'trunk/src/wp-includes/html-api/class-wp-html-processor.php',
				'Fix a typo.',
				array_merge( $global, array( '#core-html-api-commits', '#core-html-api-firehose' ) ),
			),
			'path filter and prefix'                 => array(
				'trunk/src/wp-includes/media.php',
				'I18N: Add context to a string.',
				array_merge( $global, array( '#core-i18n-commits', '#core-i18n-firehose', '#core-media-commits', '#core-media-firehose' ) ),
			),
			'no component'                           => array(
				'trunk/src/wp-includes/formatting.php',
				'Docs: Correct a parameter type.',
				$global,
			),
		);
	}

	/**
	 * New Core tickets post to the global channels and the component's channels.
	 */
	public function test_core_new_ticket_channels(): void {
		$ticket = (object) array(
			'component' => 'Gallery',
			'focuses'   => 'css, javascript',
			'keywords'  => 'has-patch needs-testing',
		);

		$channels = Trac::get( 'core' )->get_ticket_channels( $ticket );

		sort( $channels );
		$this->assertSame(
			array( '#core', '#core-firehose', '#core-media-firehose', '#core-media-newtickets', '#core-newtickets', '#core-test' ),
			$channels
		);
	}

	/**
	 * New Core tickets in a component that isn't listed post only to the global channels.
	 */
	public function test_core_new_ticket_unlisted_component(): void {
		$channels = Trac::get( 'core' )->get_ticket_channels( (object) array( 'component' => 'Text Changes' ) );

		sort( $channels );
		$this->assertSame( array( '#core', '#core-firehose', '#core-newtickets' ), $channels );
	}

	/**
	 * Core ticket updates post to the component's firehose.
	 */
	public function test_core_component_firehose_channel(): void {
		$trac = Trac::get( 'core' );

		$this->assertSame( '#core-passwords-firehose', $trac->get_component_firehose_channel( 'Login and Registration' ) );
		$this->assertSame( '#core-formatting-firehose', $trac->get_component_firehose_channel( 'Shortcodes' ) );
		$this->assertFalse( $trac->get_component_firehose_channel( 'Text Changes' ) );
		$this->assertFalse( $trac->get_component_firehose_channel( 'Not A Component' ) );
	}

	/**
	 * Meta has no component channels, so its routing is unchanged.
	 */
	public function test_meta_commit_channels_are_unchanged(): void {
		$channels = Trac::get( 'meta' )->get_commit_channels(
			'sites/trunk/wordpress.org/public_html/wp-content/plugins/plugin-directory/class-plugin-directory.php',
			'Plugin Directory: Fix a thing.'
		);

		sort( $channels );
		$this->assertSame( array( '#meta', '#meta-commits', '#meta-firehose', '#pluginreview' ), $channels );
	}
}
