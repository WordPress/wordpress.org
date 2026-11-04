<?php
/**
 * Pattern: a registrar base class registers a slug => spec map in a loop.
 *
 * Seen in: Jetpack (jetpack_vendor/automattic/jetpack-wp-abilities/src/class-registrar.php
 * and its subclasses under modules/ and src/abilities/). The only call site is
 * wp_register_ability( $slug, $spec ). The names and labels live in the array
 * returned by get_abilities(), and the category comes from get_category_slug()
 * when a spec leaves it out.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Shared registration plumbing.
 */
abstract class Registrar {

	/**
	 * Returns the category slug.
	 *
	 * @return string
	 */
	abstract public static function get_category_slug(): string;

	/**
	 * Returns the category label and description.
	 *
	 * @return array{label: string, description: string}
	 */
	abstract public static function get_category_definition(): array;

	/**
	 * Returns the abilities as a name => registration arguments map.
	 *
	 * @return array<string, array>
	 */
	abstract public static function get_abilities(): array;

	/**
	 * Hooks the registrations.
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( static::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( static::class, 'register_abilities' ) );
	}

	/**
	 * Registers the category.
	 */
	public static function register_category() {
		wp_register_ability_category( static::get_category_slug(), static::get_category_definition() );
	}

	/**
	 * Registers each ability, filling in the category when a spec leaves it out.
	 */
	public static function register_abilities() {
		foreach ( static::get_abilities() as $slug => $spec ) {
			if ( empty( $spec['category'] ) ) {
				$spec['category'] = static::get_category_slug();
			}
			wp_register_ability( $slug, $spec );
		}
	}
}

/**
 * A module's abilities.
 */
class Shortlinks_Abilities extends Registrar {

	/**
	 * Returns the category slug.
	 *
	 * @return string
	 */
	public static function get_category_slug(): string {
		return 'abilities-fixture-shortlinks';
	}

	/**
	 * Returns the category label and description.
	 *
	 * @return array
	 */
	public static function get_category_definition(): array {
		return array(
			'label'       => 'Abilities Fixture Shortlinks',
			'description' => __( 'Abilities for reading and creating shortlinks.', 'abilities-fixture' ),
		);
	}

	/**
	 * Returns the abilities as a name => registration arguments map.
	 *
	 * @return array
	 */
	public static function get_abilities(): array {
		return array(
			'abilities-fixture-shortlinks/get-shortlinks' => array(
				'label'               => __( 'Get shortlinks', 'abilities-fixture' ),
				'description'         => __( 'Returns shortlinks for the given posts.', 'abilities-fixture' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_ids' => array(
							'type'  => 'array',
							'items' => array( 'type' => 'integer' ),
						),
					),
				),
				'execute_callback'    => array( static::class, 'get_shortlinks' ),
				'permission_callback' => __NAMESPACE__ . '\can_manage',
			),
			// Sets its category explicitly, which the registrar keeps.
			'abilities-fixture/set-shortlinks-status'     => array(
				'label'               => __( 'Turn shortlinks on or off', 'abilities-fixture' ),
				'description'         => __( 'Turns the shortlinks module on or off.', 'abilities-fixture' ),
				'category'            => 'abilities-fixture',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'enabled' => array( 'type' => 'boolean' ) ),
				),
				'execute_callback'    => '__return_true',
				'permission_callback' => __NAMESPACE__ . '\can_manage',
			),
		);
	}

	/**
	 * Returns shortlinks for the requested posts.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	public static function get_shortlinks( $input = array() ) {
		return array_map( 'wp_get_shortlink', $input['post_ids'] ?? array() );
	}
}

Shortlinks_Abilities::init();
