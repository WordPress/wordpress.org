<?php
namespace Acme\Abilities;

class Abilities {
	public static function register() {
		\wp_register_ability( 'acme/fully-qualified', array( 'label' => __( 'Fully qualified', 'acme' ) ) );
		wp_register_ability( 'acme/unqualified', array( 'label' => __( 'Unqualified', 'acme' ) ) );
	}
}
