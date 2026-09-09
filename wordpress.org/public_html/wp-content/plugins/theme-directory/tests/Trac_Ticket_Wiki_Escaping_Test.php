<?php
/**
 * Tests that an upload cannot write Trac wiki markup into the ticket it files.
 *
 * The Trac ticket description is composed from the uploaded `style.css` headers, the
 * screenshot file name and the Theme Check output, and Trac renders it as wiki text.
 * A macro, a link or a processor block reaching that renderer from the upload is an
 * element in the `themes.trac.wordpress.org` page, with attributes the uploader wrote.
 *
 * @package theme-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;

/**
 * Tests for the wiki escaping of both halves of the ticket description.
 *
 * @group upload
 */
class Trac_Ticket_Wiki_Escaping_Test extends TestCase {

	/**
	 * A one-line macro call, which is what a header value has room for.
	 *
	 * The `style` argument is what makes this more than defacement: Trac writes it
	 * into the element it builds, where a CSS escape reopens markup.
	 *
	 * @var string
	 */
	const MACRO = '[[span(X, style=color: red\22\3e\3cimg src=zzz onerror=alert(1)\3e)]]';

	/**
	 * Absolute path of the temporary theme root holding the fixture.
	 *
	 * @var string
	 */
	protected $theme_root = '';

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var array
	 */
	protected $post_ids = array();

