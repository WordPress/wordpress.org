<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;
use WordPressdotorg\Plugin_Directory\ZIP;
use WP_REST_Server;
use Exception;

/**
 * An internal API endpoint used to manage the plugin ZIP files on the server.
 *
 * Each WordPress.org server manages it's own set of plugin ZIP files, this API
 * endpoint allows a script to remotely perform commands on plugin zip files.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Zip_Management extends Base {

	const ZIP_DIR = Zip\Serve::ZIP_DIR;

	function __construct() {
		register_rest_route( 'plugins/v1', '/zip-management', array(
			'methods'  => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'callback' ),
			'permission_callback' => array( $this, 'permission_check_internal_api_bearer' ),
		) );
	}

	/**
	 * Endpoint to manage plugin zip files.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	function callback( $request ) {

		$results = array();

		foreach ( $request['plugins'] as $plugin_slug => $actions ) {
			$plugin_slug = preg_replace( '![^a-z0-9%+-]!i', '', $plugin_slug );

			$to_invalidate = $to_rebuild = array();

			if ( !empty( $actions['invalidate'] ) ) {
				if ( 'all' == $actions['invalidate'] ) {
					$to_invalidate = $this->get_plugin_zips( $plugin_slug );
				} elseif ( is_array( $actions['invalidate'] ) ) {
					$to_invalidate = $actions['invalidate'];
				}
			}

			if ( !empty( $actions['rebuild'] ) ) {
				if ( 'all' == $actions['rebuild'] ) {
					$to_rebuild = $this->get_plugin_zips( $plugin_slug );
				} elseif ( is_array( $actions['rebuild'] ) ) {
					$to_rebuild = $actions['rebuild'];
				}

				foreach ( $to_rebuild as $zip ) {
					if ( false !== ( $pos = array_search( $zip, $to_invalidate ) ) ) {
						unset( $to_invalidate[ $pos ] );
					}
				}
			}

			foreach ( $to_rebuild as $zip ) {
				try {
					$this->rebuild( $plugin_slug, $zip );
					$results['rebuild'][ $zip ] = file_get_contents( self::ZIP_DIR . '/' . $plugin_slug . '/' . $zip . '.md5' );
				} catch( Exception $e ) {
					$results['rebuild'][ $zip ] = array(
						false,
						$e->getMessage(),
					);
				}
			}

			foreach ( $to_invalidate as $zip ) {
				$results['invalidate'][ $zip ] = $this->invalidate( $plugin_slug, $zip );
			}
		}

		return $results;
	}

	/**
	 * Get a listing of ZIP files for a given plugin slug.
	 *
	 * @param string $plugin_slug The plugin slug to search for.
	 * @return array The list of ZIP files for the specified plugin slug.
	 */
	protected function get_plugin_zips( $plugin_slug ) {
		$files = glob( self::ZIP_DIR . '/' . $plugin_slug . '/*.zip' );
		$files = array_map( 'basename', $files );

		return $files;
	}

	/**
	 * Invalidates a specified ZIP file for a given plugin.
	 *
	 * @param string $plugin_slug The slug of the plugin to act upon.
	 * @param string $zip         The name of the zip file to act upon.
	 * @return bool Whether the operation suceeded.
	 */
	protected function invalidate( $plugin_slug, $zip ) {
		$file = self::ZIP_DIR . '/' . $plugin_slug . '/' . $zip;

		if ( file_exists( $file ) ) {
			if ( ! unlink( $file ) ) {
				return false;
			}

			if ( file_exists( "{$file}.md5" ) ) {
				if ( ! unlink( "{$file}.md5" ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Rebuilds a specified plugin zip.
	 *
	 * @param string $plugin_slug The slug of the plugin to act upon.
	 * @param string $zip         The name of the zip file to rebuild.
	 * @return bool Whether the operation suceeded. Exceptions thrown on failure.
	 */
	public function rebuild( $slug, $zip ) {
		$version = preg_replace( '!^' . preg_quote( $slug, '!' ) . '(?:\.(.+))?\.zip$!i', '$1', $zip );
		if ( $version == $slug ) {
			throw new Exception( __METHOD__ . ': Invalid ZIP file format' );
		}
		if ( '' == $version ) {
			$version = 'trunk';
		}

		$builder = new Zip\Builder( $slug, $version );
		$builder->build();

		clearstatcache();

		return true;
	}

}
