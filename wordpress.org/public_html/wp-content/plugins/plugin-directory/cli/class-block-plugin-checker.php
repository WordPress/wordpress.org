<?php
namespace WordPressdotorg\Plugin_Directory\CLI;

use WordPressdotorg\Plugin_Directory\Readme\Parser as Readme_Parser;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Block_JSON\Parser as Block_JSON_Parser;
use WordPressdotorg\Plugin_Directory\Block_JSON\Validator as Block_JSON_Validator;

/**
 * A class that can examine a plugin, and evaluate and return status info that would be useful for a validator or similar tool.
 *
 * Note: I've written this as one class for convenience, but as it's evolved I think it would make sense to split it in two: one for collecting data
 * (particularly the prepare*() and find*() functions), and a separate class for the checks.
 *
 * @package WordPressdotorg\Plugin_Directory\CLI
 */
class Block_Plugin_Checker {

	protected $allowed_hosts = array(
		'github.com',
		'plugins.svn.wordpress.org',
	);

	protected $path_to_plugin = null;
	protected $check_methods = array();
	protected $results = array();

	public $slug = null;
	public $repo_url = null;
	public $repo_revision = null;
	protected $readme_path = null;
	protected $readme = null;
	protected $headers = null;
	protected $blocks = null;
	protected $block_json_files = null;
	protected $asset_php_files = null;
	protected $block_json_validation = array();

	/**
	 * Constructor.
	 *
	 * @param string $slug The plugin slug, if known. Optional.
	 */
	public function __construct( $slug = null ) {
		$this->slug = $slug;

		add_filter( 'extra_plugin_headers', function( $extra_headers ) {
			$extra_headers[ 'License' ] = 'License';
			return $extra_headers;
		} );
	}

	/**
	 * Getter function for results.
	 */
	public function get_results( $type = null, $check = null ) {
		$out = array();
		foreach ( $this->results as $result ) {
			if ( 
				( is_null( $type ) || $type === $result->type ) &&
				( is_null( $check ) || $check === $result->check_name )
			) {
				$out[] = $result;
			}
		}

		return $out;
	}

	/**
	 * Check a plugin given the URL of a Subversion or GitHub repo.
	 * Note that only hosts listed in $allowed_hosts are permitted.
	 *
	 * @param string $url The URL of a Subversion or GitHub repository.
	 * @return array A list of status items.
	 */
	public function run_check_plugin_repo( $url ) {

		// git@github.com:sortabrilliant/jumbotron.git

		if ( preg_match( '#^\w+@github[.]com:(\w+/[^.]+)(?:\.git)?$#', $url, $matches ) ) {
			$url = 'https://github.com/' . $matches[1];
		}

		if ( preg_match( '#^(?:https://wordpress.org/plugins/)?([-\w]+)/?$#', $url, $matches ) ) {
			$url = 'https://plugins.svn.wordpress.org/' . $matches[1];
		}

		// Parse the URL with whitespace and trailing / trimmed
		$url_parts = wp_parse_url( rtrim( trim( $url ), '/' ) );

		if ( empty( $url_parts ) ) {
			$this->record_result(
				__FUNCTION__,
				'error',
				sprintf( __( 'Invalid url: %s', 'wporg-plugins' ), $url ),
				$url
			);
			return $this->results;
		}

		if ( empty( $url_parts['host'] ) || !in_array( $url_parts['host'], $this->allowed_hosts ) ) {
			$this->record_result(
				__FUNCTION__,
				'error',
				sprintf( __( 'URL must be GitHub or plugins.svn.wordpress.org: %s', 'wporg-plugins' ), $url ),
				$url
			);
			return $this->results;
		}

		if ( 'plugins.svn.wordpress.org' === $url_parts['host'] ) {
			$path_parts = explode( '/', $url_parts[ 'path' ], 3 );
			if ( empty( $path_parts[1] ) ) {
				$this->record_result(
					__FUNCTION__,
					'error',
					sprintf( __( 'URL must be a plugin repository: %s', 'wporg-plugins' ), $url ),
					$url
				);
				return $this->results;
			}

			$this->slug = $path_parts[1];
			$url = 'https://plugins.svn.wordpress.org/' . $path_parts[1];
			if ( 4 === count( $path_parts )
				&& ( 'tags' === $path_parts[2] || 'branches' === $path_parts[2] )
				&& ( !empty( $path_parts[3] ) ) ) {
				$url .= $path_parts[2] . '/' . $path_parts[3];
			} else {
				$url .= '/trunk';
			}
		} elseif ( 'github.com' === $url_parts['host'] ) {
			// https://github.com/sortabrilliant/jumbotron
			// git@github.com:sortabrilliant/jumbotron.git
			// https://github.com/sortabrilliant/jumbotron.git
			//
			if ( preg_match( '#^/(\w+/[^.]+)(?:\.git)?$#', $url_parts[ 'path' ], $matches ) ) {
				$url = 'https://github.com/' . $matches[1] . '.git/trunk';
			} else {
				$this->record_result(
					__FUNCTION__,
					'error',
					sprintf( __( 'URL must be a plugin repository: %s', 'wporg-plugins' ), $url ),
					$url
				);
				return $this->results;
			}
		}

		$url = esc_url_raw( $url );

		$path = $this->export_plugin( $url );

		if ( $path ) {
			$result = $this->run_check_plugin_files( $path );

			return $result;
		} else {
			return $this->results;
		}
		
	}

