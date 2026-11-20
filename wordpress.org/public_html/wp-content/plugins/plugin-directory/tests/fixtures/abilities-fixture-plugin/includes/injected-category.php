<?php
/**
 * Pattern: the category is decided by whoever registers the ability, not by the ability.
 *
 * Seen in:
 * - Rank Math (includes/abilities/{group}/class-subscriber.php): each subscriber runs
 *   ( new $class( self::CATEGORY_SLUG ) )->register(), and the ability's literal call uses
 *   'category' => $this->category. Hooks go through Rank Math's own $this->action() helper,
 *   and the call is written \wp_register_ability().
 * - Fluent Forms (app/Modules/MCP/AbilitiesRegistrar.php): merges name => definition maps,
 *   then adds a fixed category. A definition without a label gets its name as the label.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Injected;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Wraps add_action(), like Rank Math's Hooker trait.
 */
trait Hooker {

	/**
	 * Hooks a method of this object to an action.
	 *
	 * @param string $hook     Action name.
	 * @param string $method   Method name.
	 * @param int    $priority Priority.
	 */
	protected function action( $hook, $method, $priority = 10 ) {
		\add_action( $hook, array( $this, $method ), $priority );
	}
}

/**
 * Registers a group of abilities in its category.
 */
class Settings_Subscriber {
	use Hooker;

	const CATEGORY_SLUG = 'abilities-fixture-settings';

	/**
	 * Hooks the registrations.
	 */
	public function __construct() {
		$this->action( 'wp_abilities_api_categories_init', 'register_category' );
		$this->action( 'wp_abilities_api_init', 'register_abilities' );
	}

	/**
	 * Registers the category.
	 */
	public function register_category() {
		\wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				'label'       => \esc_html__( 'Abilities Fixture Settings', 'abilities-fixture' ),
				'description' => \esc_html__( 'Abilities for reading plugin settings.', 'abilities-fixture' ),
			]
		);
	}

	/**
	 * Registers each ability in this subscriber's category.
	 */
	public function register_abilities() {
		foreach ( [ Get_Settings::class ] as $class ) {
			( new $class( self::CATEGORY_SLUG ) )->register();
		}
	}
}

/**
 * Reads the plugin settings.
 */
class Get_Settings {

	/**
	 * The category the subscriber assigns.
	 *
	 * @var string
	 */
	private $category;

	/**
	 * Stores the category.
	 *
	 * @param string $category Category slug.
	 */
	public function __construct( $category ) {
		$this->category = $category;
	}

	/**
	 * Registers the ability.
	 */
	public function register() {
		\wp_register_ability(
			'abilities-fixture/get-settings',
			[
				'category'            => $this->category,
				'label'               => \esc_html__( 'Get settings', 'abilities-fixture' ),
				'description'         => \esc_html__( 'Returns the plugin settings.', 'abilities-fixture' ),
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => 'Abilities_Fixture\can_manage',
			]
		);
	}
}

/**
 * Merges definition maps and adds the category (Fluent Forms).
 */
class Registrar {

	/**
	 * Returns the name => definition map, which other code can filter.
	 *
	 * @return array
	 */
	public static function definitions() {
		return apply_filters(
			'abilities_fixture_tool_definitions',
			array(
				'abilities-fixture/list-entries'   => array(
					'label'       => __( 'List entries', 'abilities-fixture' ),
					'description' => __( 'Lists form entries, newest first.', 'abilities-fixture' ),
				),
				// No label, so the registrar uses the name.
				'abilities-fixture/export-entries' => array(
					'description' => __( 'Exports form entries as CSV.', 'abilities-fixture' ),
				),
			)
		);
	}

	/**
	 * Registers every definition in the fixture category.
	 */
	public static function register() {
		foreach ( self::definitions() as $name => $definition ) {
			wp_register_ability(
				$name,
				array(
					'label'               => $definition['label'] ?? $name,
					'description'         => $definition['description'] ?? '',
					'category'            => 'abilities-fixture',
					'execute_callback'    => '__return_empty_array',
					'permission_callback' => 'Abilities_Fixture\can_manage',
				)
			);
		}
	}
}

new Settings_Subscriber();
add_action( 'wp_abilities_api_init', array( Registrar::class, 'register' ) );
