<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * An API endpoint for fetching a plugin blueprint file.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Blueprint extends Base {

	public function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/blueprint.json', array(
			'methods'             => array( \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ),
			'callback'            => array( $this, 'blueprint' ),
			// Note: the zip part of the endpoint is also public, since playground requests blueprints without cookie credentials
			'permission_callback' => '__return_true',
			'args'                => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
			)
		) );
	}

	/**
	 * Endpoint to output a blueprint file contents.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public function blueprint( $request ) {
		$plugin = Plugin_Directory::get_plugin_post( $request['plugin_slug'] );

		if ( $request->get_param('zip_hash') ) {
			$this->reviewer_blueprint( $request, $plugin );
		}
		if ( $request->get_param('url_hash') ) {
			$this->developer_blueprint( $request, $plugin );
		}

        $blueprints = get_post_meta( $plugin->ID, 'assets_blueprints', true );
        // Note: for now, only use a file called `blueprint.json`.
		if ( !isset( $blueprints['blueprint.json'] ) ) {
			return new \WP_Error( 'no_blueprint', 'File not found', array( 'status' => 404 ) );
        }
        $blueprint = $blueprints['blueprint.json'];
        if ( !$blueprint || !isset( $blueprint['contents'] ) || !is_string( $blueprint['contents'] ) ) {
			return new \WP_Error( 'invalid_blueprint', 'Invalid file', array( 'status' => 500 ) );
        }

		// Configure this elsewhere?
		header( 'Access-Control-Allow-Origin: *' );

		// We already have a json string, returning would double-encode it.
		die( $blueprint['contents'] );
	}

	protected function get_zip_url_by_slug( $slug ) {
		$plugin = Plugin_Directory::get_plugin_post( $slug );
		if ( !$plugin ) {
			return false;
		}

		$zips = get_attached_media( 'application/zip', $plugin );
		// Return the last zip (the most recent?)
		if ( $zips && count( $zips ) ) {
			return wp_get_attachment_url( end($zips)->ID );
		}
		return false;
	}

	function reviewer_blueprint( $request, $plugin ) {
		// Direct zip preview for plugin reviewers
		if ( $request->get_param('zip_hash') ) {
			foreach ( get_attached_media( 'application/zip', $plugin ) as $zip_file ) {
				$zip_file_path = get_attached_file( $zip_file->ID );
				if ( hash_equals( Template::preview_link_hash( $zip_file_path, 0 ), $request->get_param('zip_hash') ) ||
				     hash_equals( Template::preview_link_hash( $zip_file_path, -1 ), $request->get_param('zip_hash') ) ) {
					$zip_url = wp_get_attachment_url( $zip_file->ID );
					if ( $zip_url ) {
						$is_pcp = 'pcp' === $request->get_param('type');
						$output = $this->generate_blueprint( $request, $plugin, $zip_url, $is_pcp, true );

						if ( $output ) {
							header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );
							die( $output );
						}
					}
				}
			}
		}

		return new \WP_Error( 'invalid_blueprint', 'Invalid file', array( 'status' => 500 ) );
	}

	function developer_blueprint( $request, $plugin ) {
		// Generated blueprint for developers who haven't yet created a custom blueprint
		if ( $request->get_param('url_hash') ) {
			$download_link = Template::download_link( $plugin );
			if ( $download_link ) {
				if ( hash_equals( Template::preview_link_hash( $download_link, 0 ), $request->get_param('url_hash') ) ||
					hash_equals( Template::preview_link_hash( $download_link, -1 ), $request->get_param('url_hash') ) ) {
					$output = $this->generate_blueprint( $request, $plugin, $download_link, false, false );

					if ( $output ) {
						header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );
						die( $output );
					}
				}
			}
		}
	}

	public function generate_blueprint( $request, $plugin, $zip_url, $install_pcp = true, $install_prh = true ) {
		$landing_page = '/wp-admin/plugins.php';
		$activate_plugin = true;
		$dependencies = $plugin->requires_plugins ?: [];

		if ( stripos( $plugin->post_title, 'woocommerce' ) ) {
			$dependencies[] = 'woocommerce';
		}
		if ( stripos( $plugin->post_title, 'buddypress' ) ) {
			$dependencies[] = 'buddypress';
		}

		$dependencies = array_diff( $dependencies, [ $plugin->post_name ] );

		// Plugin deactivated, and land on the Plugin Check page
		if ( $install_pcp ) {
			$landing_page = '/wp-admin/admin.php?page=plugin-check&plugin=' . sanitize_title( $request['plugin_slug'] );
			$activate_plugin = false;
			$dependencies = [];
		}

		$zip_blueprint = (object)[
			'landingPage' => $landing_page,
			'preferredVersions' => (object)[
				'php' => '8.0',
				'wp'  => 'latest',
			],
			'phpExtensionBundles' => [
				'kitchen-sink'
			],
			'features' => (object)[
				'networking' => true
			],
		];

		$steps = [];

		// PCP first, if needed.
		if ( $install_pcp ) {
			$steps[] = (object)[
				'step' => 'installPlugin',
				'pluginZipFile' => (object)[
					'resource' => 'wordpress.org/plugins',
					'slug'     => 'plugin-check',
				]
			];
		}

		// Include the helper plugin too
		$helper_zip = self::get_zip_url_by_slug( 'playground-review-helper' );
		if ( $helper_zip && $install_prh ) {
			$steps[] = (object)[
					'step' => 'installPlugin',
					'pluginZipFile' => [
						'resource' => 'url',
						'url'      => $helper_zip,
					],
					'options' => (object)[
						'activate' => (bool)$activate_plugin
					]
				];
		}

		// Dependencies next
		if ( $dependencies ) {
			foreach ( $dependencies as $slug ) {
				$steps[] = (object)[
					'step' => 'installPlugin',
					'pluginZipFile' => [
						'resource' => 'wordpress.org/plugins',
						'slug'     => sanitize_title( $slug ),
					],
					'options' => (object)[
						'activate' => true
					]
				];
			}
		}

		// Now the plugin itself
		$steps[] = (object)[
					'step' => 'installPlugin',
					'pluginZipFile' => (object)[
						'resource' => 'url',
						'url'      => $zip_url,
					],
					'options' => (object)[
						'activate' => (bool)$activate_plugin
					]
				];

		// Finally log in
		$steps[] = (object)[
					'step' => 'login',
					'username' => 'admin',
					'password' => 'password',
				];

		$zip_blueprint->steps = $steps;

		$output = json_encode( $zip_blueprint, JSON_PRETTY_PRINT );

		return $output;
	}

}
