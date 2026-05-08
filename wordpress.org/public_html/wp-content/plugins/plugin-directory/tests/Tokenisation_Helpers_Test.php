<?php
/**
 * Tests for Tools\Tokenisation_Helpers.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools\Tokenisation_Helpers;

/**
 * @group tokenisation
 */
class Tokenisation_Helpers_Test extends TestCase {

	private function find_args( string $php, string $function_name, int $arg_index = 0 ): array {
		return Tokenisation_Helpers::find_function_call_arg_strings(
			"<?php\n" . $php,
			$function_name,
			$arg_index
		);
	}

	private function find_arg_and_array( string $php, string $function_name, int $array_arg_index, string $array_key ): array {
		return Tokenisation_Helpers::find_function_call_first_arg_and_array_value(
			"<?php\n" . $php,
			$function_name,
			$array_arg_index,
			$array_key
		);
	}

	/**
	 * Bare literals and translation wrappers via find_function_call_arg_strings().
	 */
	public function test_bare_string_literal() {
		$this->assertSame(
			array( 'Plain' ),
			$this->find_args( "wp_add_dashboard_widget( 'id', 'Plain', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_translation_wrapper_double_underscore() {
		$this->assertSame(
			array( 'Translated' ),
			$this->find_args( "wp_add_dashboard_widget( 'id', __( 'Translated', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_translation_wrapper_x() {
		$this->assertSame(
			array( 'Contextual' ),
			$this->find_args( "wp_add_dashboard_widget( 'id', _x( 'Contextual', 'ctx', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_translation_wrapper_esc_html() {
		$this->assertSame(
			array( 'Escaped' ),
			$this->find_args( "wp_add_dashboard_widget( 'id', esc_html__( 'Escaped', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_double_quoted_string() {
		$this->assertSame(
			array( 'Double' ),
			$this->find_args( 'wp_add_dashboard_widget( "id", "Double", "cb" );', 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_escaped_single_quote_in_label() {
		$this->assertSame(
			array( "Bob's Widget" ),
			$this->find_args( "wp_add_dashboard_widget( 'id', __( 'Bob\\'s Widget', 'td' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_class_constant_id_with_inline_doc_comment() {
		// Common pattern: class constant for the ID, doc comment between args, literal label.
		$src = <<<'PHP'
wp_add_dashboard_widget(
	My_Widget_Class::DASHBOARD_WIDGET_ID,
	/** This is a comment */
	'My Widget',
	array( $instance, 'render' )
);
PHP;
		$this->assertSame(
			array( 'My Widget' ),
			$this->find_args( $src, 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_multiline_call() {
		$src = "wp_add_dashboard_widget(\n\t'id',\n\t__( 'Multi', 'td' ),\n\t'cb'\n);";
		$this->assertSame( array( 'Multi' ), $this->find_args( $src, 'wp_add_dashboard_widget', 1 ) );
	}

	/**
	 * False-positive prevention: comments, strings, method calls, declarations.
	 */
	public function test_call_inside_line_comment_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( "// wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_call_inside_block_comment_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( "/* wp_add_dashboard_widget( 'id', 'X', 'cb' ); */", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_call_inside_string_literal_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( "\$x = \"wp_add_dashboard_widget( 'id', 'X', 'cb' );\";", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_method_call_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( "\$obj->wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_static_method_call_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( "Foo::wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_function_declaration_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( 'function wp_add_dashboard_widget( $id, $name, $cb ) {}', 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_function_exists_argument_is_ignored() {
		$this->assertSame(
			array(),
			$this->find_args( "if ( function_exists( 'wp_add_dashboard_widget' ) ) {}", 'wp_add_dashboard_widget', 1 )
		);
	}

	/**
	 * Calls without a literal at the target position still count: they yield
	 * an empty string so callers can detect that the function was used (and
	 * e.g. apply a section term) even when the label is not parseable.
	 */
	public function test_arg_with_only_variable_yields_empty_string() {
		$this->assertSame(
			array( '' ),
			$this->find_args( "wp_add_dashboard_widget( 'id', \$label, 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_arg_with_only_class_constant_yields_empty_string() {
		$this->assertSame(
			array( '' ),
			$this->find_args( "wp_add_dashboard_widget( 'id', Foo::LABEL, 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	/**
	 * Block-registration calls: register_block_type, new WP_Block_Type, namespaced.
	 */
	public function test_register_block_type_literal_name() {
		$this->assertSame(
			array( 'my-plugin/foo' ),
			$this->find_args( "register_block_type( 'my-plugin/foo' );", 'register_block_type', 0 )
		);
	}

	public function test_new_wp_block_type_constructor() {
		$this->assertSame(
			array( 'my-plugin/baz' ),
			$this->find_args( "new WP_Block_Type( 'my-plugin/baz' );", 'new WP_Block_Type', 0 )
		);
	}

	public function test_register_block_type_with_leading_backslash() {
		// Plugins occasionally call the global function via `\register_block_type(...)`.
		$this->assertSame(
			array( 'my-plugin/leading-slash' ),
			$this->find_args( "\\register_block_type( 'my-plugin/leading-slash' );", 'register_block_type', 0 )
		);
	}

	/**
	 * Title-style metadata via find_function_call_first_arg_and_array_value().
	 */
	public function test_array_value_long_array_form() {
		$this->assertSame(
			array( 'my-plugin/foo' => 'Foo Title' ),
			$this->find_arg_and_array(
				"register_block_type( 'my-plugin/foo', array( 'title' => 'Foo Title' ) );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_short_array_form() {
		$this->assertSame(
			array( 'my-plugin/bar' => 'Bar Title' ),
			$this->find_arg_and_array(
				"register_block_type( 'my-plugin/bar', [ 'title' => 'Bar Title' ] );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_with_translation_wrapper() {
		$this->assertSame(
			array( 'my-plugin/wrap' => 'Translated Title' ),
			$this->find_arg_and_array(
				"new WP_Block_Type( 'my-plugin/wrap', array( 'title' => __( 'Translated Title', 'td' ) ) );",
				'new WP_Block_Type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_with_other_keys_present() {
		$this->assertSame(
			array( 'my-plugin/multi' => 'Real Title' ),
			$this->find_arg_and_array(
				"register_block_type( 'my-plugin/multi', array( 'category' => 'widgets', 'title' => 'Real Title', 'icon' => 'star' ) );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_missing_key_yields_null() {
		$this->assertSame(
			array( 'my-plugin/no-title' => null ),
			$this->find_arg_and_array(
				"register_block_type( 'my-plugin/no-title', array( 'category' => 'widgets' ) );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_with_no_options_array_yields_null() {
		$this->assertSame(
			array( 'my-plugin/bare' => null ),
			$this->find_arg_and_array(
				"register_block_type( 'my-plugin/bare' );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_with_variable_value_yields_null() {
		$this->assertSame(
			array( 'my-plugin/dyn' => null ),
			$this->find_arg_and_array(
				"register_block_type( 'my-plugin/dyn', array( 'title' => \$dynamic ) );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	public function test_array_value_skips_calls_with_non_literal_first_arg() {
		// First arg must be literal for this method to emit an entry.
		$this->assertSame(
			array(),
			$this->find_arg_and_array(
				"register_block_type( \$name, array( 'title' => 'X' ) );",
				'register_block_type',
				1,
				'title'
			)
		);
	}

	/**
	 * Shortcut (to reduce complexity): a concatenated literal-plus-expression is
	 * captured as the leading literal alone. We do not detect that the value is
	 * actually composed of multiple parts. Consumers that require a clean literal
	 * must validate the captured value (e.g. block detection requires `\w+/\w+`).
	 */
	public function test_shortcut_concatenated_literal_returns_leading_part() {
		$this->assertSame(
			array( 'prefix-' ),
			$this->find_args( "register_block_type( 'prefix-' . \$name );", 'register_block_type', 0 )
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
			$this->find_args( "Foo\\Bar\\register_block_type( 'ns/inside' );", 'register_block_type', 0 )
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
			$this->find_args( "wp_add_dashboard_widget( 'id', \$obj->method( 'Inner' ), 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}

	/**
	 * Shortcut (to reduce complexity): when the target arg is an array literal,
	 * the helper's positional-string method returns the first inner string it
	 * finds — typically a key, not the value. Real callers don't pass arrays
	 * for the labels we extract; for the registration pattern (name + options
	 * array), use find_function_call_first_arg_and_array_value() instead.
	 */
	public function test_shortcut_array_arg_captures_first_inner_string() {
		$this->assertSame(
			array( 'title' ),
			$this->find_args( "register_block_type( array( 'title' => 'Block Title' ) );", 'register_block_type', 0 )
		);
	}

	/**
	 * Multiple matches in one source.
	 */
	public function test_multiple_calls_in_one_file() {
		// Each call yields an entry, even when the label arg has no literal —
		// callers can still detect the function's presence in such cases.
		$src = "wp_add_dashboard_widget( 'a', 'A', 'cb' );\n"
			. "wp_add_dashboard_widget( 'b', __( 'B', 'td' ), 'cb' );\n"
			. "wp_add_dashboard_widget( 'c', \$variable, 'cb' );\n"
			. "wp_add_dashboard_widget( 'd', 'D', 'cb' );";
		$this->assertSame(
			array( 'A', 'B', '', 'D' ),
			$this->find_args( $src, 'wp_add_dashboard_widget', 1 )
		);
	}

	public function test_multiple_block_registrations_with_titles() {
		$src = "register_block_type( 'p/a', array( 'title' => 'A' ) );\n"
			. "register_block_type( 'p/b' );\n"
			. "register_block_type( 'p/c', array( 'title' => __( 'C', 'td' ) ) );";
		$this->assertSame(
			array(
				'p/a' => 'A',
				'p/b' => null,
				'p/c' => 'C',
			),
			$this->find_arg_and_array( $src, 'register_block_type', 1, 'title' )
		);
	}

	public function test_returns_empty_for_no_matches() {
		$this->assertSame(
			array(),
			$this->find_args( "do_something_else( 'foo' );", 'wp_add_dashboard_widget', 1 )
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
			$this->find_args( "WP_Add_Dashboard_Widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget', 1 )
		);
	}
}
