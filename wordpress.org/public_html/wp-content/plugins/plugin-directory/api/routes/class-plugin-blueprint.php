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
		header( 'Access-Control-Allow-Origin: https://playground.wordpress.net' );

		// We already have a json string, returning would double-encode it.
		die( $blueprint['contents'] );
	}

	protected function get_zip_url_by_slug( $slug ) {
		$plugin = Plugin_Directory::get_plugin_post( $slug );
		if ( !$plugin ) {
			return false;
		}

		$zips = get_attached_media( 'application/zip', $plugin );
		// Return the first (is this correct?)
		if ( $zips & count( $zips ) ) {
			return wp_get_attachment_url( reset($zips)->ID );
		}
		return false;
	}

	function reviewer_blueprint( $request, $plugin ) {
		// Direct zip preview for plugin reviewers
		if ( $request->get_param('zip_hash') ) {
			foreach ( get_attached_media( 'application/zip', $plugin ) as $zip_file ) {
				if ( hash_equals( Template::preview_link_hash( $zip_file->ID, 0 ), $request->get_param('zip_hash') ) ||
				     hash_equals( Template::preview_link_hash( $zip_file->ID, -1 ), $request->get_param('zip_hash') ) ) {
					$zip_url = wp_get_attachment_url( $zip_file->ID );
					if ( $zip_url ) {

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
						if ( 'pcp' === $request->get_param('type') ) {
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
							'steps' => [
								(object)[
									'step' => 'installPlugin',
									'pluginZipFile' => (object)[
										'resource' => 'wordpress.org/plugins',
										'slug'     => 'plugin-check',
									]
								],
								(object)[
									'step' => 'installPlugin',
									'pluginZipFile' => (object)[
										'resource' => 'url',
										'url'      => $zip_url,
									],
									'options' => (object)[
										'activate' => (bool)$activate_plugin
									]
								],
								(object)[
									'step' => 'login',
									'username' => 'admin',
									'password' => 'password',
								]
							]
						];

						if ( $dependencies ) {
							$dep_step = [];
							foreach ( $dependencies as $slug ) {
								$dep_step[] = (object)[
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
							// Insert dependencies aftter PCP
							array_splice( $zip_blueprint->steps, 1, 0, $dep_step );
						}

						// Include the helper plugin too
						$helper_zip = self::get_zip_url_by_slug( 'playground-review-helper' );
						if ( $helper_zip && 'pcp' !== $request->get_param('type') ) {
							$helper_step = [
								(object)[
									'step' => 'installPlugin',
									'pluginZipFile' => [
										'resource' => 'url',
										'url'      => $helper_zip,
									],
									'options' => (object)[
										'activate' => (bool)$activate_plugin
									]
								]
							];
							array_splice( $zip_blueprint->steps, 1, 0, $helper_step );
						}

						$output = json_encode( $zip_blueprint );

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

}
