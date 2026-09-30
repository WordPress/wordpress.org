<?php
/**
 * Tests for how the reports page renders request data into its own links.
 *
 * @package wporg-trac-watcher
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

use function WordPressdotorg\Trac\Watcher\display_reports_page;

/**
 * Covers the report links, which are built out of the request with
 * add_query_arg() and then echoed into an href.
 *
 * Only the query string already on the base URL gets encoded on the way through:
 * the arguments handed to add_query_arg() reach build_query(), which calls
 * _http_build_query() with $urlencode false, and that skips urlencode() on keys
 * and values alike. What comes back is as tainted as what went in, so it still
 * needs escaping at the sink.
 */
class WPorg_Trac_Watcher_Reports_Page_Test extends WPorg_Trac_Watcher_TestCase {

	/**
	 * A request value that closes the href and opens a tag of its own.
	 *
	 * @var string
	 */
	const MARKUP_VALUE = '"><img src=x onerror=alert(1)>';

	/**
	 * The slug the reports screen is registered under for core.
	 *
	 * @var string
	 */
	const PAGE = 'props-reports-core';

	/**
	 * Renders the reports page for core.
	 *
	 * @return string
	 */
	protected function render_reports_page(): string {
		ob_start();
		display_reports_page( $this->svn );

		return (string) ob_get_clean();
	}

	/**
	 * Returns the href of every report link on the page.
	 *
	 * The report links are the ones carrying a `what` argument, which separates
	 * them from the profile links the individual reports render.
	 *
	 * @param string $html The rendered page.
	 * @return string[]
	 */
	protected function get_report_hrefs( string $html ): array {
		preg_match_all( '/href="([^"]*what=[^"]*)"/', $html, $matches );

		return $matches[1];
	}

	/*
	 * The report links.
	 */

	/**
	 * The version is the only free-form argument that reaches the links, so it is
	 * the one an attacker-supplied URL would carry.
	 */
	public function test_report_links_escape_a_version_from_the_request(): void {
		$_REQUEST['page']    = self::PAGE;
		$_REQUEST['version'] = self::MARKUP_VALUE;

		$output = $this->render_reports_page();

		/*
		 * The guard has to hold whether or not the escaping does, otherwise it fails
		 * first on the very output it exists to rule out and reports the wrong thing.
		 * The label is in the template either way; only the ampersand before it moves.
		 */
		$this->assertStringContainsString(
			'what=contributors',
			$output,
			'The page rendered no report links, so the assertions below would pass vacuously.'
		);
		$this->assertStringNotContainsString( '<img', $output, 'The version broke out of the href and became a live tag.' );
		$this->assertStringNotContainsString( 'onerror=alert(1)>', $output, 'The version kept a usable event handler.' );
	}

	/**
	 * Guards the test above against passing because the version never reaches the
	 * links at all: an ordinary version has to still survive into every one of them.
	 */
	public function test_report_links_carry_an_ordinary_version(): void {
		$_REQUEST['page']    = self::PAGE;
		$_REQUEST['version'] = '7.1';

		$hrefs = $this->get_report_hrefs( $this->render_reports_page() );

		$this->assertNotEmpty( $hrefs );

		foreach ( $hrefs as $href ) {
			$this->assertStringContainsString( 'version=7.1', $href );
		}
	}

	/**
	 * The page slug reaches the same URL, and a hidden input below it. Neither is
	 * reachable in practice: wp-admin/admin.php resolves the slug against the
	 * registered menus and dies on a miss, and the version call re-parses the page
	 * call's argument as part of the query string it inherits, so urlencode_deep()
	 * catches it on the way through. Both sinks should still hold on their own.
	 */
	public function test_report_links_escape_a_page_from_the_request(): void {
		$_REQUEST['page'] = self::MARKUP_VALUE;

		$output = $this->render_reports_page();

		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringContainsString(
			'value="&quot;&gt;&lt;img src=x onerror=alert(1)&gt;"',
			$output,
			'The page slug is missing from the form, so the assertion above would pass vacuously.'
		);
	}

	/*
	 * The links inside an individual report.
	 */

	/**
	 * Each row of a report links back to the edit screen through a URL built the
	 * same way, from the same request data.
	 */
	public function test_contributors_report_escapes_a_page_in_the_row_links(): void {
		$user = $this->factory()->user->create(
			array(
				'user_login'   => 'alice',
				'display_name' => 'Alice Example',
			)
		);

		$this->seed_revision( 'Fix the thing. Props alice.' );
		$this->seed_props( array( 'alice' => $user ) );

		$_REQUEST['page'] = self::MARKUP_VALUE;
		$_REQUEST['what'] = 'contributors';

		$output = $this->render_reports_page();

		$this->assertStringContainsString(
			'Alice Example',
			$output,
			'The report rendered no rows, so the assertion below would pass vacuously.'
		);
		$this->assertStringNotContainsString( '<img src=x', $output, 'The page slug broke out of a row link and became a live tag.' );
	}
}
