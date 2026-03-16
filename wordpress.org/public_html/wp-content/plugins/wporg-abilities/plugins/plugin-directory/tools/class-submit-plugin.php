<?php
/**
 * Submit Plugin tool.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Tools;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Ability_Base;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;

defined( 'ABSPATH' ) || exit;

/**
 * Submit_Plugin class.
 */
class Submit_Plugin extends Ability_Base {

	/**
	 * Register this tool as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/submit-plugin',
			array(
				'label'               => 'Submit Plugin',
				'description'         => <<<'TEXT'
Submits a plugin ZIP to the WordPress.org plugin directory for review (first-time) or uploads a new version of a plugin currently in review. Published plugins are updated via SVN, not this tool.

Before using this tool:
1. Run the wporg://plugins/plugin-directory/prepare-plugin-for-submission MCP prompt to walk through all requirements (note: this is a prompt, not a tool)
2. Use wporg://plugins/plugin-directory/validate-readme to check your readme
3. Run Plugin Check locally before submitting — this is required and the server will reject plugins that fail. See wporg://plugins/plugin-directory/plugin-check-guide for instructions.
4. Use wporg://plugins/plugin-directory/get-plugin-status to check existing submissions

For first-time submissions, omit plugin_slug.
For updates to plugins in review, provide the plugin_slug.
Provide the ZIP via zip_url (preferred for larger plugins) or zip_base64 (base64-encoded). All confirmations must be true — they are attestations that you have actually completed each step, not formalities.
TEXT
				,
				'category'            => 'wporg-plugins-plugin-directory',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'zip_base64'    => array(
							'type'        => 'string',
							'description' => 'Base64-encoded contents of the plugin ZIP file. Use zip_url instead for larger plugins. Provide exactly one of zip_url or zip_base64.',
						),
						'zip_url'       => array(
							'type'        => 'string',
							'description' => 'URL to download the plugin ZIP from. Must be a publicly accessible HTTPS URL ending in .zip. Preferred for larger plugins. Provide exactly one of zip_url or zip_base64.',
						),
						'plugin_slug'   => array(
							'type'        => 'string',
							'description' => 'Slug of an existing plugin to update with a new ZIP. Omit for first-time submissions. Use wporg://plugins/plugin-directory/get-plugin-status to find your plugin slug.',
						),
						'filename'      => array(
							'type'        => 'string',
							'description' => 'Filename for the ZIP. Use the format {plugin-slug}.zip (e.g. "my-plugin.zip").',
						),
						'comment'       => array(
							'type'        => 'string',
							'description' => 'Message to include with the submission. Only used for updates (when plugin_slug is provided) — describe what changed. Do not include for first-time submissions.',
						),
						'upload_token'  => array(
							'type'        => 'string',
							'description' => 'Upload token provided by the plugin review team, if applicable.',
						),
						'confirmations' => array(
							'type'        => 'object',
							'description' => 'Required confirmations from the user. You MUST present each confirmation to the user and get their explicit approval for each submission before setting any to true. Prior approvals do not carry forward. Confirmations apply to the specific ZIP being submitted, not to the plugin in general. Do not set these to true on the user’s behalf — these are attestations that only the user can make.',
							'properties'  => array(
								'read_faq'             => array(
									'type'        => 'boolean',
									'description' => 'The user confirms they have read the Plugin Developer FAQ (wporg://plugins/plugin-directory/plugin-faq).',
								),
								'guidelines_compliant' => array(
									'type'        => 'boolean',
									'description' => 'The user confirms the plugin complies with all Plugin Developer Guidelines (wporg://plugins/plugin-directory/plugin-guidelines).',
								),
								'has_permission'       => array(
									'type'        => 'boolean',
									'description' => 'The user confirms they have permission to upload this plugin for others to use and share.',
								),
								'gpl_compatible'       => array(
									'type'        => 'boolean',
									'description' => 'The user confirms the plugin, all included libraries, and assets are GPL or GPL-compatible licensed.',
								),
								'plugin_check_passed'  => array(
									'type'        => 'boolean',
									'description' => 'The user confirms the plugin has been tested with Plugin Check and all issues resolved.',
								),
							),
							'required'    => array( 'read_faq', 'guidelines_compliant', 'has_permission', 'gpl_compatible', 'plugin_check_passed' ),
						),
					),
					'required'   => array( 'filename', 'confirmations' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'              => array(
							'type'        => 'boolean',
							'description' => 'Whether the upload succeeded.',
						),
						'plugin_slug'          => array(
							'type'        => 'string',
							'description' => 'The assigned or existing plugin slug.',
						),
						'plugin_name'          => array(
							'type'        => 'string',
							'description' => 'The plugin name from headers.',
						),
						'version'              => array(
							'type'        => 'string',
							'description' => 'The plugin version from headers.',
						),
						'message'              => array(
							'type'        => 'string',
							'description' => 'Human-readable result message in markdown.',
						),
						'error_code'           => array(
							'type'        => 'string',
							'description' => 'Machine-readable error code, present on failure.',
						),
						'next_steps'           => array(
							'type'        => 'string',
							'description' => 'Actionable guidance on what to do next.',
						),
						'plugin_check_results' => array(
							'type'        => 'array',
							'description' => 'Automated Plugin Check scan results, if available.',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'type'    => array( 'type' => 'string' ),
									'code'    => array( 'type' => 'string' ),
									'file'    => array( 'type' => 'string' ),
									'message' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
				'meta'                => array(
					'mcp'         => array( 'type' => 'tool' ),
					'annotations' => array(
						'readonly'    => false,
						'idempotent'  => false,
						'destructive' => false,
					),
				),
			)
		);
	}

	/**
	 * Require an authenticated user.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_permission() {
		if ( get_current_user_id() > 0 ) {
			return true;
		}

		return new \WP_Error(
			'authentication_required',
			'You must be authenticated to submit plugins.'
		);
	}

	/**
	 * Submit a plugin ZIP for review or update an existing submission.
	 *
	 * @param array $input The tool input.
	 * @return array MCP tool result.
	 */
	public static function execute( array $input ): array {
		self::maybe_load_plugin_directory();

		// Step 1: Validate confirmations.
		$confirmation_error = self::validate_confirmations( $input['confirmations'] ?? array() );
		if ( $confirmation_error ) {
			return $confirmation_error;
		}

		$is_update = ! empty( $input['plugin_slug'] );

		// Step 2: Pre-flight checks.
		$can_upload = Upload_Handler::accepting_uploads( $is_update );
		if ( is_wp_error( $can_upload ) ) {
			return self::error_response(
				$can_upload->get_error_code(),
				$can_upload->get_error_message(),
				self::get_next_steps_for_error( $can_upload->get_error_code() )
			);
		}

		// Step 3: Resolve $plugin_post_id.
		$plugin_post_id = 0;
		if ( $is_update ) {
			$slug = sanitize_title( $input['plugin_slug'] );
			$post = self::find_plugin_post( $slug );

			if ( ! $post ) {
				return self::error_response(
					'plugin_not_found',
					sprintf( 'No plugin with slug "%s" was found for your account.', $slug ),
					'Use wporg://plugins/plugin-directory/get-plugin-status to check your plugin slugs.'
				);
			}

			$plugin_post_id = $post->ID;
		}

		// Step 4: Prepare ZIP file from zip_url or zip_base64.
		$temp_path = self::prepare_zip_file( $input );
		if ( is_array( $temp_path ) ) {
			return $temp_path; // Error response.
		}

		// Step 5: Populate $_FILES and $_POST, call process_upload, clean up.
		$filename = sanitize_file_name( $input['filename'] );

		// phpcs:disable WordPress.Security.NonceVerification -- Auth is handled by the MCP transport layer.
		$saved_files   = $_FILES;
		$saved_post    = $_POST;
		$saved_request = $_REQUEST;

		$_FILES['zip_file'] = array(
			'name'     => $filename,
			'type'     => 'application/zip',
			'tmp_name' => $temp_path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => filesize( $temp_path ),
		);

		$_POST['comment'] = $input['comment'] ?? '';

		if ( ! empty( $input['upload_token'] ) ) {
			$_REQUEST['upload_token'] = $input['upload_token'];
		}

		// Skip is_uploaded_file() check — file was fetched via URL or decoded from base64, not uploaded via HTTP.
		$use_sideload = function ( $overrides ) {
			$overrides['action'] = 'wp_handle_sideload';
			return $overrides;
		};
		add_filter( 'wporg_plugin_upload_overrides', $use_sideload );

		// On sandboxes, rename() fails across mount points; use copy + unlink instead.
		if ( defined( 'WPORG_SANDBOXED' ) && WPORG_SANDBOXED ) {
			$cross_fs_move = function ( $move_new_file, $file, $new_file ) {
				if ( null !== $move_new_file ) {
					return $move_new_file;
				}
				if ( @rename( $file['tmp_name'], $new_file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged
					return $new_file;
				}
				if ( @copy( $file['tmp_name'], $new_file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
					wp_delete_file( $file['tmp_name'] );
					return $new_file;
				}
				return false;
			};
			add_filter( 'pre_move_uploaded_file', $cross_fs_move, 10, 3 );
		}

		try {
			$handler = new Upload_Handler();
			$result  = $handler->process_upload( $plugin_post_id );
		} finally {
			remove_filter( 'wporg_plugin_upload_overrides', $use_sideload );
			if ( isset( $cross_fs_move ) ) {
				remove_filter( 'pre_move_uploaded_file', $cross_fs_move );
			}

			wp_delete_file( $temp_path );
			$_FILES   = $saved_files;
			$_POST    = $saved_post;
			$_REQUEST = $saved_request;
			// phpcs:enable WordPress.Security.NonceVerification
		}

		// Step 6: Format response.
		if ( is_wp_error( $result ) ) {
			return self::error_response(
				$result->get_error_code(),
				self::html_to_text( $result->get_error_message() ),
				self::get_next_steps_for_error( $result->get_error_code() )
			);
		}

		$response = array(
			'success'     => true,
			'plugin_slug' => $handler->plugin_slug,
			'plugin_name' => $handler->plugin['Name'] ?? '',
			'version'     => $handler->plugin['Version'] ?? '',
			'message'     => self::html_to_text( $result ),
			'next_steps'  => 'Your plugin has been submitted for review with the initial slug "' . $handler->plugin_slug . '". You will receive a confirmation email. Use wporg://plugins/plugin-directory/get-plugin-status with slug "' . $handler->plugin_slug . '" to track review progress.',
		);

		if ( $is_update ) {
			$response['next_steps'] = 'Your updated ZIP has been received. If your plugin is being reviewed, respond to the review email to let the reviewer know. Use wporg://plugins/plugin-directory/get-plugin-status with slug "' . $handler->plugin_slug . '" to check status.';
		}

		return $response;
	}

	/**
	 * Validate that all required confirmations are true.
	 *
	 * @param array $confirmations The confirmations input.
	 * @return array|null Error response or null if valid.
	 */
	private static function validate_confirmations( array $confirmations ): ?array {
		$required = array(
			'read_faq'             => 'Read the Plugin Developer FAQ',
			'guidelines_compliant' => 'Plugin complies with Plugin Developer Guidelines',
			'has_permission'       => 'Permission to upload this plugin',
			'gpl_compatible'       => 'Plugin and all assets are GPL-compatible',
			'plugin_check_passed'  => 'Plugin tested with Plugin Check',
		);

		$missing = array();
		foreach ( $required as $key => $label ) {
			if ( empty( $confirmations[ $key ] ) ) {
				$missing[] = $label;
			}
		}

		if ( $missing ) {
			return self::error_response(
				'missing_confirmations',
				'The following confirmations are required but missing or false: ' . implode( '; ', $missing ) . '.',
				'Run the wporg://plugins/plugin-directory/prepare-plugin-for-submission MCP prompt and verify your plugin meets all requirements.'
			);
		}

		return null;
	}

	/**
	 * Prepare a ZIP file from either a URL or base64-encoded data.
	 *
	 * Validation of the ZIP contents (headers, readme, etc.) is handled
	 * by Upload_Handler::process_upload() — this method only resolves the
	 * input into a temp file.
	 *
	 * @param array $input The tool input.
	 * @return string|array Temp file path on success, or error response array on failure.
	 */
	private static function prepare_zip_file( array $input ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';

		if ( ! empty( $input['zip_url'] ) ) {
			return self::prepare_zip_from_url( $input['zip_url'] );
		}

		if ( ! empty( $input['zip_base64'] ) ) {
			return self::prepare_zip_from_base64( $input['zip_base64'] );
		}

		return self::error_response(
			'missing_zip',
			'Provide either zip_url or zip_base64.',
			'Use zip_url with a publicly accessible HTTPS URL to a .zip file, or zip_base64 with base64-encoded ZIP contents.'
		);
	}

	/**
	 * Download a ZIP file from a URL.
	 *
	 * @param string $url The URL to download.
	 * @return string|array Temp file path on success, or error response array on failure.
	 */
	private static function prepare_zip_from_url( string $url ) {
		$url = esc_url_raw( $url );

		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return self::error_response(
				'invalid_url',
				'The URL must use HTTPS.',
				'Provide a publicly accessible HTTPS URL.'
			);
		}

		if ( ! str_ends_with( strtolower( wp_parse_url( $url, PHP_URL_PATH ) ?? '' ), '.zip' ) ) {
			return self::error_response(
				'invalid_url',
				'The URL must point to a .zip file.',
				'Provide a direct URL ending in .zip.'
			);
		}

		$temp_path = download_url( $url, 300 );
		if ( is_wp_error( $temp_path ) ) {
			return self::error_response(
				'download_failed',
				'Failed to download the ZIP file: ' . $temp_path->get_error_message(),
				'Ensure the URL is publicly accessible and points to a valid .zip file.'
			);
		}

		return $temp_path;
	}

	/**
	 * Decode a base64-encoded ZIP file and write it to a temp file.
	 *
	 * @param string $base64 The base64-encoded ZIP data.
	 * @return string|array Temp file path on success, or error response array on failure.
	 */
	private static function prepare_zip_from_base64( string $base64 ) {
		$zip_data = base64_decode( $base64, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding a ZIP file, not obfuscated code.
		if ( false === $zip_data ) {
			return self::error_response(
				'invalid_base64',
				'The zip_base64 value is not valid base64.',
				'Ensure the ZIP file contents are properly base64-encoded with no corruption.'
			);
		}

		$temp_path = wp_tempnam( 'mcp-plugin-upload' );
		if ( ! $temp_path ) {
			return self::error_response(
				'temp_file_error',
				'Failed to create a temporary file for the upload.',
				'This is a server-side issue. Please try again later.'
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $temp_path, $zip_data );
		unset( $zip_data );

		if ( false === $written ) {
			wp_delete_file( $temp_path );
			return self::error_response(
				'temp_file_error',
				'Failed to write the ZIP data to a temporary file.',
				'This is a server-side issue. Please try again later.'
			);
		}

		return $temp_path;
	}

	/**
	 * Find a plugin post owned by the current user or accessible to a plugin reviewer.
	 *
	 * @param string $slug The plugin slug.
	 * @return \WP_Post|null
	 */
	private static function find_plugin_post( string $slug ): ?\WP_Post {
		$posts = get_posts(
			array(
				'post_type'   => 'plugin',
				'name'        => $slug,
				'post_status' => 'any',
				'numberposts' => 1,
			)
		);

		$post = $posts[0] ?? null;

		if ( ! $post ) {
			return null;
		}

		// Allow the plugin author or users with approve_plugins capability.
		if ( get_current_user_id() === (int) $post->post_author || current_user_can( 'approve_plugins' ) ) { // phpcs:ignore WordPress.WP.Capabilities.Unknown -- approve_plugins is registered by the plugin-directory plugin.
			return $post;
		}

		return null;
	}

	/**
	 * Build a structured error response.
	 *
	 * @param string $code       Machine-readable error code.
	 * @param string $message    Human-readable error message.
	 * @param string $next_steps Actionable guidance.
	 * @return array MCP tool result.
	 */
	private static function error_response( string $code, string $message, string $next_steps ): array {
		return array(
			'success'    => false,
			'error_code' => $code,
			'message'    => $message,
			'next_steps' => $next_steps,
		);
	}

	/**
	 * Map error codes from process_upload() to actionable next-step guidance.
	 *
	 * @param string $code The error code.
	 * @return string Guidance for the agent or developer.
	 */
	private static function get_next_steps_for_error( string $code ): string {
		$map = array(
			'submissions_paused'         => 'Please try again after the holiday break ends.',
			'2fa_required'               => 'Enable two-factor authentication on your account and try again.',
			'unsafe_email'               => 'Update your email address at your WordPress.org profile and try again.',
			'queue_limit'                => 'Use wporg://plugins/plugin-directory/get-plugin-status to check your existing submissions. You can upload new versions of plugins already in review by providing the plugin_slug parameter.',
			'error_upload'               => 'File upload failed. Verify the ZIP is valid and under the size limit.',
			'unexpected_files'           => 'Remove version control directories (.git, .svn, .hg, .bzr) and prohibited file types (.phar, .sh, .zip, .gz, .tar, .rar, .7z) from your plugin ZIP.',
			'no_name'                    => 'Add a "Plugin Name:" header to your main plugin file. See wporg://plugins/plugin-directory/plugin-headers for the required format.',
			'unsupported_name'           => 'Plugin names may only contain Latin letters (A-z), numbers, spaces, and hyphens. Change your Plugin Name header.',
			'reserved_name'              => 'This slug is reserved. Choose a different plugin name. See wporg://plugins/plugin-directory/reserved-slugs for the full list.',
			'trademarked_name'           => 'Your plugin name contains a trademarked term. Change the Plugin Name to avoid it. If you own the trademark, email plugins@wordpress.org.',
			'already_exists'             => 'A plugin with this name or slug already exists by another author. Change your Plugin Name header to something unique.',
			'already_submitted'          => 'You already submitted this plugin. To upload a new version, provide the plugin_slug parameter. For published plugins, update via SVN. Use wporg://plugins/plugin-directory/get-plugin-status to check.',
			'already_exists_in_the_wild' => 'This plugin name is already in use outside WordPress.org with significant installs. Choose a different name to avoid conflicts.',
			'no_description'             => 'Add a "Description:" header to your main plugin file. See wporg://plugins/plugin-directory/plugin-headers.',
			'no_version'                 => 'Add a "Version:" header to your main plugin file. See wporg://plugins/plugin-directory/plugin-headers.',
			'invalid_version'            => 'The Version header must contain only numbers and periods (e.g. "1.0.0"). Remove any non-numeric characters.',
			'plugin_author_uri'          => 'Your Plugin URI and Author URI are the same. These must be different values, or omit one of them.',
			'no_readme'                  => 'Add a readme.txt or readme.md file to your plugin. Use wporg://plugins/plugin-directory/validate-readme to verify it before resubmitting. See wporg://plugins/plugin-directory/readme-standard for the format.',
			'no_license'                 => 'Declare a GPL-compatible license in your readme.txt. Use wporg://plugins/plugin-directory/validate-readme to check. See wporg://plugins/plugin-directory/plugin-guidelines for license requirements.',
			'failed_checks'              => 'Your plugin failed automated security/quality checks. Fix the reported issues and resubmit. See wporg://plugins/plugin-directory/plugin-check-guide for how to test locally.',
			'already_uploaded'           => 'This exact ZIP file has already been uploaded. Make changes to your plugin files, create a new ZIP, and try again.',
		);

		return $map[ $code ] ?? 'Review the error message and correct the issue before resubmitting.';
	}
}
