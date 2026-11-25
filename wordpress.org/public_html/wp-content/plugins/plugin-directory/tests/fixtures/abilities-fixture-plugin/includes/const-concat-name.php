<?php
/**
 * Pattern: ability objects whose names are built from a class constant.
 *
 * Seen in: Yoast SEO (src/abilities/user-interface/abilities/*.php, registered by
 * Abilities_Integration). get_name() returns CATEGORY_SLUG . '/update-term-seo-data',
 * and get_args() uses the same constant for the category. A scanner has to
 * resolve the constant to recover either value.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\SEO;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Registers the category and holds its slug.
 */
class Ability_Categories_Integration {

	const CATEGORY_SLUG = 'abilities-fixture-seo';

	/**
	 * Registers the category.
	 */
	public function register_categories() {
		\wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				'label'       => 'Abilities Fixture SEO',
				'description' => \__( 'Abilities for SEO data.', 'abilities-fixture' ),
			]
		);
	}
}

/**
 * Updates a term's SEO title and description.
 */
class Update_Term_SEO_Data_Ability {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return Ability_Categories_Integration::CATEGORY_SLUG . '/update-term-seo-data';
	}

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public function get_args(): array {
		return [
			'label'               => \__( 'Update term SEO data', 'abilities-fixture' ),
			'description'         => \__( 'Updates the SEO title and description of a category or tag.', 'abilities-fixture' ),
			'category'            => Ability_Categories_Integration::CATEGORY_SLUG,
			'input_schema'        => [ 'type' => 'object' ],
			'execute_callback'    => '__return_true',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		];
	}

	/**
	 * Lets an ability opt out at runtime, which a scanner cannot see.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}
}

/**
 * Registers every ability object it is given.
 */
class Abilities_Integration {

	/**
	 * The abilities to register.
	 *
	 * @var Update_Term_SEO_Data_Ability[]
	 */
	private $abilities;

	/**
	 * Stores the values.
	 *
	 * @param Update_Term_SEO_Data_Ability ...$abilities Abilities to register.
	 */
	public function __construct( ...$abilities ) {
		$this->abilities = $abilities;
	}

	/**
	 * Registers the abilities.
	 */
	public function register_abilities() {
		foreach ( $this->abilities as $ability ) {
			if ( ! $ability->is_available() ) {
				continue;
			}
			\wp_register_ability( $ability->get_name(), $ability->get_args() );
		}
	}
}

\add_action( 'wp_abilities_api_categories_init', [ new Ability_Categories_Integration(), 'register_categories' ] );
\add_action( 'wp_abilities_api_init', [ new Abilities_Integration( new Update_Term_SEO_Data_Ability() ), 'register_abilities' ] );