	function export_plugin( $svn_url ) {


		// Generate a unique tmp file directory name, but don't create it.
		$path = Filesystem::temp_directory( 'blockplugin' );

		$export = SVN::export( $svn_url, $path, array( 'ignore-externals', 'force' ) );
		if ( $export['result'] ) {
			$this->repo_revision = $export['revision'];
			$this->repo_url = $svn_url;
			return $path;
		} else {
			$this->record_result(
				__FUNCTION__,
				'error',
				sprintf( __( 'Error fetching repository %s: %s', 'wporg-plugins' ), $svn_url, $export['errors'][0]['error_code'] ),
				$export['errors']
			);
			return false;
		}

	}

	/**
	 * Check a plugin that has been unzipped or exported to a local filesystem.
	 *
	 * @param string $path_to_plugin Location where the plugin has been stored.
	 * @return array A list of status items.
	 */
	public function run_check_plugin_files( $path_to_plugin ) {
		$this->path_to_plugin = $path_to_plugin;
		$this->init();
		$this->prepare_data();
		$this->run_all_checks();
		return $this->results;
	}

	protected function init() {

		// Find all the methods on this class starting with `check_`
		$methods = get_class_methods( $this );
		foreach ( $methods as $method ) {
			if ( 0 === strpos( $method, 'check_' ) )
				$this->check_methods[] = $method;
		}
	}

	protected function prepare_data() {
		// Parse and stash the readme data
		$this->readme_path = Import::find_readme_file( $this->path_to_plugin );
		$this->readme = new Readme_Parser( $this->readme_path );

		// Parse and stash plugin headers
		$this->headers = Import::find_plugin_headers( $this->path_to_plugin );

		// Parse and stash block info
		$this->blocks = $this->find_blocks( $this->path_to_plugin );

		foreach ( $this->block_json_files as $block_json_file ) {
			$validator = new Block_JSON_Validator();
			$block_json = Block_JSON_Parser::parse( array( 'file' => $block_json_file ) );
			$this->block_json_validation[ $block_json_file ] = $validator->validate( $block_json );
		}

		$this->asset_php_files = $this->find_asset_php_files( $this->path_to_plugin );
	}

	public function relative_filename( $filename ) {
		return str_replace( "{$this->path_to_plugin}/", '', $filename );
	}

	public function run_all_checks() {
		foreach ( array_unique( $this->check_methods ) as $method ) {
			call_user_func( array( $this, $method ) );
		}
	}

