<?php
/**
 * Plugin Name: Block Plugin With Textdomain Fixture
 * Description: Fixture used by Block_Plugin_Checker_Translation_Test.
 * Version: 1.0.0
 * License: GPL-2.0-or-later
 * Text Domain: block-plugin-with-textdomain
 */

add_action( 'init', function () {
	register_block_type_from_metadata( __DIR__ );
} );
