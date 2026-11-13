<?php
/**
 * Patterns where the registration arguments are assembled at runtime from parts.
 *
 * Seen in:
 * - Fluent Forms (app/Modules/MCP/AbilitiesRegistrar.php): a loop merges each tool
 *   class's definitions into one array, behind class_exists() checks, and the label is
 *   isset( $definition['label'] ) ? $definition['label'] : $name.
 * - WooCommerce (src/Internal/Abilities/AbilitiesLoader.php): the class list goes through
 *   array_values( array_unique( array_filter( array_merge( … ), 'is_string' ) ) ), and the
 *   name is a ternary between a class constant and a static method.
 * - MailPoet (lib/Abilities/AbilitiesRegistrar.php, generated/FreeCachedContainer.php): a
 *   dependency injection container builds the registrar, so its abilities arrive as typed
 *   constructor parameters and the plugin never writes `new` for them.
 * - Elementor (modules/mcp/abilities/): the description is read from a file, with an empty
 *   string when the file is missing. A scanner can't read the file, and shouldn't report
 *   the empty fallback as the description.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Assembled;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Report tools (Fluent Forms).
 */
class Report_Tools {

	/**
	 * Returns the name => definition map.
	 *
	 * @return array
	 */
	public static function definitions() {
		return array(
			'abilities-fixture/get-report' => array(
				'label'       => __( 'Get report', 'abilities-fixture' ),
				'description' => __( 'Returns entry counts by day.', 'abilities-fixture' ),
			),
		);
	}
}

/**
 * Export tools (Fluent Forms), one with no label.
 */
class Export_Tools {

	/**
	 * Returns the name => definition map.
	 *
	 * @return array
	 */
	public static function definitions() {
		return array(
			'abilities-fixture/export-report' => array(
				'description' => __( 'Exports the report as CSV.', 'abilities-fixture' ),
			),
		);
	}
}

/**
 * Merges the tool definitions and registers them (Fluent Forms).
 */
class Tool_Registrar {

	/**
	 * Returns the tool classes.
	 *
	 * @return string[]
	 */
	private static function tool_classes() {
		return array( Report_Tools::class, Export_Tools::class );
	}

	/**
	 * Merges every tool class's definitions.
	 *
	 * @return array
	 */
	public static function get_definitions() {
		$definitions = array();
		foreach ( self::tool_classes() as $class ) {
			if ( class_exists( $class ) && method_exists( $class, 'definitions' ) ) {
				$definitions = array_merge( $definitions, (array) $class::definitions() );
			}
		}
		$filtered = apply_filters( 'abilities_fixture_tool_definitions', $definitions );
		return is_array( $filtered ) ? $filtered : $definitions;
	}

	/**
	 * Registers the definitions.
	 */
	public static function register() {
		foreach ( self::get_definitions() as $name => $definition ) {
			wp_register_ability(
				$name,
				array(
					'label'               => isset( $definition['label'] ) ? $definition['label'] : $name,
					'description'         => isset( $definition['description'] ) ? $definition['description'] : '',
					'category'            => 'abilities-fixture',
					'execute_callback'    => '__return_empty_array',
					'permission_callback' => 'Abilities_Fixture\can_manage',
				)
			);
		}
	}
}

/**
 * Names itself with a constant, for the ternary below (WooCommerce).
 */
class Coupon_Create {

	const ABILITY_NAME = 'abilities-fixture/coupon-create';

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Create coupon', 'abilities-fixture' ),
			'description'         => __( 'Creates a discount coupon.', 'abilities-fixture' ),
			'category'            => 'abilities-fixture',
			'execute_callback'    => '__return_true',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

/**
 * Names itself with a method, for the ternary below (WooCommerce).
 */
class Coupons_Query {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'abilities-fixture/coupons-query';
	}

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Query coupons', 'abilities-fixture' ),
			'description'         => __( 'Lists coupons, newest first.', 'abilities-fixture' ),
			'category'            => 'abilities-fixture',
			'execute_callback'    => '__return_empty_array',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

/**
 * Registers a filtered class list (WooCommerce).
 */
class Coupon_Loader {

	const CORE_CLASSES = array( Coupon_Create::class, Coupons_Query::class );

