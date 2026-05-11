<?php
/**
 * Tests for Tools\Tokenisation_Helpers::find_function_calls().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools\Tokenisation_Helpers;

/**
 * @group tokenisation
 */
class Tokenisation_Helpers_Test extends TestCase {

	private function calls( string $php, string $function_name ): array {
		return Tokenisation_Helpers::find_function_calls( "<?php\n" . $php, $function_name );
	}

	/**
	 * Bare literals and translation wrappers.
	 */
	public function test_bare_string_literal() {
		$this->assertSame(
			array( array( 'id', 'Plain', 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', 'Plain', 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_translation_wrapper_double_underscore() {
		$this->assertSame(
			array( array( 'id', 'Translated', 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', __( 'Translated', 'td' ), 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_translation_wrapper_x() {
		$this->assertSame(
			array( array( 'id', 'Contextual', 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', _x( 'Contextual', 'ctx', 'td' ), 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_translation_wrapper_esc_html() {
		$this->assertSame(
			array( array( 'id', 'Escaped', 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', esc_html__( 'Escaped', 'td' ), 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_double_quoted_string() {
		$this->assertSame(
			array( array( 'id', 'Double', 'cb' ) ),
			$this->calls( 'wp_add_dashboard_widget( "id", "Double", "cb" );', 'wp_add_dashboard_widget' )
		);
	}

	public function test_escaped_single_quote_in_label() {
		$this->assertSame(
			array( array( 'id', "Bob's Widget", 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', __( 'Bob\\'s Widget', 'td' ), 'cb' );", 'wp_add_dashboard_widget' )
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
			array(
				// Implicit-keyed entries in the callback array are skipped per
				// the "only string-keyed entries" contract.
				array( null, 'My Widget', array() ),
			),
			$this->calls( $src, 'wp_add_dashboard_widget' )
		);
	}

	public function test_multiline_call() {
		$src = "wp_add_dashboard_widget(\n\t'id',\n\t__( 'Multi', 'td' ),\n\t'cb'\n);";
		$this->assertSame(
			array( array( 'id', 'Multi', 'cb' ) ),
			$this->calls( $src, 'wp_add_dashboard_widget' )
		);
	}

	/**
	 * False-positive prevention: comments, strings, method calls, declarations.
	 */
	public function test_call_inside_line_comment_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( "// wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_call_inside_block_comment_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( "/* wp_add_dashboard_widget( 'id', 'X', 'cb' ); */", 'wp_add_dashboard_widget' )
		);
	}

	public function test_call_inside_string_literal_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( "\$x = \"wp_add_dashboard_widget( 'id', 'X', 'cb' );\";", 'wp_add_dashboard_widget' )
		);
	}

	public function test_method_call_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( "\$obj->wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_static_method_call_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( "Foo::wp_add_dashboard_widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_function_declaration_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( 'function wp_add_dashboard_widget( $id, $name, $cb ) {}', 'wp_add_dashboard_widget' )
		);
	}

	public function test_function_exists_argument_is_ignored() {
		$this->assertSame(
			array(),
			$this->calls( "if ( function_exists( 'wp_add_dashboard_widget' ) ) {}", 'wp_add_dashboard_widget' )
		);
	}

	/**
	 * Non-literal arg values resolve to null. Calls are still reported.
	 */
	public function test_variable_arg_resolves_to_null() {
		$this->assertSame(
			array( array( 'id', null, 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', \$label, 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_class_constant_arg_resolves_to_null() {
		$this->assertSame(
			array( array( 'id', null, 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', Foo::LABEL, 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_concatenation_resolves_to_null() {
		$this->assertSame(
			array( array( 'id', null, 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', 'Prefix: ' . \$title, 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_non_translation_wrapper_resolves_to_null() {
		// Only the allowlist of i18n functions is unwrapped. An arbitrary wrapping
		// call like some_helper(...) does NOT unwrap to its inner literal.
		$this->assertSame(
			array( array( 'id', null, 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', some_helper( 'Inner' ), 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_method_call_in_arg_resolves_to_null() {
		$this->assertSame(
			array( array( 'id', null, 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', \$obj->method( 'Inner' ), 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	/**
	 * Block-registration calls: register_block_type, new WP_Block_Type, namespaced.
	 */
	public function test_register_block_type_literal_name() {
		$this->assertSame(
			array( array( 'my-plugin/foo' ) ),
			$this->calls( "register_block_type( 'my-plugin/foo' );", 'register_block_type' )
		);
	}

	public function test_new_wp_block_type_constructor() {
		$this->assertSame(
			array( array( 'my-plugin/baz' ) ),
			$this->calls( "new WP_Block_Type( 'my-plugin/baz' );", 'new WP_Block_Type' )
		);
	}

	public function test_register_block_type_with_leading_backslash() {
		// Plugins occasionally call the global function via `\register_block_type(...)`.
		$this->assertSame(
			array( array( 'my-plugin/leading-slash' ) ),
			$this->calls( "\\register_block_type( 'my-plugin/leading-slash' );", 'register_block_type' )
		);
	}

	/**
	 * Inline array literals are resolved to associative arrays of resolved values.
	 */
	public function test_array_literal_long_form() {
		$this->assertSame(
			array(
				array( 'my-plugin/foo', array( 'title' => 'Foo Title' ) ),
			),
			$this->calls( "register_block_type( 'my-plugin/foo', array( 'title' => 'Foo Title' ) );", 'register_block_type' )
		);
	}

	public function test_array_literal_short_form() {
		$this->assertSame(
			array(
				array( 'my-plugin/bar', array( 'title' => 'Bar Title' ) ),
			),
			$this->calls( "register_block_type( 'my-plugin/bar', [ 'title' => 'Bar Title' ] );", 'register_block_type' )
		);
	}

	public function test_array_literal_with_translation_wrapped_value() {
		$this->assertSame(
			array(
				array( 'my-plugin/wrap', array( 'title' => 'Translated Title' ) ),
			),
			$this->calls( "new WP_Block_Type( 'my-plugin/wrap', array( 'title' => __( 'Translated Title', 'td' ) ) );", 'new WP_Block_Type' )
		);
	}

	public function test_array_literal_with_mixed_value_types() {
		$this->assertSame(
			array(
				array(
					'my-plugin/multi',
					array(
						'category' => 'widgets',
						'title'    => 'Real Title',
						'callback' => null,
					),
				),
			),
			$this->calls(
				"register_block_type( 'my-plugin/multi', array( 'category' => 'widgets', 'title' => 'Real Title', 'callback' => \$cb ) );",
				'register_block_type'
			)
		);
	}

	public function test_nested_array_literal() {
		$this->assertSame(
			array(
				array(
					'my-plugin/nested',
					array(
						'attributes' => array( 'name' => 'X' ),
					),
				),
			),
			$this->calls(
				"register_block_type( 'my-plugin/nested', [ 'attributes' => [ 'name' => 'X' ] ] );",
				'register_block_type'
			)
		);
	}

	public function test_array_literal_skips_non_string_keys() {
		// Implicit integer keys are skipped. Only string-keyed entries appear.
		$this->assertSame(
			array(
				array( 'my-plugin/mixed-keys', array( 'title' => 'T' ) ),
			),
			$this->calls(
				"register_block_type( 'my-plugin/mixed-keys', array( 'first', 'title' => 'T', 'second' ) );",
				'register_block_type'
			)
		);
	}

	/**
	 * Documented shortcut (to reduce complexity): only fully-qualified
	 * `\register_block_type` is treated as the global function. Arbitrary
	 * `Foo\Bar\register_block_type(...)` calls are NOT matched — they are
	 * assumed to be unrelated functions sharing the name.
	 */
	public function test_shortcut_namespaced_call_is_not_matched() {
		$this->assertSame(
			array(),
			$this->calls( "Foo\\Bar\\register_block_type( 'ns/inside' );", 'register_block_type' )
		);
	}

	/**
	 * Documented shortcut (to reduce complexity): nested wrapping calls are
	 * NOT recursively unwrapped through non-allowlisted functions. So
	 * `esc_html( __( 'X', 'td' ) )` resolves to null because the outer
	 * `esc_html` is not in the translation allowlist.
	 */
	public function test_shortcut_non_translation_outer_wrapper_blocks_inner_translation() {
		$this->assertSame(
			array( array( 'id', null, 'cb' ) ),
			$this->calls( "wp_add_dashboard_widget( 'id', esc_html( __( 'X', 'td' ) ), 'cb' );", 'wp_add_dashboard_widget' )
		);
	}

	/**
	 * Multiple calls in one source.
	 */
	public function test_multiple_calls_in_one_file() {
		$src = "wp_add_dashboard_widget( 'a', 'A', 'cb' );\n"
			. "wp_add_dashboard_widget( 'b', __( 'B', 'td' ), 'cb' );\n"
			. "wp_add_dashboard_widget( 'c', \$variable, 'cb' );\n"
			. "wp_add_dashboard_widget( 'd', 'D', 'cb' );";
		$this->assertSame(
			array(
				array( 'a', 'A', 'cb' ),
				array( 'b', 'B', 'cb' ),
				array( 'c', null, 'cb' ),
				array( 'd', 'D', 'cb' ),
			),
			$this->calls( $src, 'wp_add_dashboard_widget' )
		);
	}

	public function test_returns_empty_for_no_matches() {
		$this->assertSame(
			array(),
			$this->calls( "do_something_else( 'foo' );", 'wp_add_dashboard_widget' )
		);
	}

	public function test_invalid_php_returns_empty_array() {
		$this->assertSame(
			array(),
			Tokenisation_Helpers::find_function_calls( 'this is not php', 'wp_add_dashboard_widget' )
		);
	}

	public function test_function_name_match_is_case_insensitive() {
		// PHP function names are case-insensitive; mirror that.
		$this->assertSame(
			array( array( 'id', 'X', 'cb' ) ),
			$this->calls( "WP_Add_Dashboard_Widget( 'id', 'X', 'cb' );", 'wp_add_dashboard_widget' )
		);
	}
}
