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
			'focus prefix'                           => array(
				'trunk/src/wp-includes/formatting.php',
				'Docs: Correct a parameter type.',
				array_merge( $global, array( '#core-docs' ) ),
			),
			'no component'                           => array(
				'trunk/src/wp-includes/formatting.php',
				'Fix a typo.',
				$global,
			),
			'short component prefix'                 => array(
				'trunk/src/wp-includes/ai.php',
				'AI: Fix a typo.',
				array_merge( $global, array( '#core-ai-commits', '#core-ai-firehose' ) ),
			),
			'more than one component in the prefix'  => array(
				'trunk/src/wp-includes/ai.php',
				'AI, REST API: Fix a typo.',
				array_merge( $global, array( '#core-ai-commits', '#core-ai-firehose' ) ),
			),
			'component name inside a longer word'    => array(
				'trunk/src/wp-includes/ai.php',
				'AIX compatibility fixes.',
				$global,
			),
			'commit prefix alias'                    => array(
				'trunk/src/wp-includes/class-wp-customize-manager.php',
				'Customizer: Fix a typo.',
				array_merge( $global, array( '#core-customize-commits', '#core-customize-firehose' ) ),
			),
			'plural commit prefix alias'             => array(
				'trunk/src/wp-includes/theme.php',
				'Bundled Themes: Fix a typo.',
				array_merge( $global, array( '#core-themes-commits', '#core-themes-firehose' ) ),
			),
			'alias that is a common verb'            => array(
				'trunk/package.json',
				'Update @wordpress packages.',
				$global,
			),
		);
	}

	/**
	 * New Core tickets post to the global channels, the component's channels and the focus channels.
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
			array( '#core', '#core-css', '#core-firehose', '#core-js', '#core-media-firehose', '#core-media-newtickets', '#core-newtickets', '#core-test' ),
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
	 * Components in more than one area post to every area's channels.
	 */
	public function test_core_component_in_more_than_one_area(): void {
		$trac = Trac::get( 'core' );

		$tickets = $trac->get_ticket_channels( (object) array( 'component' => 'Application Passwords' ) );
		sort( $tickets );
		$this->assertSame(
			array( '#core', '#core-firehose', '#core-newtickets', '#core-passwords-firehose', '#core-passwords-newtickets', '#core-restapi-firehose', '#core-restapi-newtickets' ),
			$tickets
		);

		$tickets = $trac->get_ticket_channels( (object) array( 'component' => 'Login and Registration' ) );
		sort( $tickets );
		$this->assertSame(
			array( '#core', '#core-firehose', '#core-newtickets', '#core-passwords-firehose', '#core-passwords-newtickets', '#core-users-firehose', '#core-users-newtickets' ),
			$tickets
		);

		// Path filter.
		$commits = $trac->get_commit_channels( 'trunk/src/wp-includes/class-wp-application-passwords.php', 'Fix a typo.' );
		sort( $commits );
		$this->assertSame(
			array( '#core', '#core-commits', '#core-firehose', '#core-passwords-commits', '#core-passwords-firehose', '#core-restapi-commits', '#core-restapi-firehose' ),
			$commits
		);

		// Commit message prefix.
		$commits = $trac->get_commit_channels( 'trunk/src/wp-login.php', 'Login and Registration: Fix a typo.' );
		sort( $commits );
		$this->assertSame(
			array( '#core', '#core-commits', '#core-firehose', '#core-passwords-commits', '#core-passwords-firehose', '#core-users-commits', '#core-users-firehose' ),
			$commits
		);
	}

	/**
	 * Core ticket updates post to the component's firehose channels.
	 */
	public function test_core_component_firehose_channels(): void {
		$trac = Trac::get( 'core' );

		$this->assertSame( array( '#core-passwords-firehose', '#core-users-firehose' ), $trac->get_component_firehose_channels( 'Login and Registration' ) );
		$this->assertSame( array( '#core-formatting-firehose' ), $trac->get_component_firehose_channels( 'Shortcodes' ) );
		$this->assertSame( array(), $trac->get_component_firehose_channels( 'Text Changes' ) );
		$this->assertSame( array(), $trac->get_component_firehose_channels( 'Not A Component' ) );
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