	/**
	 * Returns the class list, deduplicated, after extensions have filtered it.
	 *
	 * @return string[]
	 */
	private static function get_classes(): array {
		$classes = apply_filters( 'abilities_fixture_coupon_classes', self::CORE_CLASSES );
		if ( ! is_array( $classes ) ) {
			$classes = array();
		}
		return array_values( array_unique( array_filter( array_merge( self::CORE_CLASSES, $classes ), 'is_string' ) ) );
	}

	/**
	 * Registers each class, named by its constant when it has one.
	 */
	public static function register() {
		foreach ( self::get_classes() as $class_name ) {
			$has_constant = defined( $class_name . '::ABILITY_NAME' ) && is_a( $class_name, Coupon_Create::class, true );
			$ability_name = $has_constant ? $class_name::ABILITY_NAME : $class_name::get_name();
			wp_register_ability( $ability_name, $class_name::get_registration_args() );
		}
	}
}

/**
 * An ability the container injects (MailPoet).
 */
class Bounce_Rate_Ability {

	const NAME = 'abilities-fixture/get-bounce-rate';

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return self::NAME;
	}

	/**
	 * Returns the registration arguments, without a category.
	 *
	 * @return array
	 */
	public function get_registration_args(): array {
		return array(
			'label'               => __( 'Get bounce rate', 'abilities-fixture' ),
			'description'         => __( 'Returns the share of emails that bounced this month.', 'abilities-fixture' ),
			'execute_callback'    => '__return_zero',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

/**
 * Receives its abilities from the container (MailPoet).
 */
class Injected_Registrar {

	/**
	 * The abilities to register.
	 *
	 * @var Bounce_Rate_Ability[]
	 */
	private $abilities;

	/**
	 * Stores the injected abilities.
	 *
	 * @param Bounce_Rate_Ability $bounce_rate The bounce rate ability.
	 */
	public function __construct( Bounce_Rate_Ability $bounce_rate ) {
		$this->abilities = array( $bounce_rate );
	}

	/**
	 * Registers the abilities in the fixture category.
	 */
	public function register() {
		foreach ( $this->abilities as $ability ) {
			wp_register_ability( $ability->get_name(), array_merge( $ability->get_registration_args(), array( 'category' => 'abilities-fixture' ) ) );
		}
	}
}

/**
 * A generated container, in the shape Symfony's dumper writes (MailPoet).
 */
class Container {

	/**
	 * Services built so far.
	 *
	 * @var array
	 */
	private $services = array();

	/**
	 * Builds the registrar.
	 *
	 * @return Injected_Registrar
	 */
	public function get_registrar() {
		return $this->services['registrar'] ?? ( $this->services['registrar'] = new Injected_Registrar( $this->services['bounce'] ?? $this->get_bounce_rate() ) ); // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- The shape a generated container has.
	}

	/**
	 * Builds the bounce rate ability.
	 *
	 * @return Bounce_Rate_Ability
	 */
	private function get_bounce_rate() {
		return $this->services['bounce'] = new Bounce_Rate_Ability(); // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found -- The shape a generated container has.
	}
}

/**
 * Loads text from files next to this one (Elementor).
 */
class Prompt_Loader {

	/**
	 * Returns a prompt's text, or '' when its file is missing.
	 *
	 * @param string $name Prompt name.
	 * @return string
	 */
	public static function load( string $name ): string {
		$path = __DIR__ . '/prompts/' . $name . '.md';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		return trim( (string) file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
	}
}

add_action( 'wp_abilities_api_init', array( Tool_Registrar::class, 'register' ) );
add_action( 'wp_abilities_api_init', array( Coupon_Loader::class, 'register' ) );
add_action(
	'wp_abilities_api_init',
	function () {
		( new Container() )->get_registrar()->register();

		wp_register_ability(
			'abilities-fixture/summarize-site',
			array(
				'label'               => __( 'Summarize site', 'abilities-fixture' ),
				'description'         => Prompt_Loader::load( 'summarize-site' ),
				'category'            => 'abilities-fixture',
				'execute_callback'    => '__return_empty_string',
				'permission_callback' => 'Abilities_Fixture\can_manage',
			)
		);
	}
);
