<?php
namespace WordPressdotorg\Theme_Directory\Rest_API;
use WP_REST_Server;
use WP_Error;
use WPORG_Themes_Repo_Package;

class Theme_Preview {

	function __construct() {
		register_rest_route( 'themes/v1', 'preview-blueprint/(?<slug>[^/]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'preview' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				'slug' => array(
					'type'              => 'string',
					'required'          => 'true',
					'sanitize_callback' => 'sanitize_key',
				),
			),
		) );

		register_rest_route( 'themes/v1', 'review-blueprint/((?<post_id>[\d]+)-)?(?<slug>[^/]+)/(?<version>[0-9a-z.-_]+)?', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'review' ),
			'permission_callback' => '__return_true',
			'args'                => array(
				// If the theme isn't (yet) published, this can be set to force loading the post.
				'post_id' => array(
					'type'              => 'integer',
					'required'          => false,
					'sanitize_callback' => 'absint',
				),
				'slug' => array(
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_key',
				),
				'version' => array(
					'type'     => 'string',
					'required' => false,
				),
			),
		) );

		register_rest_route( 'themes/v1', 'preview-blueprint/(?<slug>[^/]+)', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'set_blueprint' ),
			'args'                => array(
				'slug' => array(
					'type'              => 'string',
					'required'          => 'true',
					'sanitize_callback' => 'sanitize_key',
				),
			),
			'permission_callback' => function( $request ) {
				$theme_data = wporg_themes_theme_information( $request['slug'] );

				if ( ! empty( $theme_data->error ) ) {
					return false;
				}

				$theme_package = new WPORG_Themes_Repo_Package( $theme_data->slug );

				if ( ! $theme_package->post_author ) {
					return false;
				}

				return (
					get_current_user_id() === $theme_package->post_author ||
					current_user_can( 'edit_post', $theme_package->ID )
				);
			},
		) );
	}

	/**
	 * Generate a Blueprint for a theme preview.
	 */
	function preview( $request ) {
		$theme_data = wporg_themes_theme_information( $request->get_param( 'slug' ) );

		if ( ! empty( $theme_data->error ) ) {
			return new WP_Error( 'error', $theme_data->error );
		}

		return $this->build_blueprint( $theme_data );
	}

	/**
	 * Generate a Blueprint for a theme review.
	 */
	function review( $request ) {
		/*
		 * If the theme isn't (yet) published, use the post_id hint.
		 * Security Note:
		 *   As theme reviews are public (themes.trac.wordpress.org) we don't validate permissions here.
		 */
		$preview_post = get_post( (int) $request->get_param( 'post_id' ) );
		if (
			$preview_post &&
			'repopackage' === $preview_post->post_type &&
			'publish' !== $preview_post->post_status &&
			$preview_post->post_name === $request->get_param( 'slug' )
		) {
			$GLOBALS['post'] = $preview_post;
		}

		$theme_data = wporg_themes_theme_information( $request->get_param( 'slug' ) );

		if ( ! empty( $theme_data->error ) ) {
			return new WP_Error( 'error', $theme_data->error );
		}

		return $this->build_blueprint(
			$theme_data,
			[
				'version' => $request->get_param( 'version' ),
				'review'  => true,
			]
		);
	}

	/**
	 * Set a Blueprint for a theme preview.
	 */
	function set_blueprint( $request ) {
		$theme_data = wporg_themes_theme_information( $request['slug'] );

		if ( ! empty( $theme_data->error ) ) {
			return new WP_Error( 'error', $theme_data->error );
		}

		$theme_package = new WPORG_Themes_Repo_Package( $theme_data->slug );

		// Validate the blueprint, TODO expand upon this.
		if (
			empty( $request['blueprint'] ) ||
			! is_string( $request['blueprint'] ) ||
			! ( $decoded_blueprint = json_decode( $request['blueprint'], true) ) ||
			empty( $decoded_blueprint['steps'] )
		) {
			return new WP_Error( 'error', 'Invalid Blueprint provided, verify the JSON validates.' );
		}

		// Save the blueprint.
		update_post_meta( $theme_package->ID, 'preview_blueprint', $decoded_blueprint );

		return $this->build_blueprint( $theme_data );
	}

	/**
	 * Generate a blueprint for previewing a theme.
	 *
	 * @param object $theme_data Theme data as returned by wporg_themes_theme_information
	 * @param array $params {
	 * 		Optional parameters.
	 * 		@type bool $review If true, generate a blueprint for theme review.
	 * 		@type string $version Optional. The version string, empty for latest version.
	 * }
	 * @return array The blueprint.
	 */
	function build_blueprint( $theme_data, $params = array() ) {
		$is_theme_review = $params['review'] ?? false;
		$version         = $params['version'] ?? false;
		$theme_package   = new WPORG_Themes_Repo_Package( $theme_data->slug, $version );
		$theme_blueprint = $theme_package->preview_blueprint;
		$parent_package  = false;
		if ( isset( $theme_data->template ) && $theme_data->template !== $theme_data->slug ) {
			$parent_package = new WPORG_Themes_Repo_Package( $theme_data->template );
		}

		if ( empty( $theme_package->wp_post ) ) {
			return new WP_Error( 'not_found', 'Theme not found.' );
		}

		// Base blueprint.
		$blueprint = [
			'preferredVersions' => [
				'php' => defined( 'RECOMMENDED_PHP' ) ? RECOMMENDED_PHP : substr( phpversion(), 0, 3 ),
				'wp'  => 'latest'
			],
			// Sadly many themes require the full kitchen-sink.
			'phpExtensionBundles' => [
				'kitchen-sink'
			],
			// Networking is useful for testing a theme, to install extra plugins.
			'features' => [
				'networking' => true
			],
			// Do not specify steps here, see below for adding steps.
			'steps' => []
		];

		// Merge in the theme's blueprint.
		if ( $theme_blueprint ) {
			$blueprint = array_merge( $blueprint, $theme_blueprint );
		}

		// The various steps will be in these variables, and added at the end.
		$requred_steps = [];
		$theme_steps   = $blueprint['steps'] ?? [];
		$final_steps   = [];

		// Must run the latest version of WordPress.
		if ( 'latest' !== $blueprint['preferredVersions']['wp'] ?? '' ) {
			$blueprint['preferredVersions']['wp'] = 'latest';
		}

		// Make sure the landing page isn't an admin page.
		if ( str_contains( $blueprint['landingPage'] ?? '', 'wp-admin/' ) ) {
			unset( $blueprint['landingPage'] );
		}

		// These steps will always run for the previews.
		$required_steps = [
			// Remove any existing themes.
			[
				'step' => 'rmdir',
				'path' => '/wordpress/wp-content/themes/'
			],
			[
				'step' => 'mkdir',
				'path' => '/wordpress/wp-content/themes/'
			],
			// Login as admin.
			[
				'step'     => 'login',
				'username' => 'admin',
				'password' => 'password'
			],
			// Set the default site details. The theme blueprint may replace this.
			[
				'step'    => 'setSiteOptions',
				'options' => [
					'blogname'        => $theme_data->name . ( $version ? " $version" : '' ),
					'blogdescription' => preg_replace( '![.].+$!',  '.', $theme_data->sections['description'] ?? '' ), // First sentence only.
				]
			],
			// Special case: BuddyPress.
			! ( $theme_data->tags && in_array( 'buddypress', $theme_data->tags, true ) ) ? false : [
				'step' => 'installPlugin',
				'pluginData' => [
					'resource' => 'wordpress.org/plugins',
					'slug'     => 'buddypress',
				],
				'options' => [
					'activate' => true
				]
			],
			// Note: The following use `url` to allow setting the caption to the proper theme name.
			// Install parent theme.
			! $parent_package ? false : [
				'step'         => 'installTheme',
				'themeData' => [
					'resource' => 'url',
					'url'      => $parent_package->download_url(),
					'caption'  => "Downloading {$parent_package->post_title}",
				],
				'options' => [
					'activate' => false
				]
			],
			// Install the theme.
			[
				'step'         => 'installTheme',
				'themeData' => [
					'resource' => 'url',
					'url'      => $theme_package->download_url(),
					'caption'  => "Downloading {$theme_package->post_title}" . ( $version ? " $version" : '' ),
				],
				'options' => [
					// Import the Starter Content if the theme didn't provide a blueprint.
					'importStarterContent' => ( ! $theme_blueprint ),
				]
			]
		];

		// Filter out any theme provided steps we don't want.
		$theme_steps = array_filter(
			$theme_steps,
			static function( $step ) {
				// Don't install assets from URLs.
				if (
					! empty( $step['themeZipFile']['url'] ) ||
					! empty( $step['themeData']['url'] ) ||
					! empty( $step['pluginZipFile']['url'] ) ||
					! empty( $step['pluginData']['url'] )
				) {
					return false;
				}

				// Don't need to install the theme again.
				if ( 'installTheme' === $step['step'] ) {
					if (
						$theme_data->slug === ( $step['themeZipFile']['slug'] ?? '' ) ||
						$theme_data->slug === ( $step['themeData']['slug'] ?? '' )
					) {
						return false;
					}
					if (
						! empty( $theme_data->template ) &&
						(
							$theme_data->template === ( $step['themeZipFile']['slug'] ?? '' ) ||
							$theme_data->template === ( $step['themeData']['slug'] ?? '' )
						)
					) {
						return false;
					}
				}

				return true;
			}
		);

		// Install the Theme Previewer plugins.
		if ( ! $is_theme_review ) {
			/*
			 * TODO: These artifacts need to be hosted somewhere better.
			 */

			$final_steps[] = [
				'step' => 'installPlugin',
				'pluginData' => [
					'resource' => 'url',
					'url'      => 'https://raw.githubusercontent.com/WordPress/wordpress.org/trunk/wp-themes.com/public_html/wp-content/plugins/pattern-page.zip',
				],
			];
			$final_steps[] = [
				'step' => 'installPlugin',
				'pluginData' => [
					'resource' => 'url',
					'url'      => 'https://raw.githubusercontent.com/WordPress/wordpress.org/trunk/wp-themes.com/public_html/wp-content/plugins/style-variations.zip',
				],
			];
		}

		// Theme Review specifics.
		if ( $is_theme_review ) {
			// Enable Debug mode.
			$final_steps[] = [
				'step' => 'defineWpConfigConsts',
				'consts' => [
					'WP_DEBUG' => true
				]
			];

			// Install Theme Check.
			$final_steps[] = [
				'step' => 'installPlugin',
				'pluginData' => [
					'resource' => 'wordpress.org/plugins',
					'slug'     => 'theme-check',
				],
				'options' => [
					'activate' => true
				]
			];

			// Install the test content.
			$final_steps[] = [
				'step' => 'importWxr',
				'file' => [
					'resource' => 'url',
					'url'      => 'https://raw.githubusercontent.com/WordPress/theme-test-data/master/themeunittestdata.wordpress.xml',
					'caption'  => 'Downloading theme testing content'
				]
			];
		}

		// Set the steps.
		$blueprint['steps'] = array_merge(
			array_filter( $required_steps ),
			array_values( $theme_steps ),
			$final_steps
		);

		return $blueprint;
	}
}
new Theme_Preview();
