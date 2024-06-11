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
		$repo_package = WPORG_Themes_Repo_Package::get_by_slug( $theme_data->slug );

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
			$theme_blueprint = json_decode( json_encode( $theme_blueprint ), true ); // TEMP convert object to array.
			$blueprint = array_merge( $blueprint, $theme_blueprint );
		}

		$steps_present = array_unique( wp_list_pluck( $blueprint['steps'], 'step' ) );

		// The steps we'll prepend to the theme-provided list.
		$missing_steps = [];

		// Must run the latest version of WordPress.
		if ( 'latest' !== $blueprint['preferredVersions']['wp'] ?? '' ) {
			$blueprint['preferredVersions']['wp'] = 'latest';
		}

		// Make sure the landing page isn't an admin page.
		if ( str_contains( $blueprint['landingPage'] ?? '', 'wp-admin/' ) ) {
			unset( $blueprint['landingPage'] );
		}

		// Must log the user in.
		if ( ! in_array( 'login', $steps_present ) ) {
			$missing_steps[] = [
				'step'     => 'login',
				'username' => 'admin',
				'password' => 'password'
			];
		}

		// Set the default site name & description.
		if ( ! in_array( 'setSiteOptions', $steps_present ) ) {
			// Default the site name & description to that of the theme's.
			$description = $theme_data->sections['description'];
			// Trim it to the first sentence.
			if ( $pos = strpos( $description, '.' ) ) {
				$description = substr( $description, 0, $pos + 1 );
			}

			$missing_steps[] = [
				'step'    => 'setSiteOptions',
				'options' => [
					// Technically, we should check any existing setSiteOptions is setting these, but this will do for now.
					'blogname'        => $theme_data->name,
					'blogdescription' => $description,
				]
			];
		}

		// Make sure the right themes are installed.
		$valid_slugs      = array_filter( [ $theme_data->template ?? false, $theme_data->slug ] ); // Order is important, parent must be second, to ensure it's installed first.
		$installed_themes = [];
		foreach ( wp_list_filter( $blueprint['steps'], [ 'step' => 'installTheme' ] ) as $i => $step ) {
			if (
				// You may only install WordPress.org themes.
				( 'wordpress.org/themes' != $step['themeZipFile']['resource'] ?? '' ) ||
				// Must install by slug, not URL.
				( ! empty( $step['themeZipFile']['url'] ) ) ||
				// Must not install unexpected themes.
				( ! in_array( $step['themeZipFile']['slug'] ?? '', $valid_slugs ) )
			) {
				unset( $blueprint['steps'][ $i ] );
				continue;
			}

			$installed_themes[] = $step['themeZipFile']['slug'];
		}

		// Make sure the theme & it's parent are installed.
		foreach ( $valid_slugs as $slug ) {
			if ( ! in_array( $slug, $installed_themes ) ) {
				// Prepend install.
				$missing_steps[] = [
					'step'         => 'installTheme',
					'themeZipFile' => [
						'resource' => 'wordpress.org/themes',
						'slug' => $slug
					],
				];
			}
		}

		// Run our steps first.
		$blueprint['steps'] = array_merge(
			$missing_steps,
			array_values( $blueprint['steps'] ?? [] ),
		);

		// If the theme didn't provide a blueprint, we'll also install the Starter Content. This must be done last.
		if ( ! $theme_provided_blueprint ) {
			$blueprint['steps'][] = [
				'step' => 'runPHP',
				'code' => $this->get_starter_content_loader(),
			];
		}

		// We need to load our custom plugins too.
		$blueprint['steps'][] = [
			'step' => 'runPHP',
			// TODO: The pattern-preview plugin needs post ID 256 to exist, this needs to be created dynamically by the plugin upon load I guess.
			'code' =>  "<?php require '/wordpress/wp-load.php'; wp_insert_post( [ 'import_id' => 256, 'post_title' => 'Pattern Preview', 'post_type' => 'page', 'post_status' => 'draft' ] );"
		];
		$blueprint['steps'][] = [
			'step' => 'installPlugin',
			'pluginZipFile' => [
				'resource' => 'url',
				'url'      => 'https://raw.githubusercontent.com/dd32/wporg-theme-directory/plugins/pattern-page.zip',
			],
		];
		$blueprint['steps'][] = [
			'step' => 'installPlugin',
			'pluginZipFile' => [
				'resource' => 'url',
				'url'      => 'https://raw.githubusercontent.com/dd32/wporg-theme-directory/plugins/style-variations.zip',
			],
		];

		return $blueprint;
	}

	/**
	 * Get the PHP code to load the Starter Content.
	 */
	function get_starter_content_loader() {
		$plugin = file_get_contents( dirname( __DIR__ ) . '/bin/theme-preview-load-starter-content.php' );

		// Slim the plugin down.
		$plugin = preg_replace( '!\s*//.+?$!m', '', $plugin );
		$plugin = preg_replace( '!/[*].+?[*]/!s', '', $plugin );
		$plugin = preg_replace( '!\s+!', ' ', $plugin );

		return $plugin;
	}
}
new Theme_Preview();
