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

	// Files and directories to skip from parsing.
	const SKIP_FROM_PARSING = [
		'wp-admin/css/',
		'wp-admin/includes/class-ftp',
		'wp-admin/includes/class-pclzip.php',
		'wp-admin/js/',
		'wp-content/',
		'wp-includes/ID3/',
		'wp-includes/IXR/',
		'wp-includes/PHPMailer/',
		'wp-includes/SimplePie/',
		'wp-includes/Text/',
		'wp-includes/blocks/',
		'wp-includes/block-patterns/',
		'wp-includes/block-supports/',
		'wp-includes/certificates/',
		'wp-includes/class-IXR.php',
		'wp-includes/class-json.php',
		'wp-includes/class-phpass.php',
		'wp-includes/class-phpmailer.php',
		'wp-includes/class-pop3.php ',
		'wp-includes/class-simplepie.php',
		'wp-includes/class-smtp.php',
		'wp-includes/class-snoopy.php',
		'wp-includes/class-wp-block-parser.php',
		'wp-includes/js/',
		'wp-includes/random_compat/',
		'wp-includes/sodium_compat/',
	];


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

		// Skip parsing of certain files.
		add_filter( 'wp_parser_pre_import_file',      [ __CLASS__, 'should_file_be_imported' ], 10, 2 );
	}

	/**
	 * Indicates if the given file should be imported for parsing or not.
	 *
	 * @param  bool  $import Should the file be imported?
	 * @param  array $file   File data.
	 * @return bool  True if file should be imported, else false.
	 */
	public static function should_file_be_imported( $import, $file ) {
		// Bail early if file is already being skipped.
		if ( ! $import ) {
			return $import;
		}

		// Skip file if it matches anything in the list.
		foreach ( self::SKIP_FROM_PARSING as $skip ) {
			if ( 0 === strpos( $file['path'], $skip ) ) {
				$import = false;
				break;
			}
		}

		return $import;
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
