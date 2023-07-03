<?php
/**
 * Photo handling.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Photo {

	/**
	 * Tags that should never be auto-assigned based on Vision API analysis.
	 *
	 * These tags can still be manually assigned.
	 *
	 * @var string[]
	 */
	const TAGS_TO_IGNORE = [
		'agriculture', 'arecales', 'atmosphere', 'azure',
		'biome', 'botany',
		'canidae', 'carnivore', 'colorfulness', 'cuisine', 'cumulus',
		'ecoregion', 'erinaceidae',
		'felidae', 'fluid',
		'galliformes',
		'highland',
		'infrastructure', 'ingredient',
		'larch', 'liquid',
		'mammal',
		'organism',
		'pest', 'phasianidae', 'photograph', 'plant', 'pollinator', 'product',
		'recipe', 'rectangle',
		'terrain', 'tire', 'trunk', 'twig',
		'vegetarian', 'vegetation', 'vertebrate',
		'watercourse', 'wheel', 'whiskers', 'window', 'wood', 'world',
	];

	/**
	 * Associative array of category slugs and associated tags that imply the
	 * relevance of the category.
	 *
	 * This is not meant to be thorough, just associations likely to be
	 * frequently encountered and fairly safely correlated to provide
	 * default categorization of the photo for moderators.
	 *
	 * @var string[]
	 */
	const TAGS_TO_CATEGORY = [
		'animals' => [
			'bird', 'carnivore', 'cat', 'dog', 'fish', 'herbivore', 'horse', 'lizard', 'mammal', 'rabbit', 'reptile', 'rodent', 'whale',
		],
		'architecture' => [
			'building', 'buildings', 'castle', 'cottage', 'house', 'houses', 'hut', 'library', 'room', 'shed', 'skyscraper', 'skyscrapers', 'station', 'store', 'storefront', 'tower',
		],
		'arts-culture' => [
			'art', 'band', 'dancer', 'dancing', 'drawing', 'crafts', 'instrument', 'music', 'painting', 'sculpture', 'statue',
		],
		'athletics' => [
			'athelete', 'baseball', 'basketball', 'boxing', 'bicycling', 'cricket', 'fencing', 'football', 'golf', 'gymnastics', 'hockey', 'ice skating', 'jai alai', 'karate', 'kickboxing', 'marathon', 'martial arts', 'mma', 'power lifting', 'olympics', 'polo', 'race', 'racing', 'running', 'rugby', 'ski', 'skiing', 'soccer', 'sport', 'sports', 'swimming', 'tai chi', 'tennis', 'weightlifting', 'wrestling', 'yoga',
		],
		'fashion' => [
			'belt', 'blouse', 'bowtie', 'coat', 'dress', 'dresses', 'hat', 'hats', 'heels', 'jacket', 'pants', 'scarf', 'shoe', 'shoes', 'shorts', 'skirt', 'socks', 'suit', 'sweatshirt', 'sweatpants', 'sweats',
		],
		'food-drink' => [
			'appetizer', 'bake', 'baking', 'beef', 'beer', 'breakfast', 'brunch', 'bourbon', 'cake', 'champagne', 'chocolate', 'cocktai', 'coffee', 'cook', 'cookie', 'cookies', 'cooking', 'dairy', 'dessert', 'dinner', 'drink', 'drinks', 'food', 'fruit', 'gin', 'lunch', 'meal', 'pasta', 'pastry', 'pork', 'salad', 'sandwich', 'seafood', 'snack', 'soda', 'tea', 'tequila', 'vegetable', 'vegetables', 'whiskey', 'whisky', 'wine',
		],
		'interiors' => [
			'auditorium', 'bathroom', 'bedroom', 'den', 'furniture', 'kitchen', 'library', 'office', 'room', 'stage',
		],
		'nature' => [
			'beach', 'bush', 'bushes', 'clouds', 'creek', 'desert', 'field', 'flower', 'flowers', 'forest', 'garden', 'grass', 'hill', 'hills', 'leaf', 'meadow', 'meadows', 'mountain', 'mountains', 'ocean', 'outdoors', 'petal', 'rain', 'river', 'rocks', 'sea', 'shrub', 'shrubs', 'sky', 'snow', 'stream', 'trail', 'tree', 'trees', 'woods',
		],
		'objects' => [
		],
		'patterns' => [
			'pattern', 'pinstripes', 'stripes', 'zigzag',
		],
//		'people' => [
//			'family', 'person',
//		],
		'technology' => [
			'appliance', 'appliances', 'camera', 'clock', 'computer', 'headphones', 'ipad', 'iphone', 'imac', 'keyboard', 'laptop', 'microphone', 'phone', 'speakers', 'stereo', 'telephone', 'television', 'turntable', 'tv', 'watch', 'wristwatch',
		],
		'transportation' => [
			'airplane', 'automobile', 'bicycle', 'bus', 'car', 'helicopter', 'locomotive', 'motorcycle', 'plane', 'subway', 'suv', 'train', 'tram', 'truck', 'vehicle',
		],
	];

	/**
	 * Initializes module.
	 */
	public static function init() {
		add_action( 'wporg_photos_photo_upload_complete',   [ __CLASS__, 'get_analysis' ] );
		add_action( 'wporg_photos_photo_analysis_complete', [ __CLASS__, 'assign_categories' ], 11 );
		// Disabled until such time as it can be improved, assuming that's worthwhile.
		//add_action( 'wporg_photos_photo_analysis_complete', [ __CLASS__, 'assign_colors' ], 11 );
		add_action( 'wporg_photos_photo_analysis_complete', [ __CLASS__, 'assign_tags' ] );
		add_action( 'wporg_photos_photo_upload_complete',   [ __CLASS__, 'set_orientation' ] );

		// Workarounds for frequent inability to parse EXIF data via streamed files.
		add_filter( 'wp_read_image_metadata',               [ __CLASS__, 'retry_exif_read' ], 10, 5 );
		add_filter( 'wp_image_maybe_exif_rotate',           [ __CLASS__, 'wp_image_maybe_exif_rotate' ], 10, 2 );
	}

	/**
	 * Encodes an image file into a 'data://' stream as an alternative approach
	 * to using `exif_read_data()` (which currently has issues with the Google
	 * Storage stream).
	 *
	 * Adapted from a section of `wp_read_image_metadata()`.
	 *
	 * @param string $file  Path to the image file.
	 * @param bool   $force Optional. Should any potentially cached value be
	 *                      ignored, forcing a new read from data stream?
	 *                      Default false.
	 * @return array
	 */
	protected static function exif_read_data_as_data_stream( $file, $force = false ) {
		$cache_key   = 'exif_from_data_stream_' . $file;
		$cache_group = 'wporg_photos';

		// Return cached value if there is one and if a refresh isn't being forced.
		if ( ! $force && $cached = wp_cache_get( $cache_key, $cache_group ) ) {
			return $cached;
		}

		$image = file_get_contents( $file );
		$image_data = "data://image/jpeg;base64," . base64_encode( $image );

		// Don't silence errors when in debug mode, unless running unit tests.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG
			&& ! defined( 'WP_RUN_CORE_TESTS' )
		) {
			$exif = exif_read_data( $image_data );
		} else {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors -- Silencing notice and warning is intentional. See https://core.trac.wordpress.org/ticket/42480
			$exif = @exif_read_data( $image_data );
		}

		wp_cache_set( $cache_key, $exif, $cache_group );

		return $exif;
	}

	/**
	 * Returns the orientation defined in an image's EXIF data, but by using a
	 * `data://` stream parsing of EXIF data if the direct file stream's EXIF
	 * parsing failed.
	 *
	 * @param int    $orientation EXIF Orientation value as retrieved from the image file.
	 * @param string $file        Path to the image file.
	 * @return int
	 */
	public static function wp_image_maybe_exif_rotate( $orientation, $file ) {
		// If no orientation was extracted from file, try again.
		if ( ! $orientation ) {
			$exif_data = self::exif_read_data_as_data_stream( $file );
			if ( ! empty( $exif_data['Orientation'] ) ) {
				$orientation = $exif_data['Orientation'];
			}
		}

		return $orientation;
	}

	/**
	 * Attempts a 'data://' stream parsing of EXIF data for an image if the
	 * direct file stream's EXIF parsing failed.
	 *
	 * Calling `exif_read_data()` directly on the Google Storage 'gs://' stream
	 * wrapper seems touchy, failing to obtain EXIF data more times than not,
	 * though it appears to be related to the image files themselves and doesn't
	 * affect all files. Perhaps the stream is not a seekable as the
	 * `exif_read_data()`docs suggest it needs to be.
	 *
	 * This function essentially copies `wp_read_image_metadata()`. If that
	 * function didn't find any EXIF data, then this is called. This does everything
	 * that function does but with 2 changes:
	 * - A check was added to the very beginning to bail early if EXIF had been extracted.
	 * - The calls to `exif_read_data()` have been commented out and a call to
	 *   `self::exif_read_data_as_data_stream()` as been added instead.
	 *
	 * Look for 'Start of retry_exif_read() specific changes here. ' to denote the
	 * start of the section that is changed.
	 *
	 * @see
	 *
	 * @param array  $meta       Image meta data.
	 * @param string $file       Path to image file.
	 * @param int    $image_type Type of image, one of the `IMAGETYPE_XXX` constants.
	 * @param array  $iptc       IPTC data.
	 * @param array  $exif       EXIF data.
	 */
	public static function retry_exif_read( $meta, $file, $image_type, $iptc, $exif ) {
		// Bail if EXIF data was successfully obtained.
		if ( $exif ) {
			return $meta;
		}

		/* ====================
		 * Source for wp_read_image_metadata() starts here through end of
		 * function except for one small section denoted within.
		 * ==================== */

		if ( ! file_exists( $file ) ) {
			return false;
		}

		list( , , $image_type ) = wp_getimagesize( $file );

		/*
		 * EXIF contains a bunch of data we'll probably never need formatted in ways
		 * that are difficult to use. We'll normalize it and just extract the fields
		 * that are likely to be useful. Fractions and numbers are converted to
		 * floats, dates to unix timestamps, and everything else to strings.
		 */
		$meta = array(
			'aperture'          => 0,
			'credit'            => '',
			'camera'            => '',
			'caption'           => '',
			'created_timestamp' => 0,
			'copyright'         => '',
			'focal_length'      => 0,
			'iso'               => 0,
			'shutter_speed'     => 0,
			'title'             => '',
			'orientation'       => 0,
			'keywords'          => array(),
		);

		$iptc = array();
		$info = array();
		/*
		 * Read IPTC first, since it might contain data not available in exif such
		 * as caption, description etc.
		 */
		if ( is_callable( 'iptcparse' ) ) {
			wp_getimagesize( $file, $info );

			if ( ! empty( $info['APP13'] ) ) {
				// Don't silence errors when in debug mode, unless running unit tests.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG
					&& ! defined( 'WP_RUN_CORE_TESTS' )
				) {
					$iptc = iptcparse( $info['APP13'] );
				} else {
					// phpcs:ignore WordPress.PHP.NoSilencedErrors -- Silencing notice and warning is intentional. See https://core.trac.wordpress.org/ticket/42480
					$iptc = @iptcparse( $info['APP13'] );
				}

				// Headline, "A brief synopsis of the caption".
				if ( ! empty( $iptc['2#105'][0] ) ) {
					$meta['title'] = trim( $iptc['2#105'][0] );
					/*
					* Title, "Many use the Title field to store the filename of the image,
					* though the field may be used in many ways".
					*/
				} elseif ( ! empty( $iptc['2#005'][0] ) ) {
					$meta['title'] = trim( $iptc['2#005'][0] );
				}

				if ( ! empty( $iptc['2#120'][0] ) ) { // Description / legacy caption.
					$caption = trim( $iptc['2#120'][0] );

					mbstring_binary_safe_encoding();
					$caption_length = strlen( $caption );
					reset_mbstring_encoding();

					if ( empty( $meta['title'] ) && $caption_length < 80 ) {
						// Assume the title is stored in 2:120 if it's short.
						$meta['title'] = $caption;
					}

					$meta['caption'] = $caption;
				}

				if ( ! empty( $iptc['2#110'][0] ) ) { // Credit.
					$meta['credit'] = trim( $iptc['2#110'][0] );
				} elseif ( ! empty( $iptc['2#080'][0] ) ) { // Creator / legacy byline.
					$meta['credit'] = trim( $iptc['2#080'][0] );
				}

				if ( ! empty( $iptc['2#055'][0] ) && ! empty( $iptc['2#060'][0] ) ) { // Created date and time.
					$meta['created_timestamp'] = strtotime( $iptc['2#055'][0] . ' ' . $iptc['2#060'][0] );
				}

				if ( ! empty( $iptc['2#116'][0] ) ) { // Copyright.
					$meta['copyright'] = trim( $iptc['2#116'][0] );
				}

				if ( ! empty( $iptc['2#025'][0] ) ) { // Keywords array.
					$meta['keywords'] = array_values( $iptc['2#025'] );
				}
			}
		}

		$exif = array();

		/**
		 * Filters the image types to check for exif data.
		 *
		 * @since 2.5.0
		 *
		 * @param int[] $image_types Array of image types to check for exif data. Each value
		 *                           is usually one of the `IMAGETYPE_*` constants.
		 */
		$exif_image_types = apply_filters( 'wp_read_image_metadata_types', array( IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM ) );

		if ( is_callable( 'exif_read_data' ) && in_array( $image_type, $exif_image_types, true ) ) {
/* === Start of retry_exif_read() specific changes here. ========================================= */
//			// Don't silence errors when in debug mode, unless running unit tests.
//			if ( defined( 'WP_DEBUG' ) && WP_DEBUG
//				&& ! defined( 'WP_RUN_CORE_TESTS' )
//			) {
//				$exif = exif_read_data( $file );
//			} else {
//				// phpcs:ignore WordPress.PHP.NoSilencedErrors -- Silencing notice and warning is intentional. See https://core.trac.wordpress.org/ticket/42480
//				$exif = @exif_read_data( $file );
//			}
$exif = self::exif_read_data_as_data_stream( $file );
/* === End of retry_exif_read() specific changes here. ========================================== */

			if ( ! empty( $exif['ImageDescription'] ) ) {
				mbstring_binary_safe_encoding();
				$description_length = strlen( $exif['ImageDescription'] );
				reset_mbstring_encoding();

				if ( empty( $meta['title'] ) && $description_length < 80 ) {
					// Assume the title is stored in ImageDescription.
					$meta['title'] = trim( $exif['ImageDescription'] );
				}

				if ( empty( $meta['caption'] ) && ! empty( $exif['COMPUTED']['UserComment'] ) ) {
					$meta['caption'] = trim( $exif['COMPUTED']['UserComment'] );
				}

				if ( empty( $meta['caption'] ) ) {
					$meta['caption'] = trim( $exif['ImageDescription'] );
				}
			} elseif ( empty( $meta['caption'] ) && ! empty( $exif['Comments'] ) ) {
				$meta['caption'] = trim( $exif['Comments'] );
			}

			if ( empty( $meta['credit'] ) ) {
				if ( ! empty( $exif['Artist'] ) ) {
					$meta['credit'] = trim( $exif['Artist'] );
				} elseif ( ! empty( $exif['Author'] ) ) {
					$meta['credit'] = trim( $exif['Author'] );
				}
			}

			if ( empty( $meta['copyright'] ) && ! empty( $exif['Copyright'] ) ) {
				$meta['copyright'] = trim( $exif['Copyright'] );
			}
			if ( ! empty( $exif['FNumber'] ) && is_scalar( $exif['FNumber'] ) ) {
				$meta['aperture'] = round( wp_exif_frac2dec( $exif['FNumber'] ), 2 );
			}
			if ( ! empty( $exif['Model'] ) ) {
				$meta['camera'] = trim( $exif['Model'] );
			}
			if ( empty( $meta['created_timestamp'] ) && ! empty( $exif['DateTimeDigitized'] ) ) {
				$meta['created_timestamp'] = wp_exif_date2ts( $exif['DateTimeDigitized'] );
			}
			if ( ! empty( $exif['FocalLength'] ) ) {
				$meta['focal_length'] = (string) $exif['FocalLength'];
				if ( is_scalar( $exif['FocalLength'] ) ) {
					$meta['focal_length'] = (string) wp_exif_frac2dec( $exif['FocalLength'] );
				}
			}
			if ( ! empty( $exif['ISOSpeedRatings'] ) ) {
				$meta['iso'] = is_array( $exif['ISOSpeedRatings'] ) ? reset( $exif['ISOSpeedRatings'] ) : $exif['ISOSpeedRatings'];
				$meta['iso'] = trim( $meta['iso'] );
			}
			if ( ! empty( $exif['ExposureTime'] ) ) {
				$meta['shutter_speed'] = (string) $exif['ExposureTime'];
				if ( is_scalar( $exif['ExposureTime'] ) ) {
					$meta['shutter_speed'] = (string) wp_exif_frac2dec( $exif['ExposureTime'] );
				}
			}
			if ( ! empty( $exif['Orientation'] ) ) {
				$meta['orientation'] = $exif['Orientation'];
			}
		}

		foreach ( array( 'title', 'caption', 'credit', 'copyright', 'camera', 'iso' ) as $key ) {
			if ( $meta[ $key ] && ! seems_utf8( $meta[ $key ] ) ) {
				$meta[ $key ] = utf8_encode( $meta[ $key ] );
			}
		}

		foreach ( $meta['keywords'] as $key => $keyword ) {
			if ( ! seems_utf8( $keyword ) ) {
				$meta['keywords'][ $key ] = utf8_encode( $keyword );
			}
		}

		return wp_kses_post_deep( $meta );
	}

	/**
	 * Returns an array of post statuses for which a photo can be associated.
	 *
	 * @return string[] Array of post statuses.
	 */
	public static function get_post_statuses_with_photo() {
		return (array) apply_filters(
			'wporg_photos_post_statuses_with_photo',
			[ 'draft', 'inherit', 'pending', 'private', 'publish' ]
		);
	}

	/**
	 * Determines if the provided MD5 hash of a photo is already known, implying
	 * it is a duplicate.
	 *
	 * @param string $hash The MD5 hash of a photo file. Should not be the hash
	 *                     of a known photo as obviously the provided hash
	 *                     would match itself.
	 * @return bool True is the MD5 hash matches one for an existing photo.
	 */
	public static function hash_exists( $hash ) {
		$dupe = get_posts( [
			'fields'         => 'ids',
			'meta_query'     => [ [
				'key'        => Registrations::get_meta_key( 'file_hash' ),
				'value'      => $hash,
			] ],
			'post_status'    => [ 'draft', 'pending', 'private', 'publish', Rejection::get_post_status() ],
			'post_type'      => Registrations::get_post_type(),
			'posts_per_page' => 1,
		] );

		return ! empty( $dupe );
	}

	/**
	 * Returns the full photo analysis data from cache or via API call.
	 *
	 * @param int  $image_id The ID of the attachment that is the photo.
	 * @param bool $force    Optional. Should any potentially cached value be
	 *                       ignored, forcing a new API fetch for data? Default
	 *                       false.
	 * @return array|false   Associative array of analysis info or false if the
	 *                       image was invalid or not data was retrieved.
	 */
	public static function fetch_analysis_from_api( $image_id, $force = false ) {
		$photo_meta = wp_get_attachment_metadata( $image_id );

		if ( ! $photo_meta ) {
			return false;
		}

		$cache_key   = 'vision_data_' . $image_id;
		$cache_group = 'wporg_photos';

		// Return cached value if there is one and if a refresh isn't being forced.
		if ( ! $force && $cached = wp_cache_get( $cache_key, $cache_group ) ) {
			return $cached;
		}

		$results = VisionAPI::get_vision_analysis( $photo_meta['file'] );

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		wp_cache_set( $cache_key, $results, $cache_group );

		return $results;
	}

	/**
	 * Stores and returns the full photo analysis.
	 *
	 * @param int  $image_id The ID of the attachment that is the photo.
	 * @param bool $force    Optional. Should any potentially cached value be
	 *                       ignored, forcing a new API fetch for data? Default
	 *                       false.
	 * @return array|false   Associative array of analysis info or false if the
	 *                       image was invalid or not data was retrieved.
	 */
	public static function get_analysis( $image_id, $force = false ) {
		$results = self::fetch_analysis_from_api( $image_id, $force );

		if ( ! $results || is_wp_error( $results ) ) {
			// @todo Log error.
			return false;
		}

		// Store the data retrieved via API.
		$results = self::store_analysis_data( $results, $image_id );

		do_action( 'wporg_photos_photo_analysis_complete', $image_id, $results );

		return $results;
	}

	/**
	 * Stores analysis data from the Google Vision API as post meta.
	 *
	 * @param array $data     Analysis data.
	 * @param int   $image_id The ID of the attachment that is the photo.
	 * @return array Returns the `$data` passed in.
	 */
	protected static function store_analysis_data( $data, $image_id ) {
		$post_id = wp_get_post_parent_id( $image_id );

		if ( ! $post_id ) {
			return false;
		}

		$post = get_post( $post_id );

		// Separately store each type of analysis data into custom fields.
		foreach ( $data as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		return $data;
	}

	/**
	 * Automatically assigns categories to a photo.
	 *
	 * Categories are predefined and are only automatically assigned if it looks
	 * like the photo matches one or more of the categories (based on suggestested
	 * tags).
	 *
	 * @param int $image_id Photo attachment ID.
	 * @return bool True if at least one category was assigned, else false.
	 */
	public static function assign_categories( $image_id ) {
		$post_id = wp_get_post_parent_id( $image_id );

		if ( ! $post_id ) {
			return false;
		}

		// Get all currently defined categories.
		$all_categories = get_terms( [
			'taxonomy'   => Registrations::get_taxonomy( 'categories' ),
			'hide_empty' => false,
		] );

		if ( ! $all_categories ) {
			return false;
		}

		// Get categories for post.
		$categories = self::get_tags( $image_id );

		// Get tags for post.
		$tags = self::get_tags( $post_id );

		$categories_to_assign = [];

		$cat_names = array_map( function( $obj ) { return strtolower( $obj->name ); }, $categories );
		$tag_names = array_map( function( $obj ) { return strtolower( $obj->name ); }, $tags );

		// See if any of the assigned tags match.
		foreach ( $all_categories as $category ) {
			$cat_name = $category->slug;
			if (
				// Category is not already assigned.
				! in_array( $cat_name, $cat_names )
			&&
				(
					// Category is explicitly mentioned as a tag.
					in_array( $cat_name, $tag_names )
				||
					// Category is implied by a tag.
					( ! empty( self::TAGS_TO_CATEGORY[ $cat_name ] ) && array_intersect( self::TAGS_TO_CATEGORY[ $cat_name ], $tag_names ) )
				)
			) {
				$categories_to_assign[] = $category->slug;
			}
		}

		$return = false;

		if ( $categories_to_assign ) {
			$set = wp_set_object_terms( $post_id, $categories_to_assign, Registrations::get_taxonomy( 'categories' ) );
			if ( ! empty( $set ) && ! is_wp_error( $set ) ) {
				$return = true;
			}
		}

		return $return;
	}

	/**
	 * Assigns the colors to an attachment's parent post.
	 *
	 * Use color analysis from Google Vision API (which breaks down color usage
	 * within the image) to determine which of the recognized color categories
	 * in use apply.
	 *
	 * Also looks for color cues from the labels detected by Google Vision API.
	 *
	 * @param int $image_id Photo attachment ID.
	 * @return bool True if tags were successfully assigned, else false.
	 */
	public static function assign_colors( $image_id ) {
		$post_id = wp_get_post_parent_id( $image_id );

		if ( ! $post_id ) {
			return false;
		}

		$colors = self::get_raw_colors( $post_id );

		if ( ! $colors ) {
			return false;
		}

		// Assign colors of 40% dominance in the image or highter (and only return array keys).
		$colors_to_assign = array_keys( array_filter( $colors, function( $val ) { return $val > 0.4; } ) );

		// Look for colors detected in labels.
		$tag_colors = array_filter(
			self::get_raw_labels( $post_id ),
			function ( $val ) { return in_array( $val, array_keys( ColorUtils::COLORS ) ); }
		);

		// Merge colors to assign.
		$colors_to_assign = array_unique( array_merge( $colors_to_assign, $tag_colors ) );

		$return = false;

		if ( $colors_to_assign ) {
			$set = wp_set_object_terms( $post_id, $colors_to_assign, Registrations::get_taxonomy( 'colors' ) );
			if ( ! empty( $set ) && ! is_wp_error( $set ) ) {
				$return = true;
			}
		}

		return $return;
	}

	/**
	 * Assigns the tags to an attachment's parent post.
	 *
	 * Takes into account:
	 * - Labels determined by Google Vision API
	 * - Tags that should be rejected from use
	 * - Omitting tags that have a space in them
	 * - Results of filtering via `'wporg_photos_pre_tags_assign'`
	 *
	 * @param int $image_id Photo attachment ID.
	 * @return bool True if tags were successfully assigned, else false.
	 */
	public static function assign_tags( $image_id ) {
		$post_id = wp_get_post_parent_id( $image_id );

		if ( ! $post_id ) {
			return false;
		}

		$tags = self::get_raw_labels( $post_id );

		// Reject tags that are explicitly being excluded.
		$tags_to_assign = array_filter( $tags, function ( $val ) { return ! in_array( $val, self::TAGS_TO_IGNORE ); } );

		// Reject tags that have a space in them.
		$tags_to_assign = array_filter( $tags_to_assign, function ( $val ) { return false === strpos( $val, ' ' ); } );

		// Filter tags.
		$tags_to_assign = (array) apply_filters( 'wporg_photos_pre_tags_assign', $tags_to_assign, $post_id, $image_id );

		$return = false;

		if ( $tags_to_assign ) {
			$set = wp_set_object_terms( $post_id, $tags_to_assign, Registrations::get_taxonomy( 'tags' ) );
			if ( ! empty( $set ) && ! is_wp_error( $set ) ) {
				$return = true;
			}
		}

		return $return;
	}

	/**
	 * Returns the possible image orientations.
	 *
	 * @return array
	 */
	public static function get_orientations() {
		return [ 'portrait', 'landscape', 'square' ];
	}

	/**
	 * Sets the orientation for a photo post.
	 *
	 * @see get_orientations()
	 *
	 * @param int    $image_id    Photo post ID.
	 * @param string $return_type Optional. What should be returned? Accepts:
	 *                            'object', 'string'. Default 'object'.
	 * @return false|string|WP_Term False if an orientation couldn't be set,
	 *                              else the orientation assigned return as
	 *                              either a WP_Term object or the string
	 *                              name for the orientation based on $return_type.
	 */
	public static function set_orientation( $image_id, $return_type = 'object' ) {
		$post_id = wp_get_post_parent_id( $image_id );
		$orientation = false;

		if ( ! $post_id ) {
			return $orientation;
		}

		$meta = wp_get_attachment_metadata( $image_id );
		if ( $meta['width'] === $meta['height'] ) {
			$orientation = 'square';
		} elseif ( $meta['width'] > $meta['height'] ) {
			$orientation = 'landscape';
		} else {
			$orientation = 'portrait';
		}

		$taxonomy = Registrations::get_taxonomy( 'orientations' );

		$set = wp_set_object_terms( $post_id, $orientation, $taxonomy );
		if ( empty( $set ) || is_wp_error( $set ) ) {
			$orientation = false;
		} elseif ( 'object' === $return_type ) {
			$orientation = get_term( $set[0], $taxonomy );
		}

		return $orientation;
	}

	/**
	 * Returns the raw color assessment of the photo.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_raw_colors( $post_id ) {
		return get_post_meta( $post_id, 'vision_colors', true ) ?: [];
	}

	/**
	 * Returns the raw faces assessment of the photo.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_raw_faces( $post_id ) {
		return get_post_meta( $post_id, 'vision_faces', true ) ?: [];
	}

	/**
	 * Returns the raw labels assessment of the photo.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_raw_labels( $post_id ) {
		return get_post_meta( $post_id, 'vision_labels', true ) ?: [];
	}

	/**
	 * Returns the safe search assessment of the photo.
	 *
	 * @param int       $post_id The ID of the photo post type post.
	 * @return string[] Associative array of the safe search assessment of the
	 *                  likelihood of eacho of these five categories being
	 *                  present in the photo: adult, medical, racy, spoof,
	 *                  violence. Values can be UNKNOWN, VERY_UNLIKELY, UNLIKELY,
	 *                  POSSIBLE, LIKELY, and HIGHLY_LIKELY.
	 */
	public static function get_raw_moderation_assessment( $post_id ) {
		return get_post_meta( $post_id, 'vision_safe_search', true ) ?: [];
	}

	/**
	 * Returns an associative array of moderation flags and their likelihood of
	 * being relevant to the photo, filtered by likelihood, and slightly
	 * formatted.
	 *
	 * @param int   $post_id          The ID of the photo post type post.
	 * @param array $skip_likelihoods Likelihoods to skip due to not being of concern.
	 * @return array
	 */
	public static function get_filtered_moderation_assessment( $post_id, $skip_likelihoods = [ 'very_unlikely', 'unlikely' ] ) {
		$flags = [];
		$safe_search_flags = self::get_raw_moderation_assessment( $post_id );

		foreach ( $safe_search_flags as $flag => $likelihood ) {
			$likelihood = strtolower( $likelihood );
			if ( in_array( $likelihood, $skip_likelihoods ) ) {
				continue;
			}

			$flags[ $flag ] = $likelihood;
		}

		return $flags;
	}

	/**
	 * Returns the colors for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_colors( $post_id ) {
		return wp_get_post_terms( $post_id, Registrations::get_taxonomy( 'colors' ) );
	}

	/**
	 * Returns the categories for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_categories( $post_id ) {
		return wp_get_post_terms( $post_id, Registrations::get_taxonomy( 'categories' ) );
	}

	/**
	 * Returns the orientation for a post.
	 *
	 * Note: Since orientation is implemented as a taxonomy, it is possible to
	 * have more than one assigned, which doesn't make practical sense. This
	 * returns the first one returned, regardless of the number assigned.
	 *
	 * @param int $post_id    Photo post ID.
	 * @return WP_Term|string The term object, or if no orientation was set then
	 *                        an empty string.
	 */
	public static function get_orientation( $post_id ) {
		$orientations = wp_get_post_terms( $post_id, Registrations::get_taxonomy( 'orientations' ) );
		$orientation = ( $orientations && ! is_wp_error( $orientations ) )
			? $orientations[0]
			: self::set_orientation( get_post_thumbnail_id( $post_id ) );

		return $orientation;
	}

	/**
	 * Returns the tags for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public static function get_tags( $post_id ) {
		return wp_get_post_terms( $post_id, Registrations::get_taxonomy( 'tags' ) );
	}

	/**
	 * Returns EXIF data about the photo.
	 *
	 * @param int          $post_id  The post ID.
	 * @param array|string $exif_key EXIF attributes to return data for.
	 *                     Default empty array.
	 * @return array|string Associate array of EXIF attributes and an associate
	 *                      array with each attribute's value and label. Returns
	 *                      all EXIF data if $exif is empty, else the value of the
	 *                      EXIF attribute specified.
	 */
	public static function get_exif( $post_id, $exif_keys = [] ) {
		$image_id = get_post_thumbnail_id( $post_id );
		$metadata = wp_get_attachment_metadata( $image_id );
		if ( empty( $metadata['image_meta'] ) ) {
			return;
		}

		$exif = $metadata['image_meta'] ?? [];
		if ( $exif_keys ) {
			$keep = array_filter( (array) $exif_keys, function ( $item ) use ( $exif ) {
				return ! empty( $exif[ $item ] );
			} );
			if ( ! $keep ) {
				return;
			}
			$exif = array_filter( $exif, function ( $item ) use ( $keep ) {
				return in_array( $item, $keep );
			}, ARRAY_FILTER_USE_KEY );
		}
		$exifs_keys = array_keys( $exif );
		sort( $exifs_keys );
		$return = [];

		foreach ( $exifs_keys as $key ) {
			$label = ucfirst( $key );

			$value = $exif[ $key ];
			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}
			if ( ! $value || 'Array' === $value ) {
				continue;
			}

			switch ( $key ) {
				case 'aperture':
					$value = 'f/' . $value;
					break;
				case 'created_timestamp':
					$label = 'Created';
					$value = date( 'M jS, Y', $value );
					break;
				case 'focal_length':
					$label = 'Focal Length';
					$value .= 'mm';
					break;
				case 'iso':
					$label = 'ISO';
					break;
				case 'shutter_speed':
					$label = 'Shutter Speed';
					$value = '1/' . (int)( 1.0 / (float) $value );
					break;
			}

			$return[ $key ] = [
				'label' => $label,
				'value' => $value,
			];
		}

		return $return;
	}

	/**
	 * Returns the IP address for the photo contributor.
	 *
	 * @param int|WP_Post|null The photo post.
	 * @return string
	 */
	public static function get_contributor_ip( $post = null ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		return get_post_meta( $post->ID, Registrations::get_meta_key( 'contributor_ip' ), true );
	}

	/**
	 * Returns markup for a moderator link.
	 *
	 * If the moderator is the current user, then an unlinked "you" is used.
	 * Otherwise, if there is a moderator, then a link to the moderator's
	 * w.org profile is returned. If no moderator is recorded, then returns an
	 * empty string.
	 *
	 * @param int|WP_Post|null The photo post.
	 * @return string The link to the moderator's profile or an empty string if
	 *                no moderator.
	 */
	public static function get_moderator_link( $post = null ) {
		$post = get_post( $post );

		$moderator_id = (int) get_post_meta( $post->ID, Registrations::get_meta_key( 'moderator' ), true );

		if ( ! $moderator_id ) {
			return '';
		}

		$moderator = get_userdata( $moderator_id );

		if ( get_current_user_id() === $moderator_id ) {
			$link = '<b>you</b>';
		} else {
			$link = sprintf(
				'<span id="photo-moderator"><a href="%s">%s</a></span>',
				esc_url( 'https://profiles.wordpress.org/' . $moderator->user_nicename . '/' ),
				sanitize_text_field( $moderator->display_name )
			);
		}

		return $link;
	}

	/**
	 * Determines if faces have been detected in the photo.
	 *
	 * @param int|WP_Post|null The photo post.
	 * @return bool True if a face has been detected, else false.
	 */
	public static function has_faces( $post = null ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return false;
		}

		$faces = self::get_raw_faces( $post->ID );

		return ! empty( $faces['count'] );
	}

	/**
	 * Determines if a photo is controversial.
	 *
	 * This only applies for unpublished photos that also meet one of these
	 * criteria:
	 * - Post status is 'flagged'
	 * - Flagged by Vision as being "possible" or more likely in any criteria category
	 *
	 * @param int|WP_Post|null Optional. The post or attachment. Default null,
	 *                         indicating the current post.
	 * @return bool True if photo is controversial, else false.
	 */
	public static function is_controversial( $post = null ) {
		$post = get_post( $post );

		// If post is an attachment, get its parent.
		if ( 'attachment' === get_post_type( $post ) ) {
			$post = get_post( wp_get_post_parent_id( $post->ID ) );
		}

		if ( ! $post ) {
			return false;
		}

		$post_status = get_post_status( $post );

		// Not controversial if it has been published.
		if ( 'publish' === $post_status ) {
			return false;
		}

		// Not controversial if photo has been manually unflagged.
		if ( Flagged::was_unflagged( $post ) ) {
			return false;
		}

		// Controversial if photo is outright flagged.
		if ( Flagged::get_post_status() === $post_status ) {
			return true;
		}

		// Controversial if photo got flagged as 'possible' or more likely by Vision.
		$flags = self::get_filtered_moderation_assessment( $post->ID );
		if ( ! empty( $flags ) ) {
			return true;
		}

		return false;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Photo', 'init' ] );