	public function find_blocks( $base_dir ) {
		$blocks = array();
		$this->block_json_files = Filesystem::list_files( $base_dir, true, '!(?:^|/)block\.json$!i' );
		if ( ! empty( $this->block_json_files ) ) {
			foreach ( $this->block_json_files as $filename ) {
				$blocks_in_file = Import::find_blocks_in_file( $filename );
				$relative_filename = $this->relative_filename( $filename );
				$potential_block_directories[] = dirname( $relative_filename );
				foreach ( $blocks_in_file as $block ) {
					$blocks[ $block->name ] = $block;
				}
			}
		}
		foreach ( Filesystem::list_files( $base_dir, true, '!\.(?:php|js|jsx)$!i' ) as $filename ) {
			$blocks_in_file = Import::find_blocks_in_file( $filename );
			if ( ! empty( $blocks_in_file ) ) {
				$relative_filename = $this->relative_filename( $filename );
				foreach ( $blocks_in_file as $block ) {
					if ( preg_match( '!\.(?:js|jsx)$!i', $relative_filename ) && empty( $block->script ) )
						$block->script = $relative_filename;
					if ( empty( $blocks[ $block->name ] ) || empty( $blocks[ $block->name ]->title ) )
						$blocks[ $block->name ] = $block;
				}
			}
		}

		return $blocks;
	}

	public function find_block_scripts() {
		$block_scripts = array();
		foreach ( $this->blocks as $block ) {
			$scripts = Import::extract_file_paths_from_block_json( $block );
			if ( isset( $block_scripts[ $block->name ] ) ) {
				$block_scripts[ $block->name ] = array_merge( $block_scripts[ $block->name ], $scripts );
			} else {
				$block_scripts[ $block->name ] = $scripts;
			}
		}

		return $block_scripts;
	}

	/**
	 * Find index.asset.php files as generated by the create block scripts.
	 */
	public function find_asset_php_files( $base_dir ) {
		return Filesystem::list_files( $base_dir, true, '!(:^|/)\w+\.asset\.php$!' );
	}

	/**
	 * Used by check_*() functions to record info.
	 *
	 * @param string $check_name An unambiguous name of the check. Should normally be the calling __FUNCTION__.
	 * @param string $type The type of info being recorded: 'error', 'problem', 'info', etc.
	 * @param string $message A human-readable message explaining the info.
	 * @param mixed $data Additional data related to the info, optional. Typically a string or array.
	 */
	protected function record_result( $check_name, $type, $message, $data = null ) {
		$this->results[] = (object) array(
			'check_name' => $check_name,
			'type' => $type,
			'message' => $message,
			'data' => $data );
	}

	/**
	 * Check functions below here. Must be named `check_*()`.
	 */

	/**
	 * Readme.txt file must be present.
	 */
	function check_readme_exists() {
		if ( empty( $this->readme_path ) || !file_exists( $this->readme_path ) ) {
			$this->record_result( __FUNCTION__,
				'error',
				__( 'Missing readme.txt file.', 'wporg-plugins' ),
				$this->relative_filename( $this->readme_path )
			);
		}
	}

	/**
	 * Readme should have a license.
	 */
	function check_license() {
		if ( empty( $this->readme->license ) && empty( $this->headers->License ) ) {
			$this->record_result( __FUNCTION__,
				'warning',
				__( 'No plugin license was found.', 'wporg-plugins' )
			);
		} elseif ( !empty( $this->readme->license ) ) {
			$this->record_result( __FUNCTION__,
				'info',
				sprintf( __( 'Found a license in readme.txt: %s.', 'wporg-plugins' ), $this->readme->license ),
				$this->readme->license
			);
		} elseif ( !empty( $this->headers->License ) ) {
			$this->record_result( __FUNCTION__,
				'info',
				sprintf( __( 'Found a license in plugin headers: %s.', 'wporg-plugins' ), $this->headers->License ),
				$this->headers->License
			);
		}
	}

	/**
	 * Must have plugin headers.
	 */
	function check_plugin_headers() {
		if ( empty( $this->headers ) ) {
			$this->record_result( __FUNCTION__,
				'error',
				__( 'Missing plugin headers. Is this a WordPress plugin?', 'wporg-plugins' )
			);
		}
	}

	/**
	 * Should have the 'block' tag.
	 */
	function check_block_tag() {
		if ( empty( $this->readme->tags ) || ! in_array( 'block', array_map( 'strtolower', $this->readme->tags ) ) ) {
			$this->record_result( __FUNCTION__,
				'warning',
				__( 'No block tag found in readme.txt.', 'wporg-plugins' ),
				$this->readme->tags
			);
		} else {
			$this->record_result( __FUNCTION__,
				'info',
				__( 'Found a block tag in readme.txt.', 'wporg-plugins' ),
				$this->readme->tags
			);
		}
	}

