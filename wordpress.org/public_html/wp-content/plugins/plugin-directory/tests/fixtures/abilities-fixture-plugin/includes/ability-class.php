<?php
/**
 * Pattern: 'ability_class' => a WP_Ability subclass that supplies the category itself.
 *
 * Seen in: the AI plugin (includes/Experiments/, includes/Abstracts/Abstract_Ability.php).
 * The feature registers 'ai/' . $this->get_id() with only a label, a description and
 * 'ability_class'. The label and description come from the feature's own metadata, and
 * the category comes from the ability class's category() method, which returns a
 * define()d constant set in the main plugin file.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\AI;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

if ( ! defined( 'ABILITIES_FIXTURE_DEFAULT_CATEGORY' ) ) {
	define( 'ABILITIES_FIXTURE_DEFAULT_CATEGORY', 'abilities-fixture-ai' );
}

/**
 * Fills in everything except the label and description.
 */
abstract class Abstract_Ability extends \WP_Ability {

	/**
	 * Builds the full properties from the label and description it is given.
	 *
	 * @param string $name       Ability name.
	 * @param array  $properties Registration arguments.
	 */
	public function __construct( string $name, array $properties = array() ) {
		parent::__construct(
			$name,
			array(
				'label'               => $properties['label'] ?? '',
				'description'         => $properties['description'] ?? '',
				'category'            => $this->category(),
				'input_schema'        => array( 'type' => 'object' ),
				'execute_callback'    => array( $this, 'run' ),
				'permission_callback' => 'Abilities_Fixture\can_manage',
				'meta'                => array( 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Returns the category slug.
	 *
	 * @return string
	 */
	protected function category(): string {
		return ABILITIES_FIXTURE_DEFAULT_CATEGORY;
	}

	/**
	 * Runs the ability.
	 *
	 * @param array $input Ability input.
	 * @return mixed
	 */
	abstract public function run( $input = array() );
}

/**
 * Generates an excerpt.
 */
class Excerpt_Generation_Ability extends Abstract_Ability {

	/**
	 * Runs the ability.
	 *
	 * @param array $input Ability input.
	 * @return string
	 */
	public function run( $input = array() ) {
		return wp_trim_words( $input['content'] ?? '', 55 );
	}
}

/**
 * A feature that owns one ability.
 */
class Excerpt_Generation {

	/**
	 * Returns the feature ID, which is also the ability's name after the slash.
	 *
	 * @return string
	 */
	public static function get_id(): string {
		return 'excerpt-generation';
	}

	/**
	 * Returns the feature's metadata, which the ability reuses for its label and description.
	 *
	 * @return array
	 */
	protected function load_metadata(): array {
		return array(
			'label'       => __( 'Excerpt Generation', 'abilities-fixture' ),
			'description' => __( 'Writes an excerpt from the post content.', 'abilities-fixture' ),
		);
	}

	/**
	 * Returns the feature label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->load_metadata()['label'];
	}

	/**
	 * Returns the feature description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return $this->load_metadata()['description'];
	}

	/**
	 * Registers the ability.
	 */
	public function register_abilities(): void {
		wp_register_ability(
			sprintf( 'abilities-fixture/%s', self::get_id() ),
			array(
				'label'         => $this->get_label(),
				'description'   => $this->get_description(),
				'ability_class' => Excerpt_Generation_Ability::class,
			),
		);
	}
}

add_action(
	'wp_abilities_api_categories_init',
	function () {
		wp_register_ability_category(
			ABILITIES_FIXTURE_DEFAULT_CATEGORY,
			array(
				'label'       => __( 'Abilities Fixture AI', 'abilities-fixture' ),
				'description' => __( 'Abilities that generate content.', 'abilities-fixture' ),
			)
		);
	}
);

add_action( 'wp_abilities_api_init', array( new Excerpt_Generation(), 'register_abilities' ) );
