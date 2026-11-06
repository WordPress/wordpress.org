<?php
/**
 * Plugin Name: Constructor Metadata
 */
namespace Meta;

// Modelled on the AI plugin: the constructor copies metadata into properties, and
// features are instantiated from a list of class names, never with a literal `new`.
abstract class Feature {
	protected string $label;
	protected string $description;

	final public function __construct() {
		$metadata          = $this->load_metadata();
		$this->label       = $metadata['label'];
		$this->description = $metadata['description'];
	}

	abstract public static function get_id(): string;
	abstract protected function load_metadata(): array;

	public function get_label(): string {
		return $this->label;
	}

	public function get_description(): string {
		return $this->description;
	}

	public function register_abilities(): void {
		wp_register_ability(
			'meta/' . static::get_id(),
			array(
				'label'         => $this->get_label(),
				'description'   => $this->get_description(),
				'ability_class' => Feature_Ability::class,
			)
		);
	}
}

final class Title_Generation extends Feature {
	public static function get_id(): string {
		return 'title-generation';
	}
	protected function load_metadata(): array {
		return array(
			'label'       => __( 'Title Generation', 'meta' ),
			'description' => __( 'Suggests titles.', 'meta' ),
		);
	}
}

add_action( 'init', function () {
	foreach ( apply_filters( 'meta_features', array( Title_Generation::class ) ) as $class ) {
		( new $class() )->register_abilities();
	}
} );
