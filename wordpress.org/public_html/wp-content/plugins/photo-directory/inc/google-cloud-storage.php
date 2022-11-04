<?php
/**
 * Google Cloud Storage handling for photo file storage.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

require_once WPORG_PHOTO_DIRECTORY_DIRECTORY . '/vendor/autoload.php';

// Import Google libraries.
use Google\Cloud\Storage\StorageClient;
use Google\Cloud\Storage\StreamWrapper;
use Google\Cloud\Storage\WPORG_StreamWrapper;
use Google\Auth\HttpHandler\HttpHandlerFactory;

class GoogleCloudStorage {

	private static $gs_client;

	/**
	 * Memoized cloud buckent name.
	 *
	 * @access private
	 * @var string
	 */
	private static $cloud_bucket_name = '';

	/**
	 * Memoized cloud project ID.
	 *
	 * @access private
	 * @var string
	 */
	private static $cloud_project_id = '';

	/**
	 * Memoized cloud domain name.
	 *
	 * @access private
	 * @var string
	 */
	private static $cloud_domain_name = '';

	/**
	 * Initializes component.
	 */
	public static function init() {
		// Check if Google Cloud Storage usage is enabled.
		if ( ! self::is_google_cloud_enabled() ) {
			return;
		}

		// Initialize Google Cloud Storage client.
		$gs_client = self::init_gs_client();

		if ( is_wp_error( $gs_client ) ) {
			return;
		}

		self::$gs_client = $gs_client;
		// The default way of registering a stream wrapper...
		//self::$gs_client->registerStreamWrapper();
		// But instead, register an extended version of the StreamWrapper that
		// handles `stream_metadata()` to avoid PHP warnings.
		WPORG_StreamWrapper::register( self::$gs_client );

		add_filter( 'upload_dir',                      [ __CLASS__, 'filter_upload_dir' ] );
		add_filter( 'wp_get_attachment_url',           [ __CLASS__, 'filter_wp_get_attachment_url' ], 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'append_attachment_metadata' ], 10, 3 );
	}

	/**
	 * Returns the path to the Google Cloud Storage authorized credentials JSON file.
	 *
	 * Must be configured via 'wporg_photos_google_storage_credentials_json_path' filter.
	 *
	 * Note: Does not verify if filtered value references an actual file.
	 *
	 * @return string The path to the credentials JSON file.
	 */
	public static function get_cloud_credentials_path() {
		/**
		 * Filters the name of the  Google Cloud Storage credentials JSON file.
		 *
		 * @param string $json_credentials_path Full path to Google Cloud Storage credentials JSON file
		 */
		return apply_filters( 'wporg_photos_google_storage_credentials_json_path', '' );
	}

	/**
	 * Returns the id of the Google Cloud project.
	 *
	 * Must be configured via 'wporg_photos_google_cloud_project_id' filter.
	 *
	 * @return string|false The id of the project, or false if none defined.
	 */
	public static function get_cloud_project_id() {
		// Use memoized value if there is one.
		if ( self::$cloud_project_id ) {
			return self::$cloud_project_id;
		}

		/**
		 * Filters the name of the Google Cloud Storage project ID.
		 *
		 * @param string $project_id Google Cloud Storage project ID.
		 */
		return self::$cloud_project_id = apply_filters( 'wporg_photos_google_cloud_project_id', '' );
	}

	/**
	 * Returns the name of the Google Cloud bucket for storing photos.
	 *
	 * Must be configured via 'wporg_photos_google_cloud_bucket' filter.
	 *
	 * @return string|false The name of the bucket, or false if none defined.
	 */
	public static function get_cloud_bucket() {
		// Use memoized value if there is one.
		if ( self::$cloud_bucket_name ) {
			return self::$cloud_bucket_name;
		}

		/**
		 * Filters the name of the Google Cloud Storage bucket.
		 *
		 * @param string $bucket_name Google Cloud Storage bucket name.
		 */
		return self::$cloud_bucket_name = apply_filters( 'wporg_photos_google_cloud_bucket', '' );
	}

	/**
	 * Returns the name of the Google Cloud domain name for storing photos,
	 * without protocol or trailing slash.
	 *
	 * Must be configured via 'wporg_photos_google_cloud_bucket' filter.
	 *
	 * @return string|false The name of the bucket, or false if none defined.
	 */
	public static function get_cloud_domain() {
		// Use memoized value if there is one.
		if ( self::$cloud_domain_name ) {
			return self::$cloud_domain_name;
		}

		/**
		 * Filters the name of the Google Cloud Storage domain name.
		 *
		 * @param string $bucket_name Google Cloud Storage domain name.
		 *                            Default 'storage.googleapis.com'.
		 */
		return self::$cloud_domain_name = apply_filters( 'wporg_photos_google_cloud_domain', 'storage.googleapis.com' );
	}

	/**
	 * Determines if the configured cloud domain is the default Google Storage
	 * domain or not.
	 *
	 * @return bool True if the cloud domain is the default Google Storage
	 *              domain, else false.
	 */
	public static function is_default_cloud_domain() {
		return self::get_cloud_domain() === 'storage.googleapis.com';
	}

	/**
	 * Determines if Google Cloud Storage has been enabled.
	 *
	 * Must be enabled via 'wporg_photos_google_cloud_bucket' filter.
	 *
	 * @return bool True if enable, otherwise false.
	 */
	public static function is_google_cloud_enabled() {
		/**
		 * Filters if Google Cloud Storage should be enabled.
		 *
		 * Note that there are other configuration that must take place for GCS
		 * usage to actually function, but this determines if it should be
		 * enabled in the first place.
		 *
		 * @param bool $enable_google_cloud_storage Enable Google Cloud Storage? Default false.
		 */
		return (bool) apply_filters( 'wporg_photos_enable_google_cloud_storage', false );
	}

	/**
	 * Determines if Google Cloud has been configured.
	 *
	 * Google Cloud Storage being enabled is a prerequisite for being considered
	 * as configured.
	 *
	 * Note: Does not necessarily check that credentials are correct or that the
	 * values configured are valid, mostly just that they are configured in the
	 * first place.
	 *
	 * @return bool True if configured, else false.
	 */
	public static function is_google_cloud_configured() {
		// Check if Google Cloud Storage usage is enabled.
		if ( ! self::is_google_cloud_enabled() ) {
			return false;
		}

		if ( ! self::get_cloud_project_id() ) {
			return false;
		}

		$path = self::get_cloud_credentials_path();

		if ( ! $path || ! file_exists( $path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Initializes the Google Cloud Storage client.
	 *
	 * @access protected
	 *
	 * @param callable $http_handler Optional. HTTP handler to invoke the API call. Default null.
	 * @return StorageClient|WP_Error The storage client, or false if not created.
	 */
	protected static function init_gs_client( $http_handler = null ) {
		if ( ! self::is_google_cloud_configured() ) {
			$required_filters = [
				'wporg_photos_google_storage_credentials_json_path',
				'wporg_photos_google_cloud_project_id',
				'wporg_photos_google_cloud_bucket',
			];

			return new \WP_Error(
				'wporg-photos-cloud_storage-init-error',
				sprintf(
					/* translators: %s: Comma-separated list of filter names. */
					__( 'Unable to initialize Google Cloud Storage API. Please filter the following hooks with appropriate values: %s', 'wporg-photos' ),
					implode( ', ', $required_filters )
				)
			);
		}

		$http_handler = $http_handler ? $http_handler : HttpHandlerFactory::build();

		return new StorageClient( [
			'keyFilePath' => self::get_cloud_credentials_path(),
			'projectId'   => self::get_cloud_project_id(),
			'httpHandler' => function ( $request, $options ) use ( $http_handler ) {
				$xGoogApiClientHeader = $request->getHeaderLine( 'x-goog-api-client' );
				$request = $request->withHeader( 'x-goog-api-client', $xGoogApiClientHeader );

				return call_user_func_array( $http_handler, [ $request, $options ] );
			},
			'authHttpHandler' => HttpHandlerFactory::build(),
		] );
	}

	/**
	 * Overwrites configuration for the upload dir to use Google Cloud Storage.
	 *
	 * @param array $values Upload directory configuration.
	 * @return array
	 */
	public static function filter_upload_dir( $values ) {
		// Bail if Google Cloud Storage not configured.
		if ( ! self::is_google_cloud_configured() ) {
			return $values;
		}

		$bucket = self::get_cloud_bucket();

		$basedir = sprintf( 'gs://%s', $bucket );

		$baseurl = 'https://' . self::get_cloud_domain();
		// If using default cloud domain, then need to include bucket name in path.
		if ( self::is_default_cloud_domain() ) {
			$baseurl .= '/' . $bucket;
		}

		$values = [
			'path'   => $basedir . $values['subdir'],
			'subdir' => $values['subdir'],
			'error'  => false,
		];
		$values['url']     = rtrim( $baseurl . $values['subdir'], '/' );
		$values['basedir'] = $basedir;
		$values['baseurl'] = $baseurl;

		return $values;
	}

	/**
	 * Filters the attachment URL to account for custom domain.
	 *
	 * @param string $url           URL for the given attachment.
	 * @param int    $attachment_id Attachment post ID.
	 * @return string The photo URL changed to point to Google Cloud Storage.
	 */
	public static function filter_wp_get_attachment_url( $url, $attachment_id ) {
		$post = get_post_parent( $attachment_id );
		$domain = self::get_cloud_domain();
		$bucket = self::get_cloud_bucket();

		if ( ! $post || ! $domain ) {
			return $url;
		}

		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return $url;
		}

		$default_cloud_domain = 'storage.googleapis.com';

		if ( $domain === $default_cloud_domain ) {
			return $url;
		}

		// @todo: This should check post meta as well, so that files that got saved to cloud
		// can be linked to there, whereas in some weird hybrid fashion where some photos
		// were saved locally, those could be served locally and not assumed to also be stored
		// in cloud.

		return str_replace( $default_cloud_domain . '/' . $bucket, $domain, $url );
	}

	/**
	 * Appends Google Cloud Storage data to attachment metadata.
	 *
	 * Currently just for informational purposes.
	 *
	 * @param array  $metadata      An array of attachment meta data.
	 * @param int    $attachment_id Current attachment ID.
	 * @param string $context       Additional context. Can be 'create' when metadata was initially created for new attachment
	 *                              or 'update' when the metadata was updated.
	 * @return array
	 */
	public static function append_attachment_metadata( $metadata, $attachment_id, $context ) {
		$metadata['gcs_url']    = wp_get_attachment_url( $attachment_id );
		$metadata['gcs_name']   = $metadata['file'] ?? '';
		$metadata['gcs_bucket'] = self::get_cloud_bucket();

		return $metadata;
	}
}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\GoogleCloudStorage', 'init' ] );
