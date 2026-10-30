<?php
/**
 * Google Vision API handling for image metadata and analysis.
 *
 * Note: Be sure to filter the following:
 * - `wporg_photos_google_credentials_json_path` with the full path to the Google credentials JSON file
 * - Optional: `wporg_photos_google_cloud_bucket` with the name of the cloud storage bucket for storing photos
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

require_once WPORG_PHOTO_DIRECTORY_DIRECTORY . '/vendor/autoload.php';
require_once WPORG_PHOTO_DIRECTORY_DIRECTORY . '/inc/color-utils.php';

// Import Google libraries.
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\ImageSource;

class VisionAPI {

	/**
	 * Initializes the environment variable with the path to the Google credentials JSON
	 * file.
	 *
	 * Note: Does not validate the JSON file; merely checks for its existence.
	 *
	 * @return bool True if the credentials were loaded, else false.
	 */
	public static function init_google_credentials() {
		$success = false;
		$path = apply_filters( 'wporg_photos_google_credentials_json_path', '' );

		if ( $path && file_exists( $path ) ) {
			putenv( 'GOOGLE_APPLICATION_CREDENTIALS=' . $path );
			$success = true;
		}

		return $success;
	}

	/**
	 * Returns the name of the Google Cloud bucket for storing photos.
	 *
	 * @return string|false The name of the bucket, or false if none defined.
	 */
	public static function get_cloud_bucket() {
		/**
		 * Filters the name of the Google Cloud Storage bucket.
		 *
		 * @return string
		 */
		return apply_filters( 'wporg_photos_google_cloud_bucket', false );
	}

	/**
	 * Determines if Google Cloud has been configured.
	 *
	 * Note: Does not necessarily check that credentials are correct or that the
	 * values configured are valid, mostly just that they are configured in the
	 * first place.
	 *
	 * @return bool True if configured, else false.
	 */
	public static function is_google_cloud_configured() {
		return self::init_google_credentials();
	}

	/**
	 * Fetches the Vision API analysis of the image at the given path.
	 *
	 * @param string $path Path to image.
	 * @return array
	 */
	public static function get_vision_analysis( $path ) {
		if ( ! self::init_google_credentials() ) {
			return new \WP_Error(
				'wporg-photos-vision-init-error',
				sprintf(
					/* translators: %s: Filter name. */
					__( 'Unable to initialize Google API. Please filter "%s" with appropriate values.', 'wporg-photos' ),
					'wporg_photos_google_credentials_json_path'
				)
			);
		}

		$image  = new Image();
		$bucket = self::get_cloud_bucket();
		if ( $bucket ) {
			$image->setSource( ( new ImageSource() )->setGcsImageUri( 'gs://' . $bucket . '/' . $path ) );
		} else {
			$upload_dir = wp_get_upload_dir();
			$image->setContent( file_get_contents( $upload_dir['basedir'] . '/' . $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		}

		$features = [
			( new Feature() )->setType( Type::FACE_DETECTION )->setMaxResults( 10 ),
			( new Feature() )->setType( Type::IMAGE_PROPERTIES )->setMaxResults( 50 ),
			( new Feature() )->setType( Type::LABEL_DETECTION )->setMaxResults( 10 ),
			( new Feature() )->setType( Type::SAFE_SEARCH_DETECTION )->setMaxResults( 50 ),
		];

		$request = ( new BatchAnnotateImagesRequest() )->setRequests(
			[ ( new AnnotateImageRequest() )->setImage( $image )->setFeatures( $features ) ]
		);

		$vision   = new ImageAnnotatorClient( [ 'transport' => 'rest' ] );
		$response = $vision->batchAnnotateImages( $request )->getResponses()[0];

		if ( $response->hasError() ) {
			return new \WP_Error( 'wporg-photos-vision-error', $response->getError()->getMessage() );
		}

		// Stored as post meta, so keep it a plain array rather than a library object.
		$annotation = json_decode( $response->serializeToJsonString(), true );

		return [ 'vision_raw_annotation' => $annotation ] + self::extract_from_api( $annotation );
	}

	/**
	 * Extracts data from Vision API response.
	 *
	 * @link https://cloud.google.com/vision/docs/reference/rest/v1/AnnotateImageResponse
	 *
	 * @param array $response Image annotation response, decoded from its JSON form.
	 * @return array {
	 *     Array of data extracted from the Vision API. Keys only present if extracted.
	 *
	 *     @type array $vision_raw_colors  Raw data extracted for detected colors.
	 *     @type array $vision_colors      Base/primary colors and their percentage composition within image.
	 *     @type array $vision_faces       Detected faces.
	 *     @type array $vision_labels      Detected labels.
	 *     @type array $vision_safe_search Safe Search categories and their respective likelihoods.
	 * }
	 */
	protected static function extract_from_api( $response ) {
		$results = [];

		// Extract colors.
		$raw_colors = $response['imagePropertiesAnnotation']['dominantColors']['colors'] ?? [];
		if ( $raw_colors ) {
			$colors                       = self::extract_colors( $raw_colors );
			$results['vision_raw_colors'] = $colors[0];
			$results['vision_colors']     = $colors[1];
		}

		// Basic use of face detection, just to count detected faces.
		$faces                   = $response['faceAnnotations'] ?? [];
		$results['vision_faces'] = [ 'count' => count( $faces ) ];
		// Save raw faces data for potential future use.
		if ( $faces ) {
			$results['vision_faces']['raw'] = $faces;
		}

		$labels = self::extract_labels( $response['labelAnnotations'] ?? [] );
		if ( $labels ) {
			$results['vision_labels'] = array_map( 'strtolower', $labels );
		}

		$safe_search = self::extract_safe_search_assessment( $response['safeSearchAnnotation'] ?? [] );
		if ( $safe_search ) {
			$results['vision_safe_search'] = $safe_search;
		}

		return $results;
	}

	/**
	 * Extracts color information from Vision API response.
	 *
	 * @link https://cloud.google.com/vision/docs/reference/rest/v1/AnnotateImageResponse#DominantColorsAnnotation
	 *
	 * @param array $api_colors Array of color information returned by Vision API.
	 * @return array {
	 *     @type array $0 {
	 *         Array of extracted colors, with each color array consisting of:
	 *
	 *         @type string $r          The red RBG value.
	 *         @type string $b          The blue RBG value.
	 *         @type string $g          The green RBG value.
	 *         @type float  $percentage The percentage of pixels in the image
	 *                                  consisting of the color.
	 *         @type string $base_color The color's closest base/primary color.
	 *     }
	 *     @type float[] $1 Associative array of base/primary colors and the percentage
	 *                      of pixels in the image consisting of the color.
	 * }
	 */
	public static function extract_colors( $api_colors ) {
		$colors = [];

		foreach ( $api_colors as $dom_color ) {
			$color = [];
			$color['b']          = $dom_color['color']['blue']  ?? 0.0;
			$color['g']          = $dom_color['color']['green'] ?? 0.0;
			$color['r']          = $dom_color['color']['red']   ?? 0.0;
			$color['percentage'] = $dom_color['pixelFraction'];
			$color['base_color'] = ColorUtils::get_nearest_color( $color['r'], $color['g'], $color['b'] );
			$colors[] = $color;
		}

		$cumulative_colors = [];

		// Get the closest base colors and add the cumulative percentages.
		foreach ( $colors as $color ) {
			$base_color = $color['base_color'];
			if ( empty( $cumulative_colors[ $base_color ] ) ) {
				$cumulative_colors[ $base_color ] = $color['percentage'];
			} else {
				$cumulative_colors[ $base_color ] += $color['percentage'];
			}
		}

		return [ $colors, $cumulative_colors ];
	}

	/**
	 * Extracts and filters label information from Vision API response.
	 *
	 * Only returns labels above a confidence threshold for applicability.
	 *
	 * @link https://cloud.google.com/vision/docs/reference/rest/v1/AnnotateImageResponse
	 *
	 * @param array $api_labels Array of labels.
	 * @return array
	 */
	public static function extract_labels( $api_labels ) {
		$labels = [];
		// Minimum confidence threshold for applicability.
		$min_score = 0.80;

		foreach ( $api_labels as $label ) {
			if ( ( $label['score'] ?? 0 ) > $min_score ) {
				$labels[] = $label['description'];
			}
		}

		return $labels;
	}

	/**
	 * Extracts safe search assessments from Vision API.
	 *
	 * @link https://cloud.google.com/vision/docs/reference/rest/v1/AnnotateImageResponse#SafeSearchAnnotation
	 *
	 * @param array $api_safe_search Array of safe search assessments.
	 * @return array {
	 *     Array of safe search assessments for each moderation category. Values
	 *     will be one of VERY_LIKELY, LIKELY, POSSIBLE, UNLIKELY, VERY_UNLIKELY,
	 *     or UNKNOWN.
	 *
	 *     @type string $adult    Likelihood of the image containing adult content.
	 *     @type string $medical  Likelihood of the image containing medical content.
	 *     @type string $racy     Likelihood of the image containing racy content.
	 *     @type string $spoof    Likelihood of the image containing spoof content.
	 *     @type string $violence Likelihood of the image containing violent content.
	 * }
	 */
	public static function extract_safe_search_assessment( $api_safe_search ) {
		if ( ! $api_safe_search ) {
			return;
		}

		$moderation_categories = [
			'adult'    => '',
			'medical'  => '',
			'racy'     => '',
			'spoof'    => '',
			'violence' => '',
		];

		foreach ( array_keys( $moderation_categories ) as $key  ) {
			$moderation_categories[ $key ] = $api_safe_search[ $key ] ?? 'UNKNOWN';
		}

		return $moderation_categories;
	}

}
