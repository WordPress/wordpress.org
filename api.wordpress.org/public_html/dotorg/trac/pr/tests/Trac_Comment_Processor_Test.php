<?php
/**
 * Tests that pull request text cannot pick the Trac wiki processor of a synced comment.
 *
 * The webhook composes a Trac comment from a pull request body and posts it as
 * `prbot`. Trac reads the first line of a `{{{` block as a processor name, and
 * `#!div`, `#!span`, `#!td` and their siblings take their arguments as element
 * attributes, so a body that names one writes attributes into the rendered ticket.
 *
 * @package trac-pr
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Trac\GithubPRs;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the processor handling of the GitHub to Trac comment composer.
 */
class Trac_Comment_Processor_Test extends TestCase {

	/**
	 * A processor whose arguments Trac writes into the element it builds.
	 *
	 * @var string
	 */
	const PROCESSOR = '#!div style="color: red\22\3e\3cimg src=zzz onerror=alert(1)\3e"';

	/**
	 * Builds a fenced code block for a pull request body.
	 *
	 * @param string $language Language token for the fence; empty for none.
	 * @param string $body     Contents of the fence.
	 * @return string The pull request body.
	 */
	protected function fence( string $language, string $body ): string {
		return "Fixes #1.\n\n```{$language}\n{$body}\n```\n";
	}

	/**
	 * Names the processors Trac would honour in a composed comment.
	 *
	 * Walks the lines the way `Formatter.format()` does rather than pattern-matching
	 * the output, so a test cannot pass on markup that only looks escaped: a block
	 * opens on `{{{`, takes its name from that line or the next, and closes on `}}}`.
	 *
	 * @param string|false $desc A composed comment, or false if it was refused.
	 * @return array Processor names outside the set the composer may pick.
	 */
	protected function live_processors( $desc ): array {
		if ( ! is_string( $desc ) ) {
			return array();
		}

		$depth  = 0;
		$naming = false;
		$found  = array();

		foreach ( explode( "\n", $desc ) as $line ) {
			if ( $depth && '}}}' === trim( $line ) ) {
				--$depth;
				$naming = false;
				continue;
			}

			if ( ! str_contains( $line, '}}}' ) && preg_match( '~^[ \t>]*\{\{\{[ \t]*(?:#!(\S+))?[ \t]*$~', $line, $match ) ) {
				++$depth;
				if ( 1 === $depth ) {
					$naming = ! isset( $match[1] );
					if ( isset( $match[1] ) ) {
						$found[] = $match[1];
					}
				}
				continue;
			}

			if ( $naming ) {
				$naming = false;
				if ( preg_match( '~^[ \t>]*#!(\S+)~', $line, $match ) ) {
					$found[] = $match[1];
				}
			}
		}

		return array_values( array_diff( $found, trac_comment_processors() ) );
	}

