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
	}

	/**
	 * Generate a Blueprint for a theme preview.
	 */
	function preview( $request ) {
		$theme_data = wporg_themes_query_api(
			'theme_information',
			[ 'slug' => $request->get_param( 'slug' ) ]
		);

		if ( ! empty( $theme_data->error ) ) {
			return new WP_Error( 'error', $theme_data->error );
		}

		return $this->build_blueprint( $theme_data );
	}

	/**
	 * Generate a blueprint for previewing a theme.
	 */
	function build_blueprint( $theme_data ) {
		$repo_package = new WPORG_Themes_Repo_Package( $theme_data->slug );

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
		$theme_blueprint          = $repo_package->blueprint;
		$theme_provided_blueprint = (bool) $theme_blueprint;
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
			// Install parent theme.
			empty( $theme_data->template ) ? false : [
				'step'         => 'installTheme',
				'themeZipFile' => [
					'resource' => 'wordpress.org/themes',
					'slug' => $theme_data->template
				],
				'options' => [
					'activate' => false
				]
			],
			// Install the theme.
			[
				'step'         => 'installTheme',
				'themeZipFile' => [
					'resource' => 'wordpress.org/themes',
					'slug' => $theme_data->slug
				]
			]
		];

		// Filter out any theme provided steps we don't need.
		$invalid_steps            = [ 'login', 'installThemeStarterContent' ];
		// Take note of whether the theme wants starter content loaded.
		$requests_starter_content = (bool) wp_list_filter( $theme_steps, [ 'step' => 'installThemeStarterContent' ] );
		$theme_steps              = array_filter(
			$theme_steps,
			static function( $step ) use( $invalid_steps ) {
				if ( in_array( $step['step'], $invalid_steps ) ) {
					return false;
				}

				// Don't install assets from URLs.
				if (
					! empty( $step['themeZipFile']['url'] ) ||
					! empty( $step['pluginZipFile']['url'] )
				) {
					return false;
				}

				return true;
			}
		);

		// If the theme didn't provide a blueprint, we'll also install the Starter Content. This must be done last.
		// See also: `installThemeStarterContent`. https://github.com/WordPress/wordpress-playground/pull/1521
		if ( ! $theme_provided_blueprint || $requests_starter_content ) {
			$final_steps[] = [
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

		$final_steps[] = [
			'step' => 'installPlugin',
			'pluginZipFile' => [
				'resource' => 'url',
				'url'      => 'https://raw.githubusercontent.com/WordPress/wordpress.org/add/wp-themes.com/plugins-as-zips/wp-themes.com/public_html/wp-content/plugins/pattern-page.zip',
			],
		];

		$final_steps[] = [
			'step' => 'installPlugin',
			'pluginZipFile' => [
				'resource' => 'url',
				'url'      => 'https://raw.githubusercontent.com/WordPress/wordpress.org/add/wp-themes.com/plugins-as-zips/wp-themes.com/public_html/wp-content/plugins/style-variations.zip',
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
}
new Theme_Preview();
