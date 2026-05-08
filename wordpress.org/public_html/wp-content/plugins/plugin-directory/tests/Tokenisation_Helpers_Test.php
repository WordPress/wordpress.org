<?php
/**
 * Tests for Tools\Tokenisation_Helpers::find_function_call_arg_strings().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools\Tokenisation_Helpers;

/**
 * @group tokenisation
 */
class Tokenisation_Helpers_Test extends TestCase {

	private function find( string $php, string $function_name, int $arg_index = 0 ): array {
		return Tokenisation_Helpers::find_function_call_arg_strings(
			"<?php\n" . $php,
			$function_name,
			$arg_index
		);
	}

	// ---------------------------------------------------------------------
	// Bare literals and translation wrappers.
	// ---------------------------------------------------------------------

	public function test_bare_string_literal() {
		$this->assertSame(
			array( 'Plain' ),
			$this->find( "wp_add_dashboard_widget( 'id', 'Plain', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_translation_wrapper_double_underscore() {
		$this->assertSame(
			array( 'Translated' ),
			$this->find( "wp_add_dashboard_widget( 'id', __( 'Translated', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_translation_wrapper_x() {
		$this->assertSame(
			array( 'Contextual' ),
			$this->find( "wp_add_dashboard_widget( 'id', _x( 'Contextual', 'ctx', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_translation_wrapper_esc_html() {
		$this->assertSame(
			array( 'Escaped' ),
			$this->find( "wp_add_dashboard_widget( 'id', esc_html__( 'Escaped', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_double_quoted_string() {
		$this->assertSame(
			array( 'Double' ),
			$this->find( 'wp_add_dashboard_widget( "id", "Double", "cb" );', 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_escaped_single_quote_in_label() {
		$this->assertSame(
			array( "Bob's Widget" ),
			$this->find( "wp_add_dashboard_widget( 'id', __( 'Bob\\'s Widget', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_class_constant_id_with_inline_doc_comment() {
		// The Jetpack pattern: class constant for the ID, doc comment between args, literal label.
		$src = <<<'PHP'
wp_add_dashboard_widget(
	Dashboard_Stats_Widget::DASHBOARD_WIDGET_ID,
	/** "Stats" is a product name. */
	'Jetpack Stats',
	array( $w, 'render' )
);
PHP;
		$this->assertSame(
			array( 'Jetpack Stats' ),
			$this->find( $src, 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_multiline_call() {
		$src = "wp_add_dashboard_widget(\n\t'id',\n\t__( 'Multi', 'td' ),\n\t'cb'\n);";
		$this->assertSame( array( 'Multi' ), $this->find( $src, 'wp_add_dashboard_widget', 1 ) );
	}

	// ---------------------------------------------------------------------
	// False-positive prevention.
	// ---------------------------------------------------------------------

	public function test_call_inside_line_comment_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( "// wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_call_inside_block_comment_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( "/* wp_add_dashboard_widget( 'id', 'X', 'cb' ); */", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_call_inside_string_literal_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( "\$x = \"wp_add_dashboard_widget( 'id', 'X', 'cb' );\";", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_method_call_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( "\$obj->wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_static_method_call_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( "Foo::wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_function_declaration_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( 'function wp_add_dashboard_widget( $id, $name, $cb ) {}', 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_function_exists_argument_is_ignored() {
		$this->assertSame(
			array(),
			$this->find( "if ( function_exists( 'wp_add_dashboard_widget' ) ) {}", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_arg_with_only_variable_is_skipped() {
		$this->assertSame(
			array(),
			$this->find( "wp_add_dashboard_widget( 'id', \$label, 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_arg_with_only_class_constant_is_skipped() {
		$this->assertSame(
			array(),
			$this->find( "wp_add_dashboard_widget( 'id', Foo::LABEL, 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	// ---------------------------------------------------------------------
	// register_block_type / new WP_Block_Type / namespaced calls.
	// ---------------------------------------------------------------------

	public function test_register_block_type_literal_name() {
		$this->assertSame(
			array( 'my-plugin/foo' ),
			$this->find( "register_block_type( 'my-plugin/foo' );", 'register_block_type', 0 )
		);
	}

	public function test_register_block_type_with_options_array() {
		$this->assertSame(
			array( 'my-plugin/bar' ),
			$this->find( "register_block_type( 'my-plugin/bar', array( 'title' => 'Bar' ) );", 'register_block_type', 0 )
		);
	}

	public function test_new_wp_block_type_constructor() {
		$this->assertSame(
			array( 'my-plugin/baz' ),
			$this->find( "new WP_Block_Type( 'my-plugin/baz' );", 'new WP_Block_Type', 0 )
		);
	}

	public function test_register_block_type_with_leading_backslash() {
		// Plugins occasionally call the global function via `\register_block_type(...)`.
		$this->assertSame(
			array( 'my-plugin/leading-slash' ),
			$this->find( "\\register_block_type( 'my-plugin/leading-slash' );", 'register_block_type', 0 )
		);
	}

	// ---------------------------------------------------------------------
	// Documented shortcuts: the helper trades these edge cases for simplicity.
	// If you change behaviour here, update the corresponding consumer logic
	// (e.g. block-name shape filter in find_blocks_in_file()).
	// ---------------------------------------------------------------------

	/**
	 * Shortcut (to reduce complexity): a concatenated literal-plus-expression is
	 * captured as the leading literal alone. We do not detect that the value is
	 * actually composed of multiple parts. Consumers that require a clean literal
	 * must validate the captured value (e.g. block detection requires `\w+/\w+`).
	 */
	public function test_shortcut_concatenated_literal_returns_leading_part() {
		$this->assertSame(
			array( 'prefix-' ),
			$this->find( "register_block_type( 'prefix-' . \$name );", 'register_block_type', 0 )
		);
	}

	/**
	 * Shortcut (to reduce complexity): only `\register_block_type` (T_NAME_FULLY_QUALIFIED
	 * with a leading backslash) is treated as the global function. Calls inside an
	 * arbitrary namespace, like `Foo\Bar\register_block_type(...)`, are NOT matched
	 * — they are assumed to be unrelated functions that just happen to share the name.
	 */
	public function test_shortcut_namespaced_call_is_not_matched() {
		$this->assertSame(
			array(),
			$this->find( "Foo\\Bar\\register_block_type( 'ns/inside' );", 'register_block_type', 0 )
		);
	}

	/**
	 * Shortcut (to reduce complexity): the helper takes the FIRST literal-string
	 * reachable inside the target arg position, regardless of how it nests. A call
	 * that wraps something other than a translation function (e.g. a method call
	 * that happens to take a string literal) will still produce a value — we do
	 * not validate that the surrounding wrapper is a known i18n helper.
	 */
	public function test_shortcut_arbitrary_wrapping_call_still_captures_inner_literal() {
		$this->assertSame(
			array( 'Inner' ),
			$this->find( "wp_add_dashboard_widget( 'id', \$obj->method( 'Inner' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	/**
	 * Shortcut (to reduce complexity): when the target arg is an array literal,
	 * the helper returns the first inner string it finds — typically a key, not
	 * the value. Real callers don't use array arguments for the labels we extract
	 * (dashboard widget label, block name), so this is acceptable.
	 */
	public function test_shortcut_array_arg_captures_first_inner_string() {
		$this->assertSame(
			array( 'title' ),
			$this->find( "register_block_type( array( 'title' => 'Block Title' ) );", 'register_block_type', 0 )
		);
	}

	// ---------------------------------------------------------------------
	// Multiple matches in one source.
	// ---------------------------------------------------------------------

	public function test_multiple_calls_in_one_file() {
		$src = "wp_add_dashboard_widget( 'a', 'A', 'cb' );\n"
			. "wp_add_dashboard_widget( 'b', __( 'B', 'td' ), 'cb' );\n"
			. "wp_add_dashboard_widget( 'c', \$variable, 'cb' );\n"
			. "wp_add_dashboard_widget( 'd', 'D', 'cb' );";
		$this->assertSame(
			array( 'A', 'B', 'D' ),
			$this->find( $src, 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_returns_empty_for_no_matches() {
		$this->assertSame(
			array(),
			$this->find( "do_something_else( 'foo' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_invalid_php_returns_empty_array() {
		$this->assertSame(
			array(),
			Tokenisation_Helpers::find_function_call_arg_strings( 'this is not php', 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_function_name_match_is_case_insensitive() {
		// PHP function names are case-insensitive; mirror that.
		$this->assertSame(
			array( 'X' ),
			$this->find( "WP_Add_Dashboard_Widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}
}
