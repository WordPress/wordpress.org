<?php
/**
 * Tests that Markdown headings reach Trac as Trac headings.
 *
 * Trac reads WikiCreole's `=`, not Markdown's `#`, so an unconverted heading
 * renders as its own literal text. The reverse also holds: a body that opens a
 * line with `=` writes a heading the pull request never asked for.
 *
 * @package trac-pr
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Trac\GithubPRs;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the heading handling of the GitHub to Trac comment composer.
 */
class Trac_Comment_Heading_Test extends TestCase {

	/**
	 * Every Markdown heading level has a Trac heading of the same depth.
	 *
	 * @dataProvider data_heading_levels
	 *
	 * @param string $body   A pull request body.
	 * @param string $expect The heading it must compose.
	 * @return void
	 */
	public function test_a_heading_is_converted( string $body, string $expect ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( $expect, $desc );
	}

	/**
	 * Supplies one pull request heading per level.
	 *
	 * @return array Bodies and the headings they must compose.
	 */
	public function data_heading_levels(): array {
		return array(
			'level one'   => array( "# Summary\n\nText.\n", '= Summary =' ),
			'level two'   => array( "## Summary\n\nText.\n", '== Summary ==' ),
			'level three' => array( "### Summary\n\nText.\n", '=== Summary ===' ),
			'level four'  => array( "#### Summary\n\nText.\n", '==== Summary ====' ),
			'level five'  => array( "##### Summary\n\nText.\n", '===== Summary =====' ),
			'level six'   => array( "###### Summary\n\nText.\n", '====== Summary ======' ),
		);
	}