	/**
	 * Does the plugin have a block name that already exists in the DB?
	 * Note that this isn't a blocker if we're re-running checks on a plugin that has already been uploaded, since it will match with itself.
	 */
	function check_for_duplicate_block_name() {
		foreach ( $this->blocks as $block ) {
			if ( !trim( strval( $block->name ) ) )
				continue;

			$query_args = array(
				'post_type' => 'plugin',
				'meta_query' => array(
					array(
						'key' => 'block_name',
						'value' => $block->name,
					)
				)
			);

			$query = new \WP_Query( $query_args );
			if ( $query->found_posts > 0 ) {
				foreach ( $query->posts as $post ) {
					if ( $this->slug && $this->slug === $post->post_name )
						continue; // It's this very same plugin

					$this->record_result( __FUNCTION__,
						'info',
						sprintf( __( 'Block name %s already exists in plugin %s.', 'wporg-plugins' ), $block->name, $query->posts[0]->post_name ),
						[ 'block_name' => $block->name, 'slug' => $post->post_name ]
					);
				}
			}
		}

	}

	/**
	 * There should be at least one block.
	 */
	function check_for_blocks() {
		if ( 0 === count( $this->blocks ) ) {
			$this->record_result( __FUNCTION__,
				'error',
				__( 'No blocks found in plugin.', 'wporg-plugins' )
			);
		} else {
			$this->record_result( __FUNCTION__,
				( count( $this->blocks ) < 5 ? 'info' : 'warning' ), // 5+ blocks suggests it's probably not a block plugin
				sprintf( _n( 'Found %d block.', 'Found %d blocks.', count( $this->blocks ), 'wporg-plugins' ), count( $this->blocks ) ),
				array_keys( $this->blocks )
			);
		}
	}

	/**
	 * Every block should have a block.json file, ideally.
	 */
	function check_for_block_json() {
		foreach ( $this->blocks as $block_name => $block_info ) {
			if ( !empty( $this->block_json_files[ $block_name ] ) ) {
				$this->record_result( __FUNCTION__,
					'info',
					sprintf( __( 'block.json file exists for block %s.', 'wporg-plugins' ), $block_name ),
					$this->block_json_files[ $block_name ]
				);
			}
		}

		if ( empty( $this->block_json_files ) ) {
			$this->record_result( __FUNCTION__,
				'warning',
				__( 'No block.json files were found.', 'wporg-plugins' )
			);
		}
	}

	/**
	 * Do the blocks all have available script assets, either discoverable or in the block.json file?
	 */
	function check_for_block_scripts() {
		$block_scripts = $this->find_block_scripts();

		foreach ( $this->blocks as $block_name => $block_info ) {
			if ( !empty( $block_scripts[ $block_name ] ) ) {
				$this->record_result( __FUNCTION__,
					'info',
					sprintf( __( 'Scripts found for block %s.', 'wporg-plugins' ), $block_name ),
					$block_scripts[ $block_name ]
				);
			} else {
				$this->record_result( __FUNCTION__,
					'warning',
					sprintf( __( 'No scripts found for block %s.', 'wporg-plugins' ), $block_name ),
					$block_name
				);
			}
		}
	}

	/**
	 * Do the script files all exist?
	 */
	function check_for_block_script_files() {
		foreach ( $this->find_block_scripts() as $block_name => $scripts ) {
			foreach ( $scripts as $script ) {
				if ( file_exists( $this->path_to_plugin . $script ) ) {
					$this->record_result( __FUNCTION__,
						'info',
						sprintf( __( 'Script file exists for block %s.', 'wporg-plugins' ), $block_name ),
						$script
					);
				} else {
					$this->record_result( __FUNCTION__,
						'warning',
						sprintf( __( 'Missing script file for block %s.', 'wporg-plugins' ), $block_name ),
						$script
					);
				}
			}
		}
	}

