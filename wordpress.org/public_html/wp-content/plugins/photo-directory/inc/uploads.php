<?php
/**
 * Uploads handler.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Uploads {

	/**
	 * Maximum number of characters for a photo's description.
	 *
	 * @var int
	 */
	const MAX_LENGTH_DESCRIPTION = 250;

	/**
	 * The default maximum allowed file size in megabytes.
	 *
	 * @see `get_maximum_photo_file_size()` for actually retrieving the maximum
	 * photo size, as it may be filtered.
	 * @var int|float
	 */
	const MAX_PHOTO_FILE_SIZE = 20;

	/**
	 * The default minimum allowed file size in megabytes.
	 *
	 * @see `get_minimum_photo_file_size()` for actually retrieving the minimum
	 * photo size, as it may be filtered.
	 * @var int|float
	 */
	const MIN_PHOTO_FILE_SIZE = 1;

	/**
	 * The slug for the page used for photo uploads.
	 *
	 * @var string
	 */
	const SUBMIT_PAGE_SLUG = 'submit';

	/**
	 * Memoized value of file hash.
	 *
	 * @var string
	 */
	protected static $_hash;

	/**
	 * Photo validation error type.
	 *
	 * @var string
	 */
	protected static $photo_validation_error;

	/**
	 * Initializes component.
	 */
	public static function init() {
		/* Image restrictions. */

		add_filter( 'big_image_size_threshold',         [ __CLASS__, 'big_image_size_threshold' ] );

		/* Frontend Uploader customizations. */

		// Force settings to be certain values.
		if ( ! empty( $GLOBALS['frontend_uploader']->settings_slug ) ) {
			add_filter( 'option_' . $GLOBALS['frontend_uploader']->settings_slug, [ __CLASS__, 'override_fu_settings' ], 1 );
		}
		// Restrict upload mime types.
		add_filter( 'fu_allowed_mime_types',            [ __CLASS__, 'restrict_upload_mimes' ] );
		// Disable FU shortcodes unless logged in.
		add_action( 'do_shortcode_tag',                 [ __CLASS__, 'disable_frontend_uploader_shortcode_unless_logged_in' ], 10, 2 );
		// Overwrite default FU response notices.
		add_filter( 'fu_response_notices',              [ __CLASS__, 'customize_fu_response_notices' ] );

		/* Upload form display. */

		// Enqueue scripts.
		add_action( 'wp_enqueue_scripts',               [ __CLASS__, 'wp_enqueue_scripts' ] );
		// Disable upload form and show message if user not allowed to upload.
		add_filter( 'the_content',                      [ __CLASS__, 'insert_upload_disallowed_notice' ], 1 );
		// Insert upload form into submit page.
		add_filter( 'the_content',                      [ __CLASS__, 'insert_upload_form' ] );
		// Change some strings that may be used on submit page.
		add_action( 'wp',                               [ __CLASS__, 'handle_overwriting_fu_strings' ] );

		/* After submission, but before an upload initiates. */

		add_filter( 'fu_should_process_content_upload', [ __CLASS__, 'can_proceed_with_upload' ] );

		/* After submission, but before post is created. */

		add_filter( 'fu_before_create_post',            [ __CLASS__, 'make_post_pending_instead_of_private' ] );

		/* After submission, after an upload completes. */

		// Make photo the featured image for associated post.
		add_action( 'fu_after_upload',                  [ __CLASS__, 'make_uploaded_photo_featured' ], 1, 3 );
		// Validate the photo.
		add_action( 'fu_after_upload',                  [ __CLASS__, 'validate_uploaded_photo' ], 2, 3 );
		// Delete the associated post if the image upload failed.
		add_action( 'fu_upload_result',                 [ __CLASS__, 'delete_post_if_invalid_photo' ], 10, 2 );
		// Trigger action denoting successful upload.
		add_action( 'fu_upload_result',                 [ __CLASS__, 'trigger_photo_upload_complete' ], 10, 2 );
		// Override redirect if photo doesn't validate.
		add_filter( 'fu_upload_result_query_args',      [ __CLASS__, 'override_redirect_if_photo_validation_fails'], 10, 2 );

		/* After upload, after photo validates. */

		// Disable jpeg to webp converstion.
		add_filter( 'wp_upload_image_mime_transforms',  [ __CLASS__, 'disable_jpeg_to_webp' ] );

		// Set post fields for photo.
		add_action( 'wporg_photos_upload_success',      [ __CLASS__, 'set_post_fields' ], 100, 3 );
		// Store original file info.
		add_action( 'wporg_photos_upload_success',      [ __CLASS__, 'store_original_file_info' ], 10, 3 );
	}

	/**
	 * Returns the guidelines requirements that warrant a checklist on the submit
	 * form.
	 *
	 * @return array Array of guidelines, the keys being the checkbox field names
	 *               and the values being field labels.
	 */
	public static function get_guidelines_checklist() {
		return [
			'photo_copyright'          => __( 'I have the copyright or other legal right to upload this image.', 'wporg-photos' ),
			'photo_license'            => sprintf(
				/* translators: %s: Link to CC0 license */
				__( 'I am making this photo available under the <a href="%s">CC0 license</a>. People will be able to use this image for any purpose, including resale, marketing, branding, etc without cost or attribution.', 'wporg-photos' ),
				'https://creativecommons.org/share-your-work/public-domain/cc0/'
			),
			'photo_photograph'         => __( 'Photo is an actual photograph and not a screenshot or digital art.', 'wporg-photos' ),
			'photo_high_quality'       => __( 'Photo is high quality (well composed and lit, not blurry, etc).', 'wporg-photos' ),
			'photo_no_overlays'        => __( 'Photo does not contain overlays (watermarks, copyright notices, text, graphics, borders).', 'wporg-photos' ),
			'photo_not_overprocessed'  => __( 'Photo is not overprocessed (excessive photo editing or use of filters).', 'wporg-photos' ),
			'photo_no_collage'         => __( 'Photo is not a collage or composite of multiple photographs.', 'wporg-photos' ),
			'photo_no_extreme_content' => __( 'Photo does not depict violence, gore, hate, or sexual content.', 'wporg-photos' ),
			'photo_not_all_text'       => __( 'Photo must not consist mostly of text.', 'wporg-photos' ),
			'photo_not_others_art'     => __( 'Photo must not consist solely of the artwork of others (such as paintings, drawings, graffiti). ', 'wporg-photos' ),
			'photo_no_faces'           => __( 'Photo must not contain any human faces.', 'wporg-photos' ),
			'photo_privacy'            => __( "Photo must not potentially violate anyone's privacy (such as revealing home address, license plate, etc).", 'wporg-photos' ),
			'photo_no_variations'      => __( 'Photo must not be a minor variation of something you submitted to this site before.', 'wporg-photos' ),
		];
	}

	/**
	 * Overrides the settings for the Frontend Uploader plugin to ensure certain
	 * values are never changed from what is expected.
	 *
	 * Ensures:
	 * - Auto approval of any file is never enabled
	 * - Obfuscation of filenames is always enabled
	 *
	 * @param mixed  $value  Value of the option.
	 * @return array
	 */
	public static function override_fu_settings( $value ) {
		return array_merge( $value, [
			'auto_approve_any_files' => 'off',
			'obfuscate_file_name'    => 'on',
		] );
	}

	/**
	 * Returns the maximum image size.
	 *
	 * @param int $threshold The threshold value in pixels.
	 * @return int
	 */
	public static function big_image_size_threshold( $threshold ) {
		return 7500;
	}

	/**
	 * Returns the minimum dimension for an image's width or height.
	 *
	 * @return int
	 */
	public static function get_minimum_photo_dimension() {
		return 2000;
	}

	/**
	 * Returns the maximum allowed file size.
	 *
	 * @param bool $in_bytes Should the value returned be in bytes instead of megabytes?
	 * @return int|float Maximum allowed file size. Returns value in bytes by default, but
	 *             will return value in megabytes if `$as_bytes` is false.
	 */
	public static function get_maximum_photo_file_size( $as_bytes = true ) {
		/**
		 * Filters the maximum allowed photo file size, in megabytes.
		 *
		 * @param int The maximum allowed photo file size, in megabytes.
		 */
		$max_file_size = apply_filters( 'wporg_photos_max_photo_file_size', self::MAX_PHOTO_FILE_SIZE );

		if ( $as_bytes ) {
			$max_file_size = round( $max_file_size * 1024 * 1024 );
		}

		return $max_file_size;
	}

	/**
	 * Returns the minimum allowed file size.
	 *
	 * @param bool $in_bytes Should the value returned be in bytes instead of megabytes?
	 * @return int|float Minimum allowed file size. Returns value in bytes by default, but
	 *             will return value in megabytes if `$as_bytes` is false.
	 */
	public static function get_minimum_photo_file_size( $as_bytes = true ) {
		/**
		 * Filters the minimum allowed photo file size, in megabytes.
		 *
		 * @param int The minimum allowed photo file size, in megabytes.
		 */
		$min_file_size = apply_filters( 'wporg_photos_min_photo_file_size', self::MIN_PHOTO_FILE_SIZE );

		if ( $as_bytes ) {
			$min_file_size = round( $min_file_size * 1024 * 1024 );
		}

		return $min_file_size;
	}

	/**
	 * Enqueues scripts for the photo submit page.
	 */
	public static function wp_enqueue_scripts() {
		if ( is_page( self::SUBMIT_PAGE_SLUG ) ) {
			wp_enqueue_script( 'wporg-photos-submit', plugins_url( 'assets/js/submit.js', dirname( __FILE__ ) ), [], '1', true );

			wp_localize_script(
				'wporg-photos-submit',
				'PhotoDir',
				[
					'error_class'           => 'error',

					// Field required.
					'err_field_required'    => __( 'This field is required.', 'wporg-photos' ),

					// File extension.
					'err_invalid_mimetype' => __( 'Please select a JPEG image.', 'wporg-photos' ),

					// File size.
					'err_file_too_large'    => sprintf(
						__( 'The selected file cannot be larger than %s MB.', 'wporg-photos' ),
						self::get_maximum_photo_file_size( false )
					),
					'err_file_too_small'    => sprintf(
						__( 'The selected file must be larger than %s MB.', 'wporg-photos' ),
						self::get_minimum_photo_file_size( false )
					),
					'max_file_size' => self::get_maximum_photo_file_size(),
					'min_file_size' => self::get_minimum_photo_file_size(),

					// File dimensions.
					'err_file_too_long'     => sprintf(
						/** translators: %d: The maximum number of pixels. */
						__( 'The selected file cannot be longer in either length or width than %dpx.', 'wporg-photos' ),
						self::big_image_size_threshold( 0 )
					),
					'err_file_too_short'    => sprintf(
						/** translators: %d: The minimum number of pixels. */
						__( 'The selected file must be longer in both length and width than %dpx.', 'wporg-photos' ),
						self::get_minimum_photo_dimension()
					),
					'max_file_dimension' => self::big_image_size_threshold( 0 ),
					'min_file_dimension' => self::get_minimum_photo_dimension(),
					'msg_validating_dimensions' => __( 'Validating photo dimensions&hellip;', 'wporg-photos' ),

					// Toggle all confirmation checkboxes for upload.
					'user_can_toggle_all_checkboxes' => User::can_toggle_confirmation_checkboxes(),
					'toggle_all_checkboxes' => __( 'As a contributor of many photos, I am well aware of the requirements listed below and agree to them all.', 'wporg-photos' ),
				]
			);
		}
	}

	/**
	 * Determines if conditions are right for overwriting Frontend Uploader
	 * strings and hooks appropriately to do so.
	 */
	public static function handle_overwriting_fu_strings() {
		if ( is_page( self::SUBMIT_PAGE_SLUG ) && ! empty( $_GET['errors']['fu-disallowed-mime-type'] ) ) {
			// Hook as reasonably late as possible.
			add_filter( 'the_content', function ( $content ) {
				add_filter( 'gettext', [ __CLASS__, 'overwrite_fu_strings' ], 10, 3 );
				return $content;
			} );

			// Unhook as early as possible.
			add_action( 'fu_additional_html', function () { remove_filter( 'gettext', [ __CLASS__, 'overwrite_fu_strings' ] ); } );
		}
	}

	/**
	 * Changes some strings defined by Frontend Uploader plugin.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Text to translate.
	 * @param string $domain      Text domain. Unique identifier for retrieving translated strings.
	 * @return string
	 */
	public static function overwrite_fu_strings( $translation, $text, $domain ) {
		if ( 'frontend-uploader' === $domain ) {
			if ( $text === 'This kind of file is not allowed. Please, try again selecting other file.' ) {
				remove_filter( 'gettext', [ __CLASS__, 'overwrite_fu_strings' ] );
				$translation = __( 'This is not a supported image file. Please use a .jpg or .jpeg file.', 'wporg-photos' );
			}
		}

		return $translation;
	}

	/**
	 * Disables Frontend Uploader shortcodes unless user is logged in.
	 *
	 * @param string  $output Shortcode output.
	 * @param string  $tag    Shortcode name.
	 * @return string
	 */
	public static function disable_frontend_uploader_shortcode_unless_logged_in( $output, $tag ) {
		if ( ! is_user_logged_in() ) {
			$fu_shortcodes = [
				'fu-upload-form',
				'fu-upload-response',
			];

			if ( in_array( $tag, $fu_shortcodes ) ) {
				$output = '<p>'
					. sprintf(
						__( 'Please <a href="%s">log in or create an account</a> so you can upload a photo.', 'wporg-photos' ),
						esc_url( wp_login_url( get_permalink() ) ) )
					. '</p>';
			}
		}

		return $output;
	}

	/**
	 * Restricts upload mimetypes to only allow image mimetypes.
	 *
	 * @param array $mimes Array of mimetypes. Ignored.
	 * @return array
	 */
	public static function restrict_upload_mimes( $mimes = [] ) {
		return [
			'jpg|jpeg|jpe' => 'image/jpeg',
		];
	}

	/**
	 * Customizes upload error messages.
	 *
	 * @param array $notices Array of error notices.
	 * @return array
	 */
	public static function customize_fu_response_notices( $notices ) {
		$notices['fu-post-sent']['text'] = __( 'Your photo was successfully submitted', 'wporg-photos' );

		// Frontend Uploader doesn't permit fine-tuned error reporting. Check if
		// we've set a cookie indicating more specific reason for rejection. If
		// not, use generic message.
		$rejection = __( 'Your submission was flagged as unacceptable.', 'wporg-photos' );
		if ( ! empty( $_COOKIE['wporg_photos_reject_reason'] ) ) {
			switch ( $_COOKIE['wporg_photos_reject_reason'] ) {
				case 'checkbox_unchecked_copyright':
					$rejection = __( 'You must acknowledge your copyright of the photo.', 'wporg-photos' );
					break;
				case 'checkbox_unchecked_license':
					$rejection = __( 'You must agree to the license.', 'wporg-photos' );
					break;
				case 'concurrent-submission-limit':
					$rejection = __( 'You already have a submission awaiting moderation.', 'wporg-photos' );
					break;
				case 'duplicate-file':
					$rejection = __( 'Your submission appears to be a duplicate of something uploaded before.', 'wporg-photos' );
					break;
				case 'file-not-jpg':
					$rejection = __( 'Your submission must be an image in the JPEG format.', 'wporg-photos' );
					break;
				case 'file-too-large':
					$rejection = sprintf(
						__( 'The file size for your submission is too large. Please submit a photo smaller than %d MB in size.', 'wporg-photos' ),
						self::get_maximum_photo_file_size( false )
					);
					break;
				case 'file-too-long':
					$rejection = sprintf(
						__( 'Your submission is too large. Please submit a photo with length and width each smaller than %dpx.', 'wporg-photos' ),
						self::big_image_size_threshold( 0 )
					);
					break;
				case 'file-too-short':
					$rejection = sprintf(
						__( 'Your submission is too small. Please submit a photo with length and width each larger than %dpx.', 'wporg-photos' ),
						self::get_minimum_photo_dimension()
					);
					break;
				case 'file-too-small':
					$rejection = sprintf(
						__( 'The file size for your submission is too small. Please submit a photo larger than %d MB in size.', 'wporg-photos' ),
						self::get_minimum_photo_file_size( false )
					);
					break;
				case 'insufficient-dimension':
					$rejection = sprintf( __( 'Your photo must have a width and height of at least %d pixels each.', 'wporg-photos' ), self::get_minimum_photo_dimension() );
					break;
				case 'no-file-uploaded':
					$rejection = __( 'You must select a photo to upload.', 'wporg-photos' );
					break;
				case 'too-many-files':
					$rejection = __( 'You can only upload one photo at a time.', 'wporg-photos' );
					break;
				case 'upload-error':
					$rejection = __( 'There has been an error handling this submission. Feel free to try again, but if it persists try back again later.', 'wporg-photos' );
					break;
				case 'upload-error-partial':
					$rejection = __( 'There has been an error with this submission and it has only partially uploaded. Feel free to try again, but if it persists try back again later.', 'wporg-photos' );
					break;
				case 'upload-error-unknown':
					$rejection = __( 'There has been an unknown error handling this submission. Feel free to try again, but if it persists try back again later.', 'wporg-photos' );
					break;
			}
			unset( $_COOKIE['wporg_photos_reject_reason'] );
		}
		$notices['fu-spam']['text'] = $rejection;

		return $notices;
	}

	/**
	 * Saves posts created via Frontend Uploader as pending instead of private.
	 *
	 * @param array $post_array Array of post settings.
	 * @return array
	 */
	public static function make_post_pending_instead_of_private( $post_array ) {
		$post_array['post_status'] = 'pending';

		return $post_array;
	}

	/**
	 * Determines if the user has the ability to upload files at all.
	 *
	 * NOTE: This is akin to checking capability and not as a check if the user
	 * should be able to upload another file (e.g. this does not check if an
	 * upload limit has been reached).
	 *
	 * @return bool True if user can upload, else false.
	 */
	public static function user_can_upload() {
		require_once ABSPATH . '/wp-admin/includes/plugin.php';

		// Check killswitch setting that completely disables uploads.
		if ( Settings::is_killswitch_enabled() ) {
			return false;
		}

		$user = wp_get_current_user();
		$can = true;

		// Check if user is blocked.
		if ( $can && $user && $user->ID ) {
			$can = empty( $user->allcaps['bbp_blocked'] );
		}

		// The Frontend Uploader plugin must be active.
		$fu_plugin = 'frontend-uploader/frontend-uploader.php';
		if ( $can && ! is_plugin_active( $fu_plugin ) && ! is_plugin_active_for_network( $fu_plugin ) ) {
			$can = false;
		}

		// Google Cloud API must be configured.
		if ( $can ) {
			$can = VisionAPI::is_google_cloud_configured();
		}

		/**
		 * Filters if the user can specifically upload a file.
		 *
		 * @param bool    $can  Can the user upload files?
		 * @param WP_User $user The user.
		 */
		return (bool) apply_filters( 'wporg_photos_user_can_upload', $can, $user );
	}

	/**
	 * Determines if a user can proceed with a photo upload.
	 *
	 * `user_can_upload()` checks if the user generally has the ability to
	 * upload. This checks if the user can do so right now, taking into
	 * account submission limits, throttling, validity checks on the specific
	 * file being checked (as much as can be done without the file actually being
	 * uploaded yet), etc. Checks `user_can_upload()` as the first step.
	 *
	 * @param bool $can Can the user upload the photo?
	 * @return bool True if user can upload the photo, else false.
	 */
	public static function can_proceed_with_upload( $can ) {
		// Check if user is able to upload.
		if ( $can ) {
			$can = self::user_can_upload();
		}

		// Check if user has reached submission limit.
		if ( $can ) {
			$can = ! User::has_reached_concurrent_submission_limit();
			$reason = 'concurrent-submission-limit';
		}

		// Check if form validates.
		if ( $can ) {
			$validation_issue = self::validate_upload_form();
			if ( $validation_issue ) {
				$can = false;
				$reason = $validation_issue;
			}
		}

		// Check for duplicate file.
		if ( $can ) {
			if ( Photo::hash_exists( self::get_uploaded_file_hash() ) ) {
				$can = false;
				$reason = 'duplicate-file';
			}
		}

		if ( ! $can && $reason ) {
			setcookie( 'wporg_photos_reject_reason', $reason, time()+180, SITECOOKIEPATH, COOKIE_DOMAIN );
		}

		return $can;
	}

	/**
	 * Determines if the photo upload form field values are valid.
	 *
	 * @access protected
	 *
	 * @return string|false False if there are no validation issues with the
	 *                      upload form, else a string token representing the
	 *                      specific validation issue.
	 */
	protected static function validate_upload_form() {
		if ( ! empty( $_FILES['files']['error'][0] ) ) {
			switch ( $_FILES['files']['error'][0] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					return 'file-too-large';
				case UPLOAD_ERR_NO_FILE:
					return 'no-file-uploaded';
				case UPLOAD_ERR_PARTIAL:
					return 'upload-error-partial';
				case UPLOAD_ERR_NO_TMP_DIR:
				case UPLOAD_ERR_CANT_WRITE:
				case UPLOAD_ERR_EXTENSION:
					return 'upload-error';
				default:
					return 'upload-error-unknown';
			}
		}

		if ( empty( $_FILES['files']['tmp_name'][0] ) ) {
			return 'no-file-uploaded';
		}

		if ( ! empty( $_FILES['files']['tmp_name'] ) && count( $_FILES['files']['tmp_name'] ) > 1 ) {
			return 'too-many-files';
		}

		// Check file size.
		if ( ! empty( $_FILES['files']['size'][0] ) ) {
			$file_size = $_FILES['files']['size'][0];
			// Check if file is too large.
			//   (This is actually a fallback check in the event the MAX_FILE_SIZE
			//   directive in the upload form is missing or has been tampered with.
			//   Otherwise, PHP will already have invalidated a too-large upload.)
			if ( $file_size >= self::get_maximum_photo_file_size() ) {
				return 'file-too-large';
			}
			// Check if file is too small.
			if ( $file_size <= self::get_minimum_photo_file_size() ) {
				return 'file-too-small';
			}
		}

		list( $width, $length, $image_type ) = getimagesize( $_FILES['files']['tmp_name'][0] );

		// Check image type.
		if ( ! in_array( $image_type, [ IMG_JPG, IMG_JPEG ] ) ) {
			return 'file-not-jpg';
		}

		// Check image dimensions.
		$max_dimension = self::big_image_size_threshold( 0 );
		$min_dimension = self::get_minimum_photo_dimension();
		if ( $width > $max_dimension || $length > $max_dimension ) {
			return 'file-too-long';
		}
		if ( $width < $min_dimension || $length < $min_dimension ) {
			return 'file-too-short';
		}

		if ( ! isset( $_POST['photo_copyright'] ) || ! $_POST['photo_copyright'] ) {
			return 'checkbox_unchecked_copyright';
		}

		if ( ! isset( $_POST['photo_license'] ) || ! $_POST['photo_license'] ) {
			return 'checkbox_unchecked_license';
		}

		return false;
	}

	 /**
	 * Determines the hash of the uploaded file.
	 *
	 * @return string
	 */
	public static function get_uploaded_file_hash() {
		if ( ! self::$_hash && ! empty( $_FILES['files']['tmp_name'] ) ) {
			self::$_hash = md5_file( $_FILES['files']['tmp_name'][0] );
		}

		return self::$_hash;
	}

	/**
	 * Validates a photo after being uploaded.
	 *
	 * If valid, then triggers a success action.
	 *
	 * Note: Validations at this point probably involves needing the actual
	 * photo file. If possible, perform upload permission checks earlier in
	 * `can_proceed_with_upload()`.
	 *
	 * @param int[] $media_ids Media IDs for uploaded files.
	 * @param bool  $success   Was the upload successful?
	 * @param int   $post_id   The post ID.
	 */
	public static function validate_uploaded_photo( $media_ids, $success, $post_id ) {
		if ( ! $success || ! $media_ids || ! $post_id ) {
			return;
		}

		$reason = '';

		// Check that file is larger than minimum dimensions.
		$media_meta = wp_get_attachment_metadata( $media_ids[0] );
		$min_dimension = self::get_minimum_photo_dimension();
		if (
			empty( $media_meta['width'] )
		||
			$media_meta['width'] < $min_dimension
		||
			empty( $media_meta['height'] )
		||
			$media_meta['height'] < $min_dimension
		) {
			$success = false;
			$reason = 'insufficient-dimension';
		}

		// If the photo does not validate.
		if ( ! $success ) {
			// Delete photo post.
			// Note: Photo should be associated with post at this point and will
			// also get deleted.
			wp_delete_post( $post_id, true );

			// Trigger flag so an error redirect can be set.
			self::$photo_validation_error = $reason ?: 'error';

			return;
		}

		/**
		 * Fires when a photo is successfully uploaded and validates.
		 *
		 * @param int[] $media_ids Media IDs for uploaded files.
		 * @param bool  $success   Was the upload successful?
		 * @param int   $post_id   The post ID.
		 */
		do_action( 'wporg_photos_upload_success', $media_ids, $success, $post_id );
	}

	/**
	 * Overrides Frontend Uploader's redirect query args if uploaded photo isn't
	 * valid.
	 *
	 * @param array $query_args Query args to be used for redirect.
	 * @param array $result     Array of information on uploaded file.
	 * @return array
	 */
	public static function override_redirect_if_photo_validation_fails( $query_args, $result ) {
		if ( self::$photo_validation_error ) {
			$query_args = [
				'response' => 'fu-spam',
				'errors'   => [
					self::$photo_validation_error => 1,
				],
			];

			// Set reason for rejection.
			setcookie( 'wporg_photos_reject_reason', self::$photo_validation_error, time()+180, SITECOOKIEPATH, COOKIE_DOMAIN );

			// Unset the error type now that it has been handled.
			self::$photo_validation_error = null;
		}

		return $query_args;
	}

	/**
	 * Overrides post fields for photo post and its associated photo.
	 *
	 * Essentially sets post name and title based on the first part of the
	 * already-obfuscated filename.
	 *
	 * @param int[] $media_ids Media IDs for uploaded files.
	 * @param bool  $success   Was the upload successful?
	 * @param int   $post_id   The post ID.
	 */
	public static function set_post_fields( $media_ids, $success, $post_id ) {
		if ( ! $success || ! $media_ids || ! $post_id ) {
			return;
		}

		$photo = get_post( $media_ids[0] );
		if ( ! $photo ) {
			return;
		}

		// Determine new post slug and title based on obfuscated filename.
		$media_meta = wp_get_attachment_metadata( $photo->ID );
		$filename = ! empty( $media_meta['file'] ) ? pathinfo( $media_meta['file'] )['filename'] : '';
		// Use the first 10 (randomly generated) characters from the filename as the post title.
		$name = sanitize_title(	substr( str_replace( [ '-', '.' ], '', $filename ), 0, 10 ) );

		// @todo Check for the unlikely event that there is a post name collision.
		// If there is, progressively use next characters until there isn't one.
		// This is only for a cleaner URL; WP of course will ensure slug is unique.

		$post = get_post( $post_id );

		if ( is_object( $post ) ) {
			$post->post_name  = $name;
			$post->post_title = $name;
			wp_update_post( $post );

			// Change the same fields in the attachment to obfuscate the original
			// filename.
			$photo_name = wp_unique_post_slug( $name . '-photo', $photo->ID, $photo->post_status, $photo->post_type, $post->ID );
			$photo->post_name = $photo_name;
			$photo->post_title = $photo_name;
			wp_update_post( $photo );
		}
	}

	/**
	 * Stores information about originally uploaded file such as filename and
	 * filesize.
	 *
	 * @param int[] $media_ids Media IDs for uploaded files.
	 * @param bool  $success   Was the upload successful?
	 * @param int   $post_id   The post ID.
	 */
	public static function store_original_file_info( $media_ids, $success, $post_id ) {
		if ( ! $success || ! $media_ids || ! $post_id ) {
			return;
		}

		$file = array_shift( $_FILES );

		update_post_meta( $post_id, Registrations::get_meta_key( 'original_filename' ), $file['name'][0] );
		update_post_meta( $post_id, Registrations::get_meta_key( 'original_filesize' ), $file['size'][0] );

		// Store hash of file.
		update_post_meta( $post_id, Registrations::get_meta_key( 'file_hash' ), self::get_uploaded_file_hash() );
	}

	/**
	 * Makes the uploaded photo the featured image of its associated post.
	 *
	 * @param int[] $media_ids Media IDs for uploaded files.
	 * @param bool  $success   Was the upload successful?
	 * @param int   $post_id   The post ID.
	 */
	public static function make_uploaded_photo_featured( $media_ids, $success, $post_id ) {
		if ( ! $media_ids || ! $success || ! $post_id ) {
			return;
		}

		set_post_thumbnail( $post_id, $media_ids[0] );
	}

	/**
	 * Deletes the associated post for a photo if the photo ends up being invalid.
	 *
	 * Frontend Uploader creates the associated post before handling the upload,
	 * so the post will be present even if the upload is rejected.
	 *
	 * @param string $layout Form layout.
	 * @param array  $result {
	 *     Associative array of result data.
	 *
	 *     @type int   $post_id   Post ID.
	 *     @type array $media_ids Array of media IDs.
	 *     @type bool  $success   True if the upload was a success, else false.
	 *     @type array $errors    Array of errors.
	 * }
	 */
	public static function delete_post_if_invalid_photo( $layout, $result ) {
		if (
			// Frontend Uploader is configured to associate upload with a post.
			in_array( $layout, [ 'post_image', 'post_media' ] )
		&&
			// Upload was not successful.
			! $result['success']
		) {
			// A post was created.
			if ( ! empty( $result['post_id'] ) ) {
				wp_delete_post( $result['post_id'], true );
			} else {
				wp_delete_attachment( $result['media_ids'][0], true );
			}
		}
	}

	/**
	 * Outputs a notice for users who are prevented from uploading and prevents
	 * the upload form from being output.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function insert_upload_disallowed_notice( $content ) {
		if ( is_page( self::SUBMIT_PAGE_SLUG ) && ! self::user_can_upload() ) {
			$content .= '<div class="photo-upload-disabled">' . __( 'Photo uploading is currently disabled.', 'wporg-photos' ) . '</div>';

			// Prevent outupt of upload form.
			remove_filter( 'the_content', [ __CLASS__, 'insert_upload_form' ] );
		}

		return $content;
	}

	/**
	 * Inserts the upload form to the photo submission page.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public static function insert_upload_form( $content ) {
		$post_type = Registrations::get_post_type();
		$description_shortcode = self::MAX_LENGTH_DESCRIPTION > 250 ? 'textarea' : 'input type="text"';

		if ( is_page( self::SUBMIT_PAGE_SLUG ) ) {
			$content .= apply_filters( 'wporg_photos_pre_upload_form', $content );

			$content .= '<fieldset id="wporg-photo-upload">';

			$content .= sprintf(
				'[fu-upload-form form_layout="post_media" post_type="%s" title="%s" suppress_default_fields="true"]' . "\n",
				esc_attr( $post_type ),
				esc_attr( __( 'Upload your photo', 'wporg-photos' ) )
			);

			if ( User::has_reached_concurrent_submission_limit() ) {
				$content .= '<p>' . __( 'Thanks for your submissions! Please wait until a photo is approved by moderators before submitting again.', 'wporg-photos' ) . '</p>';
			} else {
				$valid_upload_mimetypes = implode( ',', array_values( self::restrict_upload_mimes() ) );
				$content .= '<input type="hidden" name="post_title" value="" id="ugc-input-post_title" />' . "\n";
				$content .= sprintf(
					'<input type="hidden" name="MAX_FILE_SIZE" value="%s" id="ugc-input-max_file_size" />' . "\n",
					self::get_maximum_photo_file_size()
				);
				$content .= '<div class="ugc-input-wrapper">' . "\n"
				. sprintf(
					'<label for="ug_photo">%s</label>' . "\n",
					esc_attr( __( 'Photo', 'wporg-photos' ) )
				)
				. sprintf(
					'<input type="file" name="files[]" id="ug_photo" value="" required="true" aria-required="true" accept="%s">' . "\n",
					esc_attr( $valid_upload_mimetypes )
				)
				. "</div>\n"
				. sprintf(
					'[%s name="post_content" class="textarea" id="ug_content" description="%s" maxlength="%d" help="%s"]' . "\n",
					$description_shortcode,
					esc_attr( __( 'Description (optional)', 'wporg-photos' ) ),
					self::MAX_LENGTH_DESCRIPTION,
					esc_attr( sprintf( __( 'Maximum of %d characters. No HTML.', 'wporg-photos' ), self::MAX_LENGTH_DESCRIPTION ) )
				)
				. '<div class="upload-checkbox-wrapper">' . "\n";

				// Checklist of guideline requirements.
				$content .= '<p><strong>' . __( 'I confirm that:', 'wporg-photos' ) . '</strong></p>';
				foreach ( self::get_guidelines_checklist() as $key => $desc ) {
					$content .= sprintf(
						'<div><label><input type="checkbox" name="%s" required="required" /> %s</label></div>',
						esc_attr( $key ),
						$desc
					);
				}

				$content .= "</div>\n"; // End upload-checkbox-wrapper.

				$content .= sprintf(
					'[input type="submit" class="button-primary" value="%s"]' . "\n",
					esc_attr( __( 'Submit', 'wporg-photos' ) )
				)
				. '[recaptcha]' . "\n";
			}

			$content .= '[/fu-upload-form]</fieldset>' . "\n";
		}

		return $content;
	}

	/**
	 * Fires an action that indicates a photo was fully successfully uploaded.
	 *
	 * This helps to prevent code outside of this file was needing to be aware
	 * of the use of Frontend Uploader.
	 *
	 * @param string $layout Form layout.
	 * @param array  $result {
	 *     Associative array of result data.
	 *
	 *     @type int   $post_id   Post ID.
	 *     @type array $media_ids Array of media IDs.
	 *     @type bool  $success   True if the upload was a success, else false.
	 *     @type array $errors    Array of errors.
	 * }
	 */
	public static function trigger_photo_upload_complete( $layout, $result ) {
		if (
			// Upload was successful.
			$result['success']
		&&
			// Media image was created.
			! empty( $result['media_ids'] )
		&&
			// A post was created.
			! empty( $result['post_id'] )
		) {
			do_action( 'wporg_photos_photo_upload_complete', $result['media_ids'][0], $result['post_id'] );
		}
	}

	/**
	 * Disables conversion of uploaded jpegs to webp.
	 *
	 * This is required as webp appears to use a lot of memory for conversion, often running out
	 * of memory during upload on WordPress.org. Additionally, we don't use/expose webp at present.
	 * This may be only required temporarily, see the below Core Trac ticket for confirmation.
	 *
	 * @see https://meta.trac.wordpress.org/ticket/6142
	 * @see https://core.trac.wordpress.org/ticket/55443
	 *
	 * @param array $transforms The mime type transforms for uploads.
	 * @return array The modified $transforms.
	 */
	public static function disable_jpeg_to_webp( $transforms ) {
		if ( isset( $transforms['image/jpeg'] ) ) {
			$transforms['image/jpeg'] = [ 'image/jpeg' ];
		}

		return $transforms;
	}
}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Uploads', 'init' ] );
