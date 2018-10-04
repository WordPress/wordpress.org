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

	/**
	 * Pre-caches source for parsed post types that support showing source code.
	 *
	 * By default, source code gets imported and cached as needed.
	 *
	 * Primarily intended to be run as a commandline convenience script.
	 *
	 * @return bool True on sucess, false on failure.
	 */
	public static function cache_source_code() {
		// Ensure the parsed code source directory exists.
		$import_dir = get_option( 'wp_parser_root_import_dir' );
		if ( ! $import_dir || ! file_exists( $import_dir ) ) {
			echo "Unable to cache source code; import directory does not exist: {$import_dir}\n";
			return false;
		}

		foreach ( \DevHub\get_post_types_with_source_code() as $post_type ) {
			$posts = get_posts( array( 'fields' => 'ids', 'post_type' => $post_type, 'posts_per_page' => '-1' ) );
			foreach ( $posts as $post ) {
				echo '.';
				\DevHub\get_source_code( $post, true );
			}
		}
		echo "\n";

		return true;
	}

} // DevHub_Parser

DevHub_Parser::init();