	/**
	 * Does the JS call registerBlockType?
	 */
	function check_for_register_block_type() {
		$block_files = array();
		foreach ( Filesystem::list_files( $this->path_to_plugin, true, '!\.(?:js|jsx)$!i' ) as $filename ) {
			$js = file_get_contents( $filename );
			// TODO: need something better than this. find_blocks_in_file() misses quite a few unfortunately.
			if ( false !== strpos( $js, 'registerBlockType' ) ) {
				$block_files[] = $filename;
			}
			unset( $js );
		}

		if ( empty( $block_files ) ) {
			$this->record_result( __FUNCTION__,
				'error',
				sprintf( __( '<code>registerBlockType()</code> must be called in a JavaScript file.', 'wporg-plugins' ) )
			);
		}
	}

	/**
	 * Are the block.json files parseable?
	 */
	function check_block_json_is_valid_json() {
		foreach ( $this->block_json_validation as $block_json_file => $result ) {
			if ( is_wp_error( $result ) ) {
				if ( $message = $result->get_error_message( 'json_parse_error' ) ) {
					$this->record_result( __FUNCTION__,
						'error',
						sprintf( __( 'Error attempting to parse json in %s: %s', 'wporg-plugins' ), $this->relative_filename( $block_json_file ), $message ),
						$this->relative_filename( $block_json_file )
					);
				}
			}
		}
	}

	/**
	 * Are the block.json files valid?
	 */
	function check_block_json_is_valid() {
		foreach ( $this->block_json_validation as $block_json_file => $result ) {
			if ( true === $result ) {
				$this->record_result( __FUNCTION__,
					'info',
					sprintf( __( 'JSON file %s is valid.', 'wporg-plugins' ), $this->relative_filename( $block_json_file ) ),
					$this->relative_filename( $block_json_file )
				);
				continue;
			}

			foreach ( $result->get_error_codes() as $code ) {
				$messages = $result->get_error_messages( $code );
				foreach ( $messages as $i => $message ) {
					if ( 'json_parse_error' === $code ) {
						continue; // Already handled in check_block_json_is_valid_json
					} else {
						$this->record_result( __FUNCTION__,
							( 'error' === $code ? 'warning' : $code ), // TODO: be smarter about mapping these
							$this->relative_filename( $block_json_file ) . ': ' . $message,
							array(
								$this->relative_filename( $block_json_file ),
								$result->get_error_data( $code )
							)
						);
					}
				}
			}
		}
	}

	/**
	 * Does it have an index.asset.php file generate by the create block scripts?
	 */
	function check_asset_php_file() {
		$count = 0;
		foreach ( $this->asset_php_files as $file ) {
			$contents = file_get_contents( $file );
			if ( false !== strpos( $contents, '<?php return array(' ) )
				++ $count;
		}

		if ( 1 === $count ) {
			$this->record_result( __FUNCTION__,
				'info',
				sprintf( __( 'PHP asset file found: %s.', 'wporg-plugins' ), $this->relative_filename( $file ) ),
				$this->relative_filename( $file )
			);
		} elseif ( $count > 1 ) {
			$this->record_result( __FUNCTION__,
				'warning',
				sprintf( __( 'Found %s PHP asset files.', 'wporg-plugins' ), $count ),
				array_map( null, array( $this->relative_filename( $file ) ), $this->asset_php_files )
			);
		}
	}

	/**
	 * Does it have a lot of PHP code?
	 */
	function check_php_size() {
		$php_files = Filesystem::list_files( $this->path_to_plugin, true, '!\.php$!i' );
		$total_size = 0;
		foreach ( $php_files as $file ) {
			$total_size += filesize( $file );
		}
		if ( $total_size > 50 * KB_IN_BYTES ) {
			$this->record_result( __FUNCTION__,
				'error',
				sprintf( __( 'Found %s of PHP code. Please submit PHP plugins to the plugin directory.', 'wporg-plugins' ), size_format( $total_size ) ),
				$total_size
			);
		} elseif ( $total_size > 10 * KB_IN_BYTES ) {
			$this->record_result( __FUNCTION__,
				'warning',
				sprintf( __( 'Found %s of PHP code. This might not be a block plugin.', 'wporg-plugins' ), size_format( $total_size ) ),
				$total_size
			);
		} else {
			$this->record_result( __FUNCTION__,
				'info',
				sprintf( __( 'Found %s of PHP code. Thanks for keeping it minimal!', 'wporg-plugins' ), size_format( $total_size ) ),
				$total_size
			);
		}
	}
}
