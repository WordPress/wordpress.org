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
		) );

		register_rest_route( 'themes/v1', 'preview-blueprint/(?<slug>[^/]+)', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'set_blueprint' ),
			'permission_callback' => function( $request ) {
				$theme_data    = wporg_themes_theme_information( $request['slug'] );
				$theme_package = new WPORG_Themes_Repo_Package( $theme_data->slug );

				if ( ! empty( $theme_data->error ) || ! $theme_package->post_author ) {
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
	 * Set a Blueprint for a theme preview.
	 */
	function set_blueprint( $request ) {
		$theme_data    = wporg_themes_theme_information( $request['slug'] );
		$theme_package = new WPORG_Themes_Repo_Package( $theme_data->slug );

		if ( ! empty( $theme_data->error ) ) {
			return new WP_Error( 'error', $theme_data->error );
		}

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
	 */
	function build_blueprint( $theme_data ) {
		$theme_package   = new WPORG_Themes_Repo_Package( $theme_data->slug );
		$parent_package  = $theme_data->template ? new WPORG_Themes_Repo_Package( $theme_data->template ) : false;
		$theme_blueprint = $theme_package->preview_blueprint;

		// Base blueprint.
		$blueprint = [
			'preferredVersions' => [
				'php' => '8.0',
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
					'blogname'        => $theme_data->name,
					'blogdescription' => preg_replace( '![.].+$!',  '.', $theme_data->sections['description'] ), // First sentence only.
				]
			],
			// Note: The following use `url` to allow setting the caption to the proper theme name.
			// Install parent theme.
			empty( $theme_data->template ) ? false : [
				'step'         => 'installTheme',
				'themeZipFile' => [
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
				'themeZipFile' => [
					'resource' => 'url',
					'url'      => $theme_package->download_url(),
					'caption'  => "Downloading {$theme_package->post_title}",
				]
			]
		];

		// Filter out any theme provided steps we don't want.
		$theme_steps = array_filter(
			$theme_steps,
			static function( $step ) use( $invalid_steps ) {
				// Don't install assets from URLs.
				if (
					! empty( $step['themeZipFile']['url'] ) ||
					! empty( $step['pluginZipFile']['url'] )
				) {
					return false;
				}

				// Don't need to install the theme again.
				if ( 'installTheme' === $step['step'] ) {
					if ( $theme_data->slug === ( $step['themeZipFile']['slug'] ?? '' ) ) {
						return false;
					}
					if ( ! empty( $theme_data->template ) && $theme_data->template === ( $step['themeZipFile']['slug'] ?? '' ) ) {
						return false;
					}
				}

				return true;
			}
		);

		// Fill in a theme starter content step if specified.
		// See: `installThemeStarterContent`. https://github.com/WordPress/wordpress-playground/pull/1521
		foreach ( $theme_steps as $i => $step ) {
			if ( 'installThemeStarterContent' === $step['step'] ) {
				$theme_steps[ $i ] = $this->get_install_starter_content_step();
			}
		}

		// If the theme didn't provide a blueprint, we'll also install the Starter Content. This must be done last.
		// See also: `installThemeStarterContent`. https://github.com/WordPress/wordpress-playground/pull/1521
		if ( ! $theme_blueprint ) {
			$final_steps[] = $this->get_install_starter_content_step();
		}

		/*
		 * TODO: These artifacts need to be hosted somewhere better. 
		 */
		$final_steps[] = [
			'step' => 'installPlugin',
			'pluginZipFile' => [
				'resource' => 'url',
				'url'      => 'https://raw.githubusercontent.com/WordPress/wordpress.org/trunk/wp-themes.com/public_html/wp-content/plugins/pattern-page.zip',
			],
		];
		$final_steps[] = [
			'step' => 'installPlugin',
			'pluginZipFile' => [
				'resource' => 'url',
				'url'      => 'https://raw.githubusercontent.com/WordPress/wordpress.org/trunk/wp-themes.com/public_html/wp-content/plugins/style-variations.zip',
			],
		];

		// Set the steps.
		$blueprint['steps'] = array_merge(
			array_filter( $required_steps ),
			array_values( $theme_steps ),
			$final_steps
		);

		return $blueprint;
	}

	/**
	 * Returns a formed step to install the starter content.
	 */
	function get_install_starter_content_step() {
		return [
			'step' => 'runPHP',
			'code' => '<?php
				playground_add_filter( "plugins_loaded", "importThemeStarterContent_plugins_loaded", 0 );
				function importThemeStarterContent_plugins_loaded() {
					/* Set as the admin user, this ensures we can customize the site. */
					wp_set_current_user(
						get_users( [ "role" => "Administrator" ] )[0]
					);

					/*
					 * Simulate this request as a ajax customizer save, with the current theme in preview mode.
					 *
					 * See _wp_customize_include()
					 */
					add_filter( "wp_doing_ajax", "__return_true" );
					$_REQUEST["action"]          = "customize_save";
					$_REQUEST["wp_customize"]    = "on";
					$_REQUEST["customize_theme"] = get_stylesheet();
					$_GET                        = $_REQUEST;

					/* Force the site to be fresh, although it should already be, some themes require this. */
					add_filter( "pre_option_fresh_site", "__return_true" );
				}

				require "/wordpress/wp-load.php";

				if ( ! get_theme_starter_content() ) {
					return;
				}

				/* Import the Starter Content. */
				$wp_customize->import_theme_starter_content();

				/* Publish the changeset, which publishes the starter content. */
				wp_publish_post( $wp_customize->changeset_post_id() );
			'
		];
	}
}
new Theme_Preview();