	/**
	 * A seventh `#` is past Trac's deepest heading, so the line is left as text.
	 *
	 * @return void
	 */
	public function test_a_seventh_level_is_not_a_heading() {
		$desc = format_github_content_for_trac_comment( "####### Summary\n\nText.\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '####### Summary', $desc );
	}

	/**
	 * Markdown's optional closing run belongs to the marker, not to the title.
	 *
	 * @return void
	 */
	public function test_a_closed_heading_keeps_only_its_title() {
		$desc = format_github_content_for_trac_comment( "## Summary ##\n\nText.\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '== Summary ==', $desc );
		$this->assertStringNotContainsString( '## ', $desc );
	}

	/**
	 * Up to three leading spaces are Markdown's, and Trac has no use for them.
	 *
	 * @return void
	 */
	public function test_an_indented_heading_is_converted() {
		$desc = format_github_content_for_trac_comment( "Text.\n\n   ## Summary\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "\n== Summary ==", $desc );
	}

	/**
	 * A line that only looks like a heading must reach Trac as it was written.
	 *
	 * @dataProvider data_non_headings
	 *
	 * @param string $body A pull request body opening a line with `#`.
	 * @return void
	 */
	public function test_a_hash_that_is_not_a_heading_is_left_alone( string $body ) {
		$this->assertSame( trim( $body ), format_github_content_for_trac_comment( $body ) );
	}

	/**
	 * Supplies the line-opening hashes that are not Markdown headings.
	 *
	 * @return array Bodies that must be composed unchanged.
	 */
	public function data_non_headings(): array {
		return array(
			'ticket reference' => array( "#65845 is the ticket.\n" ),
			'no space'         => array( "#Summary\n" ),
			'interpreter line' => array( "#!/bin/sh\n" ),
			'empty heading'    => array( "## \n" ),
			'four spaces'      => array( "Text.\n\n    ## Summary\n" ),
		);
	}

	/**
	 * Trac strips a citation's `>` before it reads the line, so a quote has headings too.
	 *
	 * @dataProvider data_cited_headings
	 *
	 * @param string $body   A pull request body quoting a heading.
	 * @param string $expect The quoted line it must compose.
	 * @return void
	 */
	public function test_a_cited_heading_keeps_its_citation( string $body, string $expect ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( $expect, $desc );
	}

	/**
	 * Supplies the quoted lines Trac reads as a heading.
	 *
	 * @return array Bodies and the quoted lines they must compose.
	 */
	public function data_cited_headings(): array {
		return array(
			'converted'        => array( "> ## Summary\n", '> == Summary ==' ),
			'no space'         => array( ">## Summary\n", '> == Summary ==' ),
			'nested'           => array( "> > ## Summary\n", '> > == Summary ==' ),
			'escaped'          => array( "> == Summary\n", '> !== Summary' ),
			'escaped nested'   => array( "> > == Summary\n", '> > !== Summary' ),
			'escaped indented' => array( ">   == Summary\n", '>   !== Summary' ),
		);
	}

	/**
	 * A quotation deep enough to exhaust PCRE's stack must still reach the ticket.
	 *
	 * A pattern that repeats a group rather than naming a character class gives up on
	 * a line like this, and a refused span takes the whole comment with it.
	 *
	 * @dataProvider data_deep_citations
	 *
	 * @param string $line   The line to quote, once the citation is prepended.
	 * @param string $expect The end of the line it must compose.
	 * @return void
	 */
	public function test_a_deep_citation_does_not_refuse_the_comment( string $line, string $expect ) {
		// Two characters per level, within GitHub's 65536 character body.
		$desc = format_github_content_for_trac_comment( str_repeat( '> ', 30000 ) . $line );

		$this->assertIsString( $desc );
		$this->assertStringEndsWith( $expect, $desc );
	}

	/**
	 * Supplies the deeply quoted lines that must survive the composer.
	 *
	 * @return array Lines to quote and the endings they must compose.
	 */
	public function data_deep_citations(): array {
		return array(
			'converted' => array( "## Summary\n", '== Summary ==' ),
			'escaped'   => array( "== Summary\n", '!== Summary' ),
			'neither'   => array( "Summary\n", '> Summary' ),
		);
	}

	/**
	 * A heading's own inline code must stay inside the heading.
	 *
	 * The composer splits a span on its inline code, so a heading cut in half by a
	 * backtick would otherwise close over the first piece alone.
	 *
	 * @return void
	 */
	public function test_a_heading_holding_inline_code_stays_one_heading() {
		$desc = format_github_content_for_trac_comment( "## The `retention-days` key\n\nText.\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '== The `retention-days` key ==', $desc );
	}

	/**
	 * Inline code that opens a line is content, not a marker.
	 *
	 * @return void
	 */
	public function test_a_hash_inside_inline_code_is_not_a_heading() {
		$desc = format_github_content_for_trac_comment( "Write `## Summary` for a heading.\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '`## Summary`', $desc );
	}

	/**
	 * A heading's links and images are converted with the rest of the prose.
	 *
	 * @return void
	 */
	public function test_a_heading_still_converts_its_links() {
		$desc = format_github_content_for_trac_comment( "## See [the docs](https://example.org/doc)\n" );

		$this->assertSame( '== See [https://example.org/doc the docs] ==', $desc );
	}

	/**
	 * A fenced block's contents are code, so its hashes are not headings.
	 *
	 * @return void
	 */
	public function test_a_hash_inside_a_fence_is_not_a_heading() {
		$desc = format_github_content_for_trac_comment( "Text.\n\n```sh\n## Summary\n```\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "\n## Summary\n", $desc );
		$this->assertStringNotContainsString( '== Summary ==', $desc );
	}

	/**
	 * Trac's own heading marker is the composer's to write, not the body's.
	 *
	 * @dataProvider data_wiki_headings
	 *
	 * @param string $body   A pull request body opening a line with `=`.
	 * @param string $expect The escaped line it must compose.
	 * @return void
	 */
	public function test_a_wiki_heading_in_the_body_is_escaped( string $body, string $expect ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( $expect, $desc );
	}

	/**
	 * Supplies the line-opening `=` runs Trac would read as a heading.
	 *
	 * @return array Bodies and the escaped lines they must compose.
	 */
	public function data_wiki_headings(): array {
		return array(
			'bare'           => array( "== Summary\n", '!== Summary' ),
			'closed'         => array( "== Summary ==\n", '!== Summary ==' ),
			'indented'       => array( "Text.\n\n  = Summary =\n", "\n  !=" ),
			'deepest'        => array( "====== Summary\n", '!====== Summary' ),
			'tab spaced'     => array( "==\tSummary\n", "!==\tSummary" ),
			// Trac steps over Python's whitespace, which is wider than PCRE's.
			'no-break'       => array( "Text.\n\n\u{00A0}== Summary\n", "\u{00A0}!== Summary" ),
			'en space'       => array( "Text.\n\n\u{2002}== Summary\n", "\u{2002}!== Summary" ),
			'ideographic'    => array( "Text.\n\n\u{3000}== Summary\n", "\u{3000}!== Summary" ),
			'unit separator' => array( "Text.\n\n\x1f== Summary\n", "\x1f!== Summary" ),
		);
	}

	/**
	 * An `=` that opens no heading of Trac's is prose, and stays prose.
	 *
	 * @dataProvider data_inert_equals
	 *
	 * @param string $body A pull request body opening a line with `=`.
	 * @return void
	 */
	public function test_an_inert_equals_is_left_alone( string $body ) {
		$this->assertSame( trim( $body ), format_github_content_for_trac_comment( $body ) );
	}

	/**
	 * Supplies the line-opening `=` runs Trac reads as text.
	 *
	 * @return array Bodies that must be composed unchanged.
	 */
	public function data_inert_equals(): array {
		return array(
			'no space' => array( "=Summary\n" ),
			'too deep' => array( "======= Summary\n" ),
			'arrow'    => array( "=> Returns the value.\n" ),
		);
	}

	/**
	 * A heading must not gain the escape written for the line above it.
	 *
	 * @return void
	 */
	public function test_an_escape_and_a_heading_can_share_a_body() {
		$desc = format_github_content_for_trac_comment( "== Written\n\n## Composed\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '!== Written', $desc );
		$this->assertStringContainsString( '== Composed ==', $desc );
		$this->assertStringNotContainsString( '!== Composed', $desc );
	}
}