	/**
	 * A fence with no language token must not carry a processor into the comment.
	 *
	 * The callback that applies the supported-processor list only matches a fence
	 * whose language token is `[a-z]+`, so this shape used to reach the fallback
	 * conversion, which named no processor and left the first line to do it.
	 *
	 * @return void
	 */
	public function test_language_less_fence_cannot_pick_a_processor() {
		$desc = format_github_content_for_trac_comment( $this->fence( '', self::PROCESSOR . "\nBODY" ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!default\n", $desc );
		$this->assertStringNotContainsString( "{{{\n#!div", $desc );
	}

	/**
	 * A language token that is not plain lowercase reaches the same fallback.
	 *
	 * @return void
	 */
	public function test_nonalpha_language_fence_cannot_pick_a_processor() {
		$desc = format_github_content_for_trac_comment( $this->fence( 'c++', self::PROCESSOR . "\nBODY" ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!default\n", $desc );
		$this->assertStringNotContainsString( "{{{\n#!div", $desc );
	}

	/**
	 * A body may open a block without a fence at all, and that must not sync.
	 *
	 * @return void
	 */
	public function test_a_raw_processor_block_is_neutralised() {
		$desc = format_github_content_for_trac_comment( "Fixes #1.\n\n{{{\n" . self::PROCESSOR . "\nBODY\n}}}\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '!{{{', $desc );
		$this->assertSame( array(), $this->live_processors( $desc ) );
	}

	/**
	 * Trac starts a line on more than `\n`, and the composer must read lines its way.
	 *
	 * PCRE's `\s` covers neither the C1 separators nor NEL, LS and PS, so a block
	 * opened with one of them used to leave the processor name unread.
	 *
	 * @dataProvider data_line_breaks
	 *
	 * @param string $line_break A character Trac breaks a line on.
	 * @return void
	 */
	public function test_a_block_opened_with_any_line_break_is_neutralised( string $line_break ) {
		$desc = format_github_content_for_trac_comment(
			"Fixes #1.\n\n{{{{$line_break}" . self::PROCESSOR . "{$line_break}BODY{$line_break}}}}\n"
		);

		$this->assertIsString( $desc );
		$this->assertSame( array(), $this->live_processors( $desc ) );
	}

	/**
	 * A fence broken only by one of those characters is still pinned to `default`.
	 *
	 * @dataProvider data_line_breaks
	 *
	 * @param string $line_break A character Trac breaks a line on.
	 * @return void
	 */
	public function test_a_fence_broken_by_any_line_break_is_pinned( string $line_break ) {
		$desc = format_github_content_for_trac_comment(
			"Fixes #1.\n\n```{$line_break}" . self::PROCESSOR . "{$line_break}BODY```\n"
		);

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!default\n", $desc );
		$this->assertStringNotContainsString( "{{{\n#!div", $desc );
	}

	/**
	 * Every character Python's `str.splitlines()` starts a new line on.
	 *
	 * @return array
	 */
	public static function data_line_breaks() {
		return array(
			'line feed'        => array( "\n" ),
			'carriage return'  => array( "\r" ),
			'vertical tab'     => array( "\x0b" ),
			'form feed'        => array( "\x0c" ),
			'file separator'   => array( "\x1c" ),
			'group separator'  => array( "\x1d" ),
			'record separator' => array( "\x1e" ),
			'next line'        => array( "\xc2\x85" ),
			'line separator'   => array( "\xe2\x80\xa8" ),
			'paragraph sep'    => array( "\xe2\x80\xa9" ),
		);
	}

	/**
	 * Trac skips more than a space before a block, and so must the guard.
	 *
	 * Its whitespace class is Python's, which covers the C0 separators and the
	 * Unicode spaces that PCRE's own `\s` leaves out.
	 *
	 * @dataProvider data_skipped_before_a_block
	 *
	 * @param string $skipped Text Trac skips before `{{{` or before `#!`.
	 * @return void
	 */
	public function test_a_block_behind_skipped_text_is_neutralised( string $skipped ) {
		$open = format_github_content_for_trac_comment( "Fixes #1.\n\n{$skipped}{{{\n#!html\nBODY\n}}}\n" );
		$name = format_github_content_for_trac_comment( "Fixes #1.\n\n{{{\n{$skipped}#!html\nBODY\n}}}\n" );

		// Asserted, or `live_processors()` would also pass on a comment that never synced.
		$this->assertIsString( $open );
		$this->assertIsString( $name );

		$this->assertSame( array(), $this->live_processors( $open ), 'Skipped text before the block opener.' );
		$this->assertSame( array(), $this->live_processors( $name ), 'Skipped text before the processor name.' );
	}

	/**
	 * Whitespace Trac reads as leading, and the `>` whose contents it re-formats.
	 *
	 * @return array
	 */
	public static function data_skipped_before_a_block() {
		return array(
			'space'             => array( ' ' ),
			'tab'               => array( "\t" ),
			'unit separator'    => array( "\x1f" ),
			'no-break space'    => array( "\xc2\xa0" ),
			'figure space'      => array( "\xe2\x80\x87" ),
			'ideographic space' => array( "\xe3\x80\x80" ),
			'citation'          => array( '> ' ),
			'citation, no gap'  => array( '>' ),
			'nested citation'   => array( '> > ' ),
		);
	}

	/**
	 * The composer indents its own blocks inside a quote, and those must still sync.
	 *
	 * @return void
	 */
	public function test_a_quoted_fence_still_syncs() {
		$desc = format_github_content_for_trac_comment( "Fixes #1.\n\n> ```php\n> echo 'hi';\n> ```\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '#!php', $desc );
	}

	/**
	 * Nothing the body says may go missing on the way to the ticket.
	 *
	 * The composer converts each fenced span on its own, so a pattern matching only
	 * part of a span would drop the rest of it without any other test noticing.
	 *
	 * @dataProvider data_bodies_that_must_survive
	 *
	 * @param string $body   A pull request body.
	 * @param array  $tokens Text that must still appear in the comment.
	 * @return void
	 */
	public function test_no_content_is_dropped( string $body, array $tokens ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		foreach ( $tokens as $token ) {
			$this->assertStringContainsString( $token, $desc );
		}
	}

	/**
	 * Bodies whose fences do not close cleanly, with the text each must keep.
	 *
	 * @return array
	 */
	public static function data_bodies_that_must_survive() {
		return array(
			'unbalanced fence'   => array( "```\nA\n``` x\n\n```\nB\n```\n", array( 'A', 'B' ) ),
			'trailing space'     => array( "```php\n\$a = 1;\n```   \n", array( '#!php', '$a = 1;' ) ),
			'text around fences' => array( "one\n```\nA\n```\ntwo\n```js\nB\n```\nthree\n", array( 'one', 'A', 'two', 'B', 'three' ) ),
			'lone fence marker'  => array( "before\n```\nafter\n", array( 'before', 'after' ) ),
		);
	}

	/**
	 * Braces the code closes on one line are the code's own, and must survive.
	 *
	 * Trac ends a block only on a line of exactly `}}}`, so only that shape needs
	 * breaking up. Rewriting every run instead rewrites ordinary source.
	 *
	 * @dataProvider data_code_with_brace_runs
	 *
	 * @param string $language Fence language token.
	 * @param string $code     A line of code closing three or more braces.
	 * @return void
	 */
	public function test_brace_runs_inside_code_are_left_alone( string $language, string $code ) {
		$desc = format_github_content_for_trac_comment( "```{$language}\n{$code}\n```\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( $code, $desc );
	}

	/**
	 * Source that closes three or more braces on a single line.
	 *
	 * @return array
	 */
	public static function data_code_with_brace_runs() {
		return array(
			'json' => array( 'json', '{"a":{"b":{"c":1}}}' ),
			'js'   => array( 'js', 'const x = {a:{b:{c:1}}};' ),
			'php'  => array( 'php', 'if ( $a ) { if ( $b ) { if ( $c ) { d(); }}}' ),
		);
	}

	/**
	 * A delimiter on its own line still cannot close or nest the block.
	 *
	 * Trac reads the line with Python's `strip()`, so the whitespace it will skip in
	 * front of the delimiter is wider than PCRE's own and has to be matched as such.
	 *
	 * @dataProvider data_skipped_before_a_delimiter
	 *
	 * @param string $skipped Whitespace Trac strips before the delimiter.
	 * @return void
	 */
	public function test_a_delimiter_on_its_own_line_is_still_broken_up( string $skipped ) {
		$desc = format_github_content_for_trac_comment(
			"```\ncode\n{$skipped}}}}\n{$skipped}{{{\n#!html\nx\n```\n"
		);

		$this->assertIsString( $desc );
		$this->assertSame( 1, substr_count( $desc, "\n}}}" ) );
		$this->assertSame( array(), $this->live_processors( $desc ) );
	}

	/**
	 * Whitespace `str.strip()` removes that is not a `str.splitlines()` boundary.
	 *
	 * @return array
	 */
	public static function data_skipped_before_a_delimiter() {
		return array(
			'none'              => array( '' ),
			'space'             => array( ' ' ),
			'unit separator'    => array( "\x1f" ),
			'no-break space'    => array( "\xc2\xa0" ),
			'em space'          => array( "\xe2\x80\x83" ),
			'ideographic space' => array( "\xe3\x80\x80" ),
			'citation'          => array( '> ' ),
		);
	}

	/**
	 * Markup around a one-line fence must still convert.
	 *
	 * A block is converted on its own because its contents are not wiki markup, but a
	 * one-line fence is part of the line it sits on, and a table or link it appears in
	 * has to be read whole.
	 *
	 * @dataProvider data_markup_around_inline_code
	 *
	 * @param string $body   A pull request body with inline code inside other markup.
	 * @param string $expect The converted markup the comment should carry.
	 * @return void
	 */
	public function test_markup_around_inline_code_still_converts( string $body, string $expect ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( $expect, $desc );
	}

	/**
	 * Constructs that span a one-line fence.
	 *
	 * @return array
	 */
	public static function data_markup_around_inline_code() {
		return array(
			'table row' => array(
				"| ```wp_head()``` | OK |\n| --- | --- |\n| a | b |\n",
				'||= {{{wp_head()}}} =||= OK =||',
			),
			'link label' => array(
				"See [the ```wp_head()``` hook](https://example.org/d) here.\n",
				'[https://example.org/d the {{{wp_head()}}} hook]',
			),
		);
	}

	/**
	 * A line opening with inline code does not open a block.
	 *
	 * The opening line of a block must not reach past its own closing backticks, or
	 * two one-line fences and everything between them are read as one block.
	 *
	 * @return void
	 */
	public function test_two_inline_fences_stay_separate() {
		$desc = format_github_content_for_trac_comment(
			"```wp_head()```\nsee [docs](https://e.org/d)\n```wp_footer()```\n"
		);

		$this->assertSame(
			"{{{wp_head()}}}\nsee [https://e.org/d docs]\n{{{wp_footer()}}}",
			$desc
		);
	}

	/**
	 * Inline code renders literally, so the row-separator escape stays out of it.
	 *
	 * @return void
	 */
	public function test_a_row_separator_inside_inline_code_is_untouched() {
		$desc = format_github_content_for_trac_comment( "Use ```|-id=x``` in a row.\n" );

		$this->assertSame( 'Use {{{|-id=x}}} in a row.', $desc );
	}

	/**
	 * A single backtick is Trac's inline code too, and its contents are literal.
	 *
	 * @dataProvider data_backtick_spans
	 *
	 * @param string $body A pull request body with markup inside a backtick span.
	 * @return void
	 */
	public function test_a_backtick_span_is_left_alone( string $body ) {
		$this->assertSame( trim( $body ), format_github_content_for_trac_comment( $body ) );
	}

	/**
	 * Backtick spans whose contents would otherwise be escaped.
	 *
	 * @return array
	 */
	public static function data_backtick_spans() {
		return array(
			'macro'         => array( "Use `[[Image(x)]]` here.\n" ),
			'anchor'        => array( "Use `[=#x]` here.\n" ),
			'block opener'  => array( "Use `{{{` here.\n" ),
			'row separator' => array( "Use `|-id=x` here.\n" ),
		);
	}

	/**
	 * A quoted fence must sit inside the citation, delimiters and all.
	 *
	 * Trac re-formats a citation's lines as their own document, so a block whose
	 * opener is quoted and whose processor line is not opens in one and closes in
	 * the other, and renders as loose text instead of code.
	 *
	 * @return void
	 */
	public function test_a_quoted_fence_keeps_every_line_in_the_citation() {
		$desc = format_github_content_for_trac_comment( "> ```\n> #!div style=\"x\"\n> code\n> ```\n" );

		$this->assertIsString( $desc );
		foreach ( array( '> {{{', '> #!default', '> }}}' ) as $line ) {
			$this->assertStringContainsString( $line, $desc );
		}
		$this->assertSame( array(), $this->live_processors( $desc ) );
	}

	/**
	 * The row-separator escape must not reach inside the markup it runs after.
	 *
	 * Trac reads a `[[macro]]` or `[link]` whole, so a `|-` inside one is already
	 * inert; escaping it there only corrupts the target.
	 *
	 * @dataProvider data_targets_with_a_pipe
	 *
	 * @param string $body   A pull request body whose target contains `|-`.
	 * @param string $expect The encoded target the comment should carry.
	 * @return void
	 */
	public function test_a_pipe_in_a_target_is_encoded_not_escaped( string $body, string $expect ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( $expect, $desc );
		$this->assertStringNotContainsString( '!|', $desc );
	}

	/**
	 * Markdown whose link and image targets carry a pipe.
	 *
	 * @return array
	 */
	public static function data_targets_with_a_pipe() {
		return array(
			'image' => array( "![a](http://x/y?a,b|-c)\n", '[[Image(http://x/y?a%2Cb%7C-c)]]' ),
			'link'  => array( "[a](http://x/y?a|-b)\n", '[http://x/y?a%7C-b a]' ),
		);
	}

	/**
	 * An image target is a macro argument, so a comma in it must not add one.
	 *
	 * Trac writes an argument it does not recognise onto the `img` as an attribute.
	 *
	 * @dataProvider data_image_bodies
	 *
	 * @param string $body A pull request body embedding an image.
	 * @return void
	 */
	public function test_an_image_target_cannot_add_macro_arguments( string $body ) {
		$desc = format_github_content_for_trac_comment( $body );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '[[Image(https://e.org/i.png%2C', $desc );
		$this->assertStringNotContainsString( 'i.png, id=', $desc );
	}

	/**
	 * Both shapes the composer turns into an `[[Image()]]` macro.
	 *
	 * @return array
	 */
	public static function data_image_bodies() {
		return array(
			'markdown' => array( "Fixes #1.\n\n![alt](https://e.org/i.png, id=wpTrac)\n" ),
			'html tag' => array( "Fixes #1.\n\n<img src=\"https://e.org/i.png, id=wpTrac\">\n" ),
		);
	}

	/**
	 * A row separator's parameters are written onto the `tr`, so `|-` is escaped.
	 *
	 * @return void
	 */
	public function test_a_row_separator_cannot_carry_parameters() {
		$desc = format_github_content_for_trac_comment(
			"Fixes #1.\n\n|-style=\"color: red\\22\\3e\\3cimg src=zzz onerror=alert(1)\\3e\"\n"
		);

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '!|-style=', $desc );
		$this->assertStringNotContainsString( "\n|-style=", $desc );
	}

	/**
	 * The tables the composer builds must survive that escape.
	 *
	 * @return void
	 */
	public function test_a_markdown_table_still_converts() {
		$desc = format_github_content_for_trac_comment( "Fixes #1.\n\n| a | b |\n| --- | --- |\n| 1 | 2 |\n" );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( '||= a =||= b =||', $desc );
		$this->assertStringContainsString( '|| 1 || 2 ||', $desc );
	}

	/**
	 * Text that only mentions a processor must still reach the ticket.
	 *
	 * Trac honours a processor named on a line-opening `{{{`, so nothing else needs
	 * refusing, and refusing it drops the comment with no word to the author.
	 *
	 * @dataProvider data_processor_mentions
	 *
	 * @param string $body A pull request body naming a processor inertly.
	 * @return void
	 */
	public function test_an_inert_processor_mention_still_syncs( string $body ) {
		$this->assertIsString( format_github_content_for_trac_comment( $body ) );
	}

	/**
	 * Bodies naming a processor somewhere Trac would not read one.
	 *
	 * @return array
	 */
	public static function data_processor_mentions() {
		return array(
			'inline code'       => array( "Fixes #1.\n\nRun `#!bash` first.\n" ),
			'quoted in a fence' => array( "Fixes #1.\n\n```php\n\$x = '{{{#!comment}}}';\n```\n" ),
			'inline block'      => array( "Fixes #1.\n\nTrac writes {{{#!div class=x}}} inline.\n" ),
		);
	}

	/**
	 * Closing the composer's own block early must not free the rest of the body.
	 *
	 * @return void
	 */
	public function test_breaking_out_of_a_block_is_neutralised() {
		$desc = format_github_content_for_trac_comment(
			$this->fence( '', "BODY\n}}}\n{{{\n" . self::PROCESSOR )
		);

		$this->assertIsString( $desc );
		$this->assertSame( array(), $this->live_processors( $desc ) );
	}

	/**
	 * The `#!html` refusal the guard already made must be kept.
	 *
	 * @return void
	 */
	public function test_a_raw_html_block_is_neutralised() {
		$desc = format_github_content_for_trac_comment( "Fixes #1.\n\n{{{\n#!html\n<img src=zzz onerror=alert(1)>\n}}}\n" );

		$this->assertIsString( $desc );
		$this->assertSame( array(), $this->live_processors( $desc ) );
	}

	/**
	 * `#!html` written inside a fence is content rather than a processor.
	 *
	 * The guard used to drop the whole comment for this, because the fence handed the
	 * line to Trac as the processor name. Naming `default` makes the line inert, so
	 * the comment carries the code the author wrote instead of going missing.
	 *
	 * @return void
	 */
	public function test_html_inside_a_fence_is_inert_content() {
		$desc = format_github_content_for_trac_comment( $this->fence( '', "#!html\n<img src=zzz onerror=alert(1)>" ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!default\n#!html\n", $desc );
	}

	/**
	 * A fence tagged `html` is still rewritten to the inert `xml` processor.
	 *
	 * @return void
	 */
	public function test_html_fence_is_still_rewritten_to_xml() {
		$desc = format_github_content_for_trac_comment( $this->fence( 'html', '<img src=x onerror=alert(1)>' ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!xml\n", $desc );
	}

	/**
	 * A supported language must still reach Trac as that processor.
	 *
	 * @return void
	 */
	public function test_supported_language_is_kept() {
		$desc = format_github_content_for_trac_comment( $this->fence( 'php', "echo 'hello';" ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!php\necho 'hello';\n}}}", $desc );
	}

	/**
	 * An unsupported language is still mapped to `default` rather than refused.
	 *
	 * @return void
	 */
	public function test_unsupported_language_is_mapped_to_default() {
		$desc = format_github_content_for_trac_comment( $this->fence( 'bash', 'echo hello' ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!default\necho hello\n}}}", $desc );
	}

	/**
	 * A shebang is ordinary content, and a script quoting one must still sync.
	 *
	 * @return void
	 */
	public function test_a_shebang_in_a_block_is_not_a_processor() {
		$desc = format_github_content_for_trac_comment( $this->fence( '', "#!/bin/bash\necho hello" ) );

		$this->assertIsString( $desc );
		$this->assertStringContainsString( "{{{\n#!default\n#!/bin/bash\necho hello\n}}}", $desc );
	}

	/**
	 * A fence that opens and closes on one line is inline code, and stays inline.
	 *
	 * @return void
	 */
	public function test_a_single_line_fence_stays_inline() {
		$desc = format_github_content_for_trac_comment( "Fixes #1.\n\nUse ```wp_head()``` here.\n" );

		$this->assertSame( "Fixes #1.\n\nUse {{{wp_head()}}} here.", $desc );
	}

	/**
	 * The conversions the composer exists for must be left alone.
	 *
	 * @return void
	 */
	public function test_ordinary_markdown_still_converts() {
		$desc = format_github_content_for_trac_comment(
			"See [the docs](https://example.org/doc) and ![shot](https://example.org/s.png).\n"
		);

		$this->assertSame(
			'See [https://example.org/doc the docs] and [[Image(https://example.org/s.png)]].',
			$desc
		);
	}
}
