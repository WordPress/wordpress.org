<?php
/**
 * Pattern: the plugin wraps wp_register_ability() in its own function, so the call
 * sites never name it.
 *
 * Seen in:
 * - LearnPress (inc/MCP/Abilities.php): self::reg( name, label, description, schema, callback ),
 *   with the arguments in order and the category added inside reg().
 * - Formidable Forms (classes/helpers/FrmAbilitiesHelper.php): Helper::register( name, args ),
 *   with an ordinary arguments array.
 * - Easy Digital Downloads (src/CommandPalette/Registry.php): register( name, args ), which
 *   fills in the category and builds a description from the label when none is given.
 * - MailPoet (lib/WP/Functions.php): a camelCase proxy, $this->wp->registerAbility( name, args ),
 *   called with the category merged in. A grep for "registerAbility(" matches it in PHP.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Wrappers;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Positional wrapper (LearnPress).
 */
class Course_Abilities {

	const CATEGORY = 'abilities-fixture-courses';

	/**
	 * Registers the category.
	 */
	public static function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Abilities Fixture Courses', 'abilities-fixture' ),
				'description' => __( 'Abilities for courses and lessons.', 'abilities-fixture' ),
			)
		);
	}

	/**
	 * Registers the abilities.
	 */
	public static function register_abilities() {
		self::reg(
			'abilities-fixture/get-courses',
			__( 'Get courses', 'abilities-fixture' ),
			__( 'Lists courses, newest first.', 'abilities-fixture' ),
			array( 'type' => 'object' ),
			'__return_empty_array'
		);
	}

	/**
	 * Registers one ability in the course category.
	 *
	 * @param string   $name         Ability name.
	 * @param string   $label        Label.
	 * @param string   $description  Description.
	 * @param array    $input_schema Input schema.
	 * @param callable $callback     Execute callback.
	 */
	protected static function reg( string $name, string $label, string $description, array $input_schema, callable $callback ) {
		wp_register_ability(
			$name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => self::CATEGORY,
				'input_schema'        => $input_schema,
				'execute_callback'    => $callback,
				'permission_callback' => 'Abilities_Fixture\can_manage',
			)
		);
	}
}

/**
 * Keyed wrapper with defaults (Formidable Forms, Easy Digital Downloads).
 */
class Helper {

	/**
	 * Registers an ability, filling in the category, callbacks and, from the label, a description.
	 *
	 * @param string $name Ability name.
	 * @param array  $args Registration arguments.
	 */
	public static function register( string $name, array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'category'            => 'abilities-fixture',
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => 'Abilities_Fixture\can_manage',
			)
		);

		if ( empty( $args['description'] ) ) {
			/* translators: %s: What is searched, such as "Orders". */
			$args['description'] = sprintf( __( 'Searches %s by keyword.', 'abilities-fixture' ), $args['label'] );
		}

		wp_register_ability( $name, $args );
	}
}

/**
 * Thin proxies for WordPress functions (MailPoet).
 */
class WP_Functions {

	/**
	 * Proxies wp_register_ability().
	 *
	 * @param string $name Ability name.
	 * @param array  $args Registration arguments.
	 * @return \WP_Ability|null
	 */
	public function registerAbility( string $name, array $args ) { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- MailPoet's camelCase naming is the pattern being reproduced.
		return wp_register_ability( $name, $args );
	}
}

/**
 * An ability definition with its name in a constant (MailPoet).
 */
class Sending_Status {

	const NAME = 'abilities-fixture/get-sending-status';

	/**
	 * Returns the registration arguments, without a category.
	 *
	 * @return array
	 */
	public function getRegistrationArgs(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- MailPoet's camelCase naming is the pattern being reproduced.
		return array(
			'label'               => __( 'Get sending status', 'abilities-fixture' ),
			'description'         => __( 'Reports whether email sending is working.', 'abilities-fixture' ),
			'execute_callback'    => '__return_empty_array',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

add_action( 'wp_abilities_api_categories_init', array( Course_Abilities::class, 'register_category' ) );

add_action(
	'wp_abilities_api_init',
	function () {
		Course_Abilities::register_abilities();

		Helper::register(
			'abilities-fixture/list-forms',
			array(
				'label'       => __( 'List forms', 'abilities-fixture' ),
				'description' => __( 'Lists the forms on the site.', 'abilities-fixture' ),
			)
		);

		// No description, so the wrapper builds one from the label.
		Helper::register(
			'abilities-fixture/search-orders',
			array( 'label' => __( 'Orders', 'abilities-fixture' ) )
		);

		$wp      = new WP_Functions();
		$ability = new Sending_Status();
		$wp->registerAbility( $ability::NAME, array_merge( $ability->getRegistrationArgs(), array( 'category' => 'abilities-fixture' ) ) );
	}
);
