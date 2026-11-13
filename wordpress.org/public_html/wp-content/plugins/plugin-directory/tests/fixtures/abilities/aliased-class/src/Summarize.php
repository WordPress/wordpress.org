<?php
namespace Aliased\Abilities;

class Summarize extends \WP_Ability {
	public function __construct( string $name, array $properties = array() ) {
		parent::__construct( $name, array_merge( $properties, array( 'category' => 'aliased-tools' ) ) );
	}
}
