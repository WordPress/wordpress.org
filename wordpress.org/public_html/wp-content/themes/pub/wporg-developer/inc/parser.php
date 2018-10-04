<?php
/**
 * Code Reference parser customizations and tools.
 *
 * @package wporg-developer
 */

/**
 * Class to handle parser customization and tools.
 */
class DevHub_Parser {

	/**
	 * Initializer.
	 */
	public static function init() {
		add_action( 'init', [ __CLASS__, 'do_init' ] );
	}

	/**
	 * Handles adding/removing hooks.
	 */
	public static function do_init() {
		// Skip duplicate hooks.
		add_filter( 'wp_parser_skip_duplicate_hooks', '__return_true' );
	}

} // DevHub_Parser

DevHub_Parser::init();