	/**
	 * Creates the theme root the fixture styles are written into.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// A unique root per test, so WP_Theme's header cache cannot be reused.
		$this->theme_root = untrailingslashit( get_temp_dir() ) . '/wporg-theme-wiki-' . uniqid();
		wp_mkdir_p( $this->theme_root . '/fixture-theme' );
	}

	/**
	 * Removes the fixtures and posts the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		$GLOBALS['themechecks'] = array();

		/*
		 * The plugin prevents repopackages from being deleted; detach that specific
		 * guard while cleaning up the fixture posts.
		 */
		remove_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );
		$this->post_ids = array();

		$this->remove_fixture( $this->theme_root );

		parent::tearDown();
	}

	/**
	 * Recursively removes a fixture directory.
	 *
	 * @param string $path Absolute path to remove.
	 * @return void
	 */
	protected function remove_fixture( string $path ): void {
		if ( ! is_dir( $path ) ) {
			return;
		}

		foreach ( (array) glob( $path . '/*' ) as $item ) {
			if ( is_dir( $item ) ) {
				$this->remove_fixture( $item );
			} else {
				wp_delete_file( $item );
			}
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Test fixture in a temporary directory.
		rmdir( $path );
	}

	/**
	 * Composes a ticket description for a theme carrying the given headers.
	 *
	 * @param array  $headers    Header lines to add to `style.css`, keyed by header name.
	 * @param string $screenshot File name to report as the theme's screenshot.
	 * @return string The composed description.
	 */
	protected function compose_description( array $headers, string $screenshot = 'screenshot.png' ): string {
		$headers = array_merge(
			array(
				'Theme Name' => 'Fixture Theme',
				'Version'    => '1.0',
			),
			$headers
		);

		$style = "/*\n";
		foreach ( $headers as $name => $value ) {
			$style .= "{$name}: {$value}\n";
		}
		$style .= "*/\n";

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture in a temporary directory.
		file_put_contents( $this->theme_root . '/fixture-theme/style.css', $style );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'repopackage',
				'post_status' => 'publish',
				'post_title'  => 'Fixture Theme',
				'post_name'   => 'fixture-theme',
				'post_author' => 1,
			)
		);

		$this->post_ids[] = $post_id;

		$theme             = new WP_Theme( 'fixture-theme', $this->theme_root );
		$theme->screenshot = $screenshot;

		$upload              = new WPORG_Themes_Upload();
		$upload->theme       = $theme;
		$upload->theme_slug  = 'fixture-theme';
		$upload->theme_post  = get_post( $post_id );
		$upload->trac_ticket = (object) array(
			'summary'     => '',
			'description' => '',
			'keywords'    => array(),
			'priority'    => '',
			'parent_link' => '',
			'diff_line'   => '',
		);

		$upload->prepare_trac_ticket();

		return $upload->trac_ticket->description;
	}

	/**
	 * Registers a Theme Check double reporting the given messages.
	 *
	 * @param array $errors Messages the check should report, as HTML.
	 * @return void
	 */
	protected function register_themecheck( array $errors ): void {
		$GLOBALS['themechecks'] = array(
			new class( $errors ) implements themecheck {

				/**
				 * The messages to report.
				 *
				 * @var array
				 */
				public $errors;

				/**
				 * Constructor.
				 *
				 * @param array $errors The messages to report.
				 */
				public function __construct( array $errors ) {
					$this->errors = $errors;
				}

				/**
				 * Reports a failure, so the messages are read.
				 *
				 * @param array $php_files   Unused.
				 * @param array $css_files   Unused.
				 * @param array $other_files Unused.
				 * @return bool
				 */
				public function check( $php_files, $css_files, $other_files ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- The double mirrors the interface.
					return false;
				}

				/**
				 * Returns the canned messages.
				 *
				 * @return array
				 */
				public function getError() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Set by the interface.
					return $this->errors;
				}
			},
		);
	}

	/**
	 * A macro in the theme name must reach the ticket without its opening brackets.
	 *
	 * @return void
	 */
	public function test_macro_in_theme_name_is_escaped() {
		$description = $this->compose_description( array( 'Theme Name' => self::MACRO ) );

		$this->assertStringContainsString( '![![span(X,', $description );
		$this->assertStringNotContainsString( '[[span', $description );
	}

	/**
	 * A macro in the description must reach the ticket without its opening brackets.
	 *
	 * @return void
	 */
	public function test_macro_in_description_is_escaped() {
		$description = $this->compose_description( array( 'Description' => self::MACRO ) );

		$this->assertStringContainsString( '![![span(X,', $description );
		$this->assertStringNotContainsString( '[[span', $description );
	}

	/**
	 * A URL header is written as text, so the table markup a URL can carry is escaped.
	 *
	 * `sanitize_url()` encodes the brackets and drops the braces, but it leaves `|`
	 * alone, and a row separator's parameters are written into the `tr` element.
	 *
	 * @return void
	 */
	public function test_table_markup_in_url_headers_is_escaped() {
		$description = $this->compose_description(
			array(
				'Theme URI'  => 'http://example.org/||-style=red',
				'Author URI' => 'http://example.net/||-style=blue',
			)
		);

		$this->assertStringContainsString( 'Theme URL - http://example.org/!|!|-style=red', $description );
		$this->assertStringContainsString( 'Author URL - http://example.net/!|!|-style=blue', $description );
		$this->assertStringNotContainsString( '/||-', $description );
	}

	/**
	 * A payload that arrives pre-escaped must not come out live.
	 *
	 * Trac reads `!!` as an escaped `!`, so an uploader writing the escape themselves
	 * would consume the one the composer adds if the two were not kept apart.
	 *
	 * @return void
	 */
	public function test_a_pre_escaped_macro_stays_escaped() {
		$description = $this->compose_description( array( 'Theme Name' => '!' . self::MACRO ) );

		$this->assertStringContainsString( '!![![span(X,', $description );
		$this->assertStringNotContainsString( '[[span', $description );
	}

	/**
	 * The composer's own macros must survive, or the ticket loses what it is for.
	 *
	 * @return void
	 */
	public function test_the_composers_own_markup_still_renders() {
		$description = $this->compose_description( array() );

		$this->assertStringContainsString( 'Live preview – [[https://playground.wordpress.net/?', $description );
		$this->assertStringContainsString( '[[TicketQuery(format=table, keywords=~theme-fixture-theme,', $description );
		$this->assertStringContainsString( '/fixture-theme/1.0/screenshot.png, width=640)]]', $description );
	}

	/**
	 * A heading names the element it builds, so a value may not open one.
	 *
	 * `!` is not a heading escape, but a line no longer starting with `=` is not a
	 * heading, and the anchor that would have become the element's `id` goes with it.
	 *
	 * @return void
	 */
	public function test_a_heading_in_a_description_is_neutralised() {
		$description = $this->compose_description( array( 'Description' => '= Fixture =#wpTrac' ) );

		$this->assertStringContainsString( '!= Fixture =#wpTrac', $description );
	}

	/**
	 * Trac accepts more than a space in front of a heading, and so must the check.
	 *
	 * @dataProvider data_unicode_spaces
	 *
	 * @param string $space A space character Trac reads as leading whitespace.
	 * @return void
	 */
	public function test_a_heading_behind_unicode_space_is_neutralised( string $space ) {
		$description = $this->compose_description(
			array( 'Description' => $space . '= Fixture =#wpTrac' )
		);

		$this->assertStringContainsString( '!' . $space . '= Fixture =#wpTrac', $description );
	}

	/**
	 * Space characters outside `[ \t]` that Trac still reads as leading whitespace.
	 *
	 * One case per data row, so each gets the fresh theme root `setUp()` builds:
	 * `WP_Theme` caches its headers against the root it read them from.
	 *
	 * @return array
	 */
	public static function data_unicode_spaces() {
		return array(
			'no-break space'    => array( "\xc2\xa0" ),
			'en quad'           => array( "\xe2\x80\x80" ),
			'narrow no-break'   => array( "\xe2\x80\xaf" ),
			'ideographic space' => array( "\xe3\x80\x80" ),
		);
	}

	/**
	 * Every value is stripped to valid UTF-8, not just the one header that used to be.
	 *
	 * The ticket travels by XML-RPC, which cannot carry an invalid byte, and the
	 * heading check reads the value with a `/u` pattern that such a byte would fail.
	 *
	 * @return void
	 */
	public function test_invalid_utf8_is_stripped_from_every_value() {
		$description = $this->compose_description(
			array(
				'Theme Name'  => "Bad\xffName",
				'Description' => "Bad\xffDesc",
				'Theme URI'   => "http://example.org/a\xffb",
			)
		);

		$this->assertTrue( mb_check_encoding( $description, 'UTF-8' ) );
		$this->assertStringStartsWith( 'BadName - 1.0', $description );
	}

	/**
	 * The screenshot name is a macro argument, so a comma in it must not add one.
	 *
	 * @return void
	 */
	public function test_screenshot_name_cannot_add_macro_arguments() {
		$description = $this->compose_description(
			array(),
			'x, style=color: red\22\3e\3cimg src=zzz\3e.png'
		);

		$this->assertStringContainsString( '/x%2C%20style%3D', $description );
		$this->assertStringNotContainsString( 'x, style=', $description );
	}

	/**
	 * Ordinary headers must survive untouched, so reviewers read what was submitted.
	 *
	 * @return void
	 */
	public function test_plain_headers_are_left_alone() {
		$description = $this->compose_description(
			array(
				'Description' => 'A theme for readers and writers.',
				'Theme URI'   => 'https://example.org/theme',
			)
		);

		$this->assertStringContainsString( 'A theme for readers and writers.', $description );
		$this->assertStringContainsString( 'Theme URL - https://example.org/theme', $description );
	}

	/**
	 * The quoted code must not be able to choose the processor of its own block.
	 *
	 * Theme Check prefixes the quoted line, but a carriage return inside the line
	 * starts a new line as far as Trac is concerned, which is enough to reach the
	 * processor line the block opens with.
	 *
	 * @return void
	 */
	public function test_quoted_code_cannot_choose_the_block_processor() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: bad<pre class='tc-grep'>Line 1: \r#!html\r&lt;img src=zzz onerror=alert(1)&gt;</pre>" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertStringContainsString( "{{{\r\n#!default\r\n", $results );
		$this->assertStringNotContainsString( "{{{\r\n#!html", $results );
	}

	/**
	 * The quoted code must not be able to close its own block.
	 *
	 * Closing it early would put the rest of the report, which quotes the theme too,
	 * back into wiki context.
	 *
	 * @return void
	 */
	public function test_quoted_code_cannot_close_its_block() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: bad<pre class='tc-grep'>Line 1: \r}}}\r{{{\r#!html\r&lt;img src=zzz&gt;</pre> in style.css" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		// One block, opened and closed by the composer alone.
		$this->assertSame( 1, substr_count( $results, '{{{' ) );
		$this->assertSame( 1, substr_count( $results, '}}}' ) );
	}

	/**
	 * A message quoting several lines opens and closes a block for each of them.
	 *
	 * `tc_grep()` concatenates one `<pre>` per matching line into a single message.
	 *
	 * @return void
	 */
	public function test_every_block_in_a_message_is_delimited_and_inert() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: bad<pre class='tc-grep'>Line 1: a</pre><pre class='tc-grep'>Line 9: b</pre>" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertSame( 2, substr_count( $results, '#!default' ) );
		$this->assertSame( 2, substr_count( $results, '{{{' ) );
		$this->assertSame( 2, substr_count( $results, '}}}' ) );
	}

	/**
	 * An unclosed `<pre>` must not open a block that swallows the rest of the ticket.
	 *
	 * @return void
	 */
	public function test_an_unclosed_pre_opens_no_block() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: bad<pre class='tc-grep'>Line 1: a in style.css" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertStringNotContainsString( '{{{', $results );
		$this->assertStringContainsString( 'in style.css', $results );
	}

	/**
	 * Stripping a tag must not splice a block delimiter back together.
	 *
	 * The `<span>` and `<br>` removals close the gap they leave, so a delimiter split
	 * across a tag becomes whole again. Both halves are therefore stripped before the
	 * delimiters are counted rather than after.
	 *
	 * @return void
	 */
	public function test_stripping_a_tag_cannot_splice_a_block_delimiter() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: bad<pre class='tc-grep'>Line 1: a\r}}<span class='x'>}</span>\r#!html\r&lt;img src=x&gt;</pre> in style.css" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		// A spliced `}}}` alone on its line would close the block and free the rest.
		$this->assertSame( 1, substr_count( $results, '}}}' ) );
		$this->assertStringContainsString( "\r} }}\r", $results );
	}

	/**
	 * Stripping a tag must not splice wiki markup back together either.
	 *
	 * @return void
	 */
	public function test_stripping_a_tag_cannot_splice_table_markup() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: Found x|<span class='x'>|</span>-style=red in foo.php" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertStringContainsString( 'x!|!|-style=red', $results );
		$this->assertStringNotContainsString( 'x||-', $results );
	}

	/**
	 * A file name quoted outside a block is wiki text, and is escaped as such.
	 *
	 * @return void
	 */
	public function test_file_name_in_a_message_is_escaped() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: Found eval in <strong>[[Image(x, id=wpTrac)]].php</strong>." )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertStringContainsString( '![![Image(x, id=wpTrac)]].php', $results );
		$this->assertStringNotContainsString( '[[Image', $results );
	}

	/**
	 * A quoted string is one line to PHP, but Trac breaks lines on more than a newline.
	 *
	 * Line-start markup is not reached with `!`, so the breaks are removed instead: a
	 * heading writes its anchor into the element's `id`, which the Trac scripts read.
	 *
	 * @return void
	 */
	public function test_line_breaks_in_a_message_are_removed() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: Found <strong>a\r= x =#wpTrac</strong>." )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertStringNotContainsString( "\r", $results );
		$this->assertStringContainsString( "'''a = x =#wpTrac'''", $results );
	}

	/**
	 * A message the block splitter cannot handle must not take the upload down.
	 *
	 * @return void
	 */
	public function test_an_unsplittable_message_is_survivable() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: bad<pre class='tc-grep'>Line 1: a</pre>" )
		);

		$limit = ini_get( 'pcre.backtrack_limit' );

		// Restored in `finally`: leaving the limit at 1 takes PHPUnit's own output with it.
		try {
			// phpcs:ignore WordPress.PHP.IniSet.Risky -- The only way to make PCRE fail on demand.
			ini_set( 'pcre.backtrack_limit', '1' );

			$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();
		} finally {
			// phpcs:ignore WordPress.PHP.IniSet.Risky -- Restores the value read above.
			ini_set( 'pcre.backtrack_limit', $limit );
		}

		$this->assertIsString( $results );
	}

	/**
	 * Ordinary Theme Check output must still convert to the markup reviewers read.
	 *
	 * @return void
	 */
	public function test_plain_themecheck_output_still_converts() {
		$this->register_themecheck(
			array( "<span class='tc-lead tc-required'>REQUIRED</span>: See <a href='https://example.org/doc'>the docs</a><pre class='tc-grep'>Line 1: &lt;?php eval( \$x ); ?&gt;</pre>" )
		);

		$results = ( new WPORG_Themes_Upload() )->generate_themecheck_results_for_trac();

		$this->assertStringContainsString( '* REQUIRED: See [https://example.org/doc the docs]', $results );
		$this->assertStringContainsString( 'Line 1: <?php eval( $x ); ?>', $results );
	}
}
