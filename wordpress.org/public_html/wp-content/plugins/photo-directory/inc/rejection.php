<?php
/**
 * Photo submission rejection functionality.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Rejection {

	/**
	 * Name for form action for rejecting photo post.
	 *
	 * @var string
	 */
	public static $action = 'reject-photo';

	/**
	 * Array of reason types and their explanations (suitable for dropdown use).
	 *
	 * @var string[]
	 */
	protected static $rejection_reasons = [];

	/**
	 * Initializes component.
	 */
	public static function init() {
		self::$rejection_reasons = [
			''              => [
				'label' => __( 'Do not reject', 'wporg-photos' ),
				'email' => '',
			],
			'general'       => [
				'label' => __( 'General/nonspecific', 'wporg-photos' ),
				'email' => '', // No specific reason will be conveyed to contributor.
			],
			'image_quality' => [
				'label' => __( 'Insufficient image quality (e.g. blurriness, composition, lighting, lens issues)', 'wporg-photos' ),
				'email' => __( 'The photo had an issue regarding image quality. Submissions should be of high quality composition, lighting, focus, and color. The image should be free of blur (for the primary subject), noise, lens flare, glare, and spots due to water or dirt on the lens.', 'wporg-photos' ),
			],
			'not_a_photo'    => [
				'label' => __( 'Not a photo (e.g. screenshot, digital art)', 'wporg-photos' ),
				'email' => __( 'The image did not appear to be a photograph. We do not accept screenshots, digital art, or other non-photographic images.', 'wporg-photos' ),
			],
			'collage'        => [
				'label' => __( 'Collage or composite image', 'wporg-photos' ),
				'email' => __( 'The image appeared to be a collage or composite of separate images.', 'wporg-photos' ),
			],
			'overlays'       => [
				'label' => __( 'Overlays, watermark, borders, or other additions', 'wporg-photos' ),
				'email' => __( 'The photo included an overlay of some form (e.g. graphic, text, watermark, border).', 'wporg-photos' ),
			],
			'image_subject' => [
				'label' => __( 'Image subject matter', 'wporg-photos' ),
				'email' => __( 'The photo included subject matter of insufficient quality.', 'wporg-photos' ),
			],
			'image_extreme' => [
				'label' => __( 'Violence, gore, hate, or sexual content', 'wporg-photos' ),
				'email' => __( 'The photo depicted some element of violence, gore, hate, or sexual content.', 'wporg-photos' ),
			],
			'text'          => [
				'label' => __( 'Predominantly text', 'wporg-photos' ),
				'email' => __( 'The photo was predominantly text. Please refrain from submitting photos where text is a significant element of the photo.', 'wporg-photos' ),
			],
			'overprocessed' => [
				'label' => __( 'Overprocessed', 'wporg-photos' ),
				'email' => __( 'The photo appeared to be overprocessed with filters or other photo adjustments. We prefer minimal processing.', 'wporg-photos' ),
			],
			'anothers_art' => [
				'label' => __( 'Predominantly another piece of art', 'wporg-photos' ),
				'email' => __( 'The photo appeared to largely consist of the art of another person. We respect the rights of other artists by not distributing reproductions of their work.', 'wporg-photos' ),
			],
			'faces'         => [
				'label' => __( 'Contains human face(s)', 'wporg-photos' ),
				'email' => __( 'The photo contained one or more human faces. We do not currently accept photos that show human faces, wholly or partially, even if facial features cannot clearly be identified.', 'wporg-photos' ),
			],
			'privacy'       => [
				'label' => __( 'Potentially violates privacy', 'wporg-photos' ),
				'email' => __( 'The photo contained potentially privacy-violating material such as a home address, license plate, or other form of personal identification.', 'wporg-photos' ),
			],
			'variation'     => [
				'label' => __( 'Minor variation of already published photo', 'wporg-photos' ),
				'email' => __( 'The photo is a minor variation of something you have already had published to the site. This can be the same subject matter taken from a different angle, from slightly before or after in time, with a different composition or cropping, or staged or edited differently.', 'wporg-photos' ),
			],
			'submission-error' => [ // This specific key is referenced in code, so make related updates if renaming.
				'label' => __( 'Submission error', 'wporg-photos' ),
				/* translators: %s: URL to meta.trac to report bugs. */
				'email' => sprintf(
					__( 'There appears to have been an error with your submission and the photo never fully uploaded. This could be caused by a broken internet connection, network issues, or a glitch somewhere. Please retry your submission. If this is not your first notice regarding this image, try another. If you have anything to report in terms of errors encountered while uploading, please report them to us at %s.', 'wporg-photos' ),
					'https://meta.trac.wordpress.org/newticket?component=Photo%20Directory'
				),
			],
			'other'         => [
				'label' => __( 'Reason specified below', 'wporg-photos' ),
				'email' => '',
			],
		];

		$post_type = Registrations::get_post_type();

		// Register custom post status.
		add_action( 'init',                                    [ __CLASS__, 'register_post_status' ], 1 );

		// Register post meta.
		add_action( 'init',                                    [ __CLASS__, 'register_meta' ] );
		add_filter( 'is_protected_meta',                       [ __CLASS__, 'is_protected_meta' ], 10, 2 );

		// Customize post row actions.
		add_action( 'post_row_actions',                        [ __CLASS__, 'post_row_actions' ], 10, 2 );

		// Hide 'Restore' bulk action for deleted photo posts.
		add_filter( "bulk_actions-edit-{$post_type}",          [ __CLASS__, 'remove_bulk_restore' ] );

		// Prevent deletion of photos (unless already rejected) by disabling capability to do so.
		add_filter( 'map_meta_cap',                            [ __CLASS__, 'remove_delete_post_cap' ], 10, 4 );

		// Prevent publication of photos once rejected.
		add_filter( 'map_meta_cap',                            [ __CLASS__, 'remove_publish_photos_cap' ], 10, 4 );

		// Prevent untrashing of post unless it was previously rejected.
		add_filter( 'pre_untrash_post',                        [ __CLASS__, 'prevent_untrash' ], 10, 3 );

		// Only allow untrashing of post to rejected status.
		add_filter( 'wp_untrash_post_status',                  [ __CLASS__, 'untrash_post_status' ], 10, 3 );

		// Process post update as a rejection if rejection reason is provided.
		add_action( 'load-post.php',                           [ __CLASS__, 'set_post_status_on_rejection' ], 1 );

		// Prevent republication once rejected or trashed.
		add_filter( 'wp_insert_post_data',                     [ __CLASS__, 'prevent_undesired_post_status_changes' ], 10, 2 );

		// Add info regarding rejection (e.g. user, date) to post edit.
		add_action( 'post_submitbox_misc_actions',             [ __CLASS__, 'show_rejection_info' ] );

		// Add rejection fields to post edit.
		add_action( 'post_submitbox_start',                    [ __CLASS__, 'post_submitbox_start' ] );

		// Save rejection fields.
		add_filter( 'edit_post_' . Registrations::get_post_type(), [ __CLASS__, 'save_post_meta' ], 10, 2 );

		// Trigger action if post was just rejected or approved.
		add_action( 'post_updated',                            [ __CLASS__, 'trigger_moderation_action' ], 5, 3 );

		// Add columns (moderator, date, reason, etc) to rejection view.
		add_filter( 'manage_posts_columns',                    [ __CLASS__, 'posts_columns' ], 8, 2 );
		add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'custom_rejection_columns' ], 10, 2 );

		// Log the user and datetime when a photo gets rejected.
		add_action( 'wporg_photos_reject_post',                [ __CLASS__, 'log_rejection' ], 1 );
		add_action( 'wporg_photos_reject_post',                [ __CLASS__, 'delete_associated_photo_media' ], 1 );
		add_action( 'wporg_photos_reject_post',                [ __CLASS__, 'delete_taxonomy_associations' ], 1 );

		// Use JS to inject rejected post status into post submit box.
		add_action( 'admin_footer',                            [ __CLASS__, 'output_js_to_modify_post_status_in_submitbox_dropdown' ] );
	}

	/**
	 * Returns the rejection post status.
	 *
	 * @return string The rejection post status.
	 */
	public static function get_post_status() {
		return 'reject';
	}

	/**
	 * Returns the meta keys used by the plugin.
	 *
	 * @return array Meta key names and their attributes.
	 */
	public static function get_meta_keys() {
		return [
			'rejected_by' => [
				'meta_config' => [
					'type'              => 'integer',
					'description'       => __( 'The user who rejected the photo', 'wporg-photos' ),
					'sanitize_callback' => 'absint',
				],
			],
			'rejected_on' => [
				'meta_config' => [
					'description' => __( 'The datetime of when the photo was rejected', 'wporg-photos' ),
				],
			],
			'rejected_reason' => [
				'input_in_metabox' => true,
				'meta_config'      => [
					'description' => __( 'The string token of the reason for the photo rejection.', 'wporg-photos' ),
				],
			],
			'moderator_note_to_user' => [
				'input_in_metabox' => true,
				'meta_config'      => [
					'description' => __( 'A message sent to the user by the moderator within the approval/rejection email.', 'wporg-photos' ),
				],
			],
			'moderator_private_note' => [
				'input_in_metabox' => true,
				'meta_config'      => [
					'description' => __( 'A private message associated with the photo intended solely for moderators.', 'wporg-photos' ),
				],
			],
		];
	}

	/**
	 * Returns the ID of the user who rejected the post.
	 *
	 * @param int|WP_Post The post or post ID.
	 * @param string      Type of data to return. Accepts 'integer' for user ID
	 *                    'object' for WP_User object, or 'link' for link markup
	 *                    for user's w.org profile. Default 'object'.
	 * @return int|WP_User|false
	 */
	public static function get_rejection_user( $post, $return_type = 'object' ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return false;
		}

		$user_id = (int) get_post_meta( $post->ID, 'rejected_by', true );

		if ( ! $user_id ) {
			return false;
		}

		$return = $user_id;

		if ( in_array( $return_type, [ 'link', 'object' ] ) ) {
			$user = get_user_by( 'id', (int) $user_id );
		}

		if ( 'object' === $return_type ) {
			$return = $user;
		} elseif ( 'link' === $return_type ) {
			$return = sprintf(
				'<a href="%s">%s</a>',
				esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename . '/' ),
				'@' . $user->user_nicename
			);
		}

		return $return;
	}

	/**
	 * Returns the formatted datetime of when the post got rejected.
	 *
	 * @param int|WP_Post The post or post ID.
	 * @param string      Format of the date to return (using PHP date format
	 *                    syntax). Default 'Y/m/d g:s a'.
	 * @return string
	 */
	public static function get_rejection_date( $post, $format = 'Y/m/d g:s a' ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		$rejected_on = get_post_meta( $post->ID, 'rejected_on', true );

		if ( $rejected_on ) {
			$rejected_on = mysql2date( $format, sanitize_text_field( $rejected_on ) );
		}

		return $rejected_on;
	}

	/**
	 * Returns the array of rejection reasons or a specific rejection reason.
	 *
	 * @param string $reason Optional. A specific rejection type for which
	 *                       info should be returned. Empty string returns all
	 *                       data for all rejection reasons. Default ''.
	 * @param string $field  Optional. If a specific reason is specified, this
	 *                       is the specific attribute of the reason to return.
	 *                       Empty string returns all data for reason. Default ''.
	 * @return array|string
	 */
	public static function get_rejection_reasons( $reason = '', $field = '' ) {
		// Return all reasons if one wasn't specified.
		if ( ! $reason ) {
			return self::$rejection_reasons;
		}

		// Bail if reason requested is not valid.
		if ( ! isset( self::$rejection_reasons[ $reason ] ) ) {
			return [];
		}

		// Return all of reason's data if a specific field wasn't specified.
		if ( ! $field ) {
			return self::$rejection_reasons[ $reason ];
		}

		return self::$rejection_reasons[ $reason ][ $field ] ?? '';
	}

	/**
	 * Returns the reason why a post was rejected.
	 *
	 * If a post is rejected but has no explicitly stored reason, then the reason
	 * of 'general' is implied.
	 *
	 * @param int|WP_Post The post or post ID.
	 * @return string The key value from the `$rejection_reasons` array
	 *                corresponding to the reason for the rejection.
	 */
	public static function get_rejection_reason( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		$default_reason = 'general';
		$rejection_reason = get_post_meta( $post->ID, 'rejected_reason', true );

		// If no rejection reason is stored, but post is rejected, assume 'general'.
		if ( ! $rejection_reason && self::is_post_rejected( $post ) ) {
			$rejection_reason = $default_reason;
		}

		// If reason present, verify it is a valid reason. If not, use default.
		if ( $rejection_reason && ! self::get_rejection_reasons( $rejection_reason ) ) {
			$rejection_reason = $default_reason;
		}

		return $rejection_reason;
	}

	/**
	 * Returns the note the moderator has for the user.
	 *
	 * @param int|WP_Post The post or post ID.
	 * @return string
	 */
	public static function get_moderator_note_to_user( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		return get_post_meta( $post->ID, 'moderator_note_to_user', true );
	}

	/**
	 * Returns the private note left by the moderator.
	 *
	 * @param int|WP_Post The post or post ID.
	 * @return string
	 */
	public static function get_moderator_private_note( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return '';
		}

		return get_post_meta( $post->ID, 'moderator_private_note', true );
	}

	/**
	 * Returns the rejections for a given user.
	 *
	 * @param int $user_id User ID.
	 * @return WP_Post[] Array of rejected photo posts.
	 */
	public static function get_user_rejections( $user_id ) {
		return get_posts( [
			'posts_per_page' => 99,
			'author'         => (int) $user_id,
			'post_status'    => Rejection::get_post_status(),
			'post_type'      => Registrations::get_post_type(),
		] );
	}

	/**
	 * Is a given post rejectable?
	 *
	 * @param int|WP_Post $post The post or post ID.
	 * @return bool True if post is rejectable, else false.
	 */
	public static function is_post_rejectable( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		// Only photo post type can be rejected.
		return Registrations::get_post_type() === get_post_type( $post );
	}

	/**
	 * Is a given post rejected?
	 *
	 * @param int|WP_Post $post The post or post ID.
	 * @return bool True if post is rejected, else false.
	 */
	public static function is_post_rejected( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}

		if ( self::get_post_status() === get_post_status( $post ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Registers the custom 'rejection' post status.
	 */
	public static function register_post_status() {
		// Status of 'rejected' indicates a photo that was rejected from appearing on the site.
		register_post_status( self::get_post_status(), array(
				'label'                     => __( 'Rejected', 'wporg-photos' ),
				'public'                    => false,
				'exclude_from_search'       => true,
				'show_in_admin_all_list'    => false,
				'show_in_admin_status_list' => true,
				'label_count'               => _n_noop( 'Rejected <span class="count">(%s)</span>', 'Rejected <span class="count">(%s)</span>', 'wporg-photos' ),
		) );
	}

	/**
	 * Registers the post meta fields.
	 */
	public static function register_meta() {
		$default_config = [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'single'            => true,
			'auth_callback'     => [ __CLASS__, 'current_user_can_delete_photos' ],
			'show_in_rest'      => false,
		];

		$post_type = Registrations::get_post_type();

		foreach ( self::get_meta_keys() as $meta_key => $config ) {
			register_post_meta( $post_type, $meta_key, wp_parse_args( $config[ 'meta_config' ] ?? [], $default_config ) );
		}
	}

	/**
	 * Determines if the current user has the capability to delete photos, which
	 * is the primary basis for being a photo moderator.
	 *
	 * @return bool True if current user can delete photos, else false.
	 */
	public static function current_user_can_delete_photos() {
		return current_user_can( 'delete_photos' );
	}

	/**
	 * Hides meta keys from the custom field dropdown.
	 *
	 * @param bool   $protected Is the meta key protected?
	 * @param string $meta_key  The meta key.
	 * @return bool True if meta key is protected, else false.
	 */
	public static function is_protected_meta( $protected, $meta_key ) {
		return in_array( $meta_key, array_keys( self::get_meta_keys() ) ) ? true : $protected;
	}

	/**
	 * Prevents a photo post from being untrashed unless it was previously rejected.
	 *
	 * A trashed post should always be transitioning from the 'reject' status,
	 * but this is an additional safeguard to ensure a post can't be restored to
	 * a published or publishable state.
	 *
	 * @param bool|null $untrash         Whether to go forward with untrashing.
	 * @param WP_Post   $post            Post object.
	 * @param string    $previous_status The status of the post at the point where it was trashed.
	 */
	public static function prevent_untrash( $untrash, $post, $previous_status ) {
		// Bail if untrash is already being prevented.
		if ( false === $untrash ) {
			return $untrash;
		}

		// Bail if not of photo post type.
		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return $untrash;
		}

		// Prevent untrashing if the previous post status was rejected.
		if ( self::get_post_status() !== $previous_status ) {
			return false;
		}

		// Allow untrashing.
		return $untrash;
	}

	/**
	 * Potentially changes handling of post update to be as a rejection.
	 */
	public static function set_post_status_on_rejection() {
		// Bail if post isn't being updated.
		if ( empty( $_POST ) ) {
			return;
		}

		// Bail if not editing a post.
		if ( empty( $_POST['action'] ) || 'editpost' !== $_POST['action'] ) {
			return;
		}

		$nonce_field = 'photo-rejection-nonce';

		// Bail if expected data isn't present.
		if ( empty( $_POST['reject'] ) || empty( $_POST[ $nonce_field ] ) || empty( $_POST['post_type'] ) || empty( $_POST['post_ID'] ) ) {
			return;
		}

		// Bail if post is not a photo post.
		if ( Registrations::get_post_type() !== $_POST['post_type'] ) {
			return;
		}

		// Bail if nonce check fails.
		if ( ! wp_verify_nonce( $_POST[ $nonce_field ], 'photo-rejection-post-save-' . (int) $_POST['post_ID'] ) ) {
			return;
		}

		// Bail if current user can't reject photo posts.
		if ( ! self::current_user_can_delete_photos() ) {
			return;
		}

		// Change post status.
		$_POST['post_status'] = self::get_post_status();
	}

	/**
	 * Prevents photo posts from being changed to any other status
	 * than rejected or trashed once rejected or trashed.
	 *
	 * @param array $data    An array of slashed, sanitized, and processed post data.
	 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
	 * @return array
	 */
	public static function prevent_undesired_post_status_changes( $data, $postarr ) {
		// Bail if post isn't being updated.
		if ( empty( $postarr['ID'] ) ) {
			return $data;
		}

		// Bail if post is not a photo post.
		if ( Registrations::get_post_type() !== $data['post_type'] ) {
			return $data;
		}

		// Bail if post didn't have a previous status.
		$previous_status = get_post_field( 'post_status', $postarr['ID'] );
		if ( ! $previous_status ) {
			return $data;
		}

		// Bail if post status didn't change.
		$new_status = $data['post_status'];
		if ( $previous_status === $new_status ) {
			return $data;
		}

		$reject_status = self::get_post_status();

		// If post was previously rejected...
		if ( $reject_status === $previous_status ) {
			// It can only change if getting trashed.
			if ( 'trash' !== $new_status ) {
				$new_status = $reject_status;
			}
		}
		// Else if post was previously trashed...
		elseif ( 'trash' === $previous_status ) {
			// It can only change if being restored as rejected.
			if ( $reject_status !== $new_status ) {
				$new_status = 'trash';
			}
		}

		$data['post_status'] = $new_status;

		return $data;
	}

	/**
	 * Sets untrash post status for photo posts to be the rejected status.
	 *
	 * Posts in the trash should never get restored to anything but rejected.
	 *
	 * @param string $new_status      The new status of the post being restored.
	 * @param int    $post_id         The ID of the post being restored.
	 * @param string $previous_status The status of the post at the point where it was trashed.
	 */
	public static function untrash_post_status( $new_status, $post_id, $previous_status ) {
		if ( Registrations::get_post_type() === get_post_type( $post_id ) ) {
			$new_status = self::get_post_status();
		}

		return $new_status;
	}

	/**
	 * Modifies post row actions for photo post.
	 *
	 * - Removes 'Trash' link from photos
	 * - Removes 'Quick Edit' and 'View' links from rejected photos
	 * - Removes 'Restore' link from photos unless restoring would convert photo to rejected
	 *
	 * @param array   $actions Existing post row actions.
	 * @param WP_Post $post    The post.
	 * @return array
	 */
	public static function post_row_actions( $actions, $post ) {
		// Bail if post isn't rejectable in the first place.
		if ( ! self::is_post_rejectable( $post ) ) {
			return $actions;
		}

		// If the photo is already in the trash.
		if ( 'trash' === get_post_status( $post ) ) {
			$previous_status = get_post_meta( $post->ID, '_wp_trash_meta_status', true );

			// Remove the 'Restore' link unless a restore would be to 'reject' status.
			if ( ! $previous_status || self::get_post_status() !== $previous_status ) {
				unset( $actions['untrash'] );
			}
		}
		// Handle if post is already rejected.
		elseif ( self::is_post_rejected( $post ) ) {
			// Remove the 'Quick Edit' link.
			unset( $actions['inline hide-if-no-js'] );

			// Remove the 'View' link.
			unset( $actions['view'] );
		}
		// Else modify post row actions for a rejectable post.
		else {
			// Remove the 'Trash' link.
			unset( $actions['trash'] );
		}

		return $actions;
	}

	/**
	 * Removes the ability to delete photo posts.
	 *
	 * Photo posts must first be rejected (and ideally remain rejected) rather
	 * than get deleted since we'd lose valuatble information about the rejection
	 * such who submitted, when they submitted, why it was rejected, who rejected
	 * it, and the hash of the photo to prevent resubmissions of the photo.
	 *
	 * @param string[] $caps    Primitive capabilities required of the user.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id The user ID.
	 * @param array    $args    Adds context to the capability check, typically
	 *                          starting with an object ID.
	 */
	 public static function remove_delete_post_cap( $caps, $cap, $user_id, $args ) {
		// Bail if not 'delete_post' cap and post not specified.
		if ( 'delete_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}

		$post = $args[0];

		// Bail if already rejected (in order to allow deletion).
		if ( self::is_post_rejected( $post ) ) {
			return $caps;
		}

		// Bail if not for the photo post type.
		if ( get_post_type( $post ) !== Registrations::get_post_type() ) {
			return $caps;
		}

		// Bail if not in the trash.
		if ( get_post_status( $post ) === 'trash' ) {
			return $caps;
		}

		$caps[] = 'do_not_allow';

		return $caps;
	}

	/**
	 * Removes the ability to publish photos once rejected.
	 *
	 * Once rejected, photo posts will have had their submitted photo deleted,
	 * publishing the post is no longer a viable path for the post. If no
	 * post is associated with the capability check, and no post is global, then
	 * the capability isn't disallowed since user may be able to publish photos
	 * in other contexts.
	 *
	 * @param string[] $caps    Primitive capabilities required of the user.
	 * @param string   $cap     Capability being checked.
	 * @param int      $user_id The user ID.
	 * @param array    $args    Adds context to the capability check, typically
	 *                          starting with an object ID.
	 */
	public static function remove_publish_photos_cap( $caps, $cap, $user_id, $args ) {
		// Bail if not 'publish_photos' cap.
		if ( 'publish_photos' !== $cap ) {
			return $caps;
		}

		// If post not specified, try global post.
		if ( ! empty( $args[0] ) ) {
			$post = $args[0];
		} else {
			global $post;
		}

		// Bail if no post.
		if ( ! $post ) {
			return $caps;
		}

		// Disallow if post is rejected.
		if ( self::is_post_rejected( $post ) ) {
			$caps[] = 'do_not_allow';
		}

		return $caps;
	}

	/**
	 * Outputs who rejected a post and when they did so using markup suitable
	 * for use within the post submitbox.
	 */
	public static function show_rejection_info() {
		global $post;

		// Don't show if the post is not rejected.
		if ( ! self::is_post_rejected( $post ) ) {
			return;
		}

		$rejection_user = self::get_rejection_user( $post, 'link' );
		$rejection_date = self::get_rejection_date( $post, 'Y-m-d H:m:s' );

		if ( ! $rejection_user ) {
			$rejection_user = __( 'unknown', 'wporg-photos' );
		}

		if ( ! $rejection_date ) {
			$rejection_date = __( 'unknown', 'wporg-photos' );
		}

		echo '<div class="misc-pub-section curtime misc-pub-curtime">';
		printf( __( 'Rejected by: %s', 'wporg-photos' ), '<b>' . $rejection_user . '</b>' );
		echo '<br>';
		printf( __( 'Rejected on: %s', 'wporg-photos' ), '<b>' . $rejection_date . '</b>' );
		echo '</div>';
	}

	/**
	 * Outputs form fields used for rejecting a post.
	 */
	public static function post_submitbox_start() {
		global $post;

		// Bail if post is not rejectable.
		if ( ! self::is_post_rejectable( $post ) ) {
			return;
		}

		// Bail if user cannot reject photos.
		if ( ! self::current_user_can_delete_photos() ) {
			return;
		}

		$selected = self::get_rejection_reason( $post );
		$is_disabled = in_array( get_post_status( $post ), [ 'trash', self::get_post_status() ] );

		echo "<style>
			.reject-fields textarea { width: 100%; }
			.reject-fields select,
			.reject-fields textarea { margin-bottom: 1rem; }

			.reject-action { float: left; }
			.reject-action.post-is-rejected { float: right; }
			.reject-action input[type=submit] { background-color: #b32d2e; border-color: #b32d2e; color: white; }
			.reject-action.post-is-rejected input[type=submit] { background-color: #2271b1; border-color: #2271b1; }
			.reject-action input[type=submit]:hover { background-color: #8f2424; border-color: #8f2424; color: white; }
		</style>\n";

		echo '<div class="reject-fields">';

		wp_nonce_field( 'photo-rejection-post-save-' . $post->ID, 'photo-rejection-nonce' );

		// Markup for selecting reason to reject post.
		echo <<<JS
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				// Bind changing of value of rejection reason dropdown to toggle whether reject or publish button is enabled.
				const rejectSelect = document.querySelector('#rejected_reason');
				rejectSelect.addEventListener('change', (event) => {
					const hasRejectReason = Boolean(event.target.value);
					// Publish button should be disabled if a rejection reason is selected.
					document.querySelector('#publish').disabled = hasRejectReason;
					// Reject button should be disabled if no rejection reason is selected.
					document.querySelector('#reject-post').disabled = !hasRejectReason;
				});

				// Fire the select change event so the Reject button gets disabled initially.
				// (Could be done via 'disabled' attribute, but wouldn't allow rejecting if JS is disabled.)
				rejectSelect.dispatchEvent(new Event('change'));
			} );
		</script>
JS;

		echo '<label for="rejected_reason">' . __( 'Reject due to:', 'wporg-photos' ) . '<br>';
		printf(
			'<select id="rejected_reason" name="rejected_reason"%s>',
			disabled( true, $is_disabled, false )
		);
		foreach ( self::get_rejection_reasons() as $reason => $args ) {
			printf(
				'<option value="%s"%s>%s</option>' . "\n",
				esc_attr( $reason ),
				selected( $selected, $reason, false ),
				sanitize_text_field( $args['label'] )
			);
		}
		echo '</select></label>';

		echo '<div class="reject-additional-fields">';

		// Markup for optional note to send to user in rejection email.
		echo '<label for="moderator_note_to_user">' . __( '(Optional) Note to user:', 'wporg-photos' );
		echo '<p class="description"><em>' . __( 'Included in approval/rejection email.', 'wporg-photos' ) . '</em></p>';
		echo '<textarea id="moderator_note_to_user" name="moderator_note_to_user" rows="4"' . disabled( true, $is_disabled, false ) . '>';
		echo esc_textarea( self::get_moderator_note_to_user( $post ) );
		echo '</textarea>';
		echo '</label>';

		// Markup for optional private note for moderator-eyes only.
		echo '<label for="moderator_private_note">' . __( '(Optional) Private moderators-only note:', 'wporg-photos' );
		echo '<textarea id="moderator_private_note" name="moderator_private_note" rows="4">';
		echo esc_textarea( self::get_moderator_private_note( $post ) );
		echo '</textarea>';
		echo '</label>';

		echo "</div></div>\n";

		$is_rejected = self::is_post_rejected( $post );

		printf( '<div class="reject-action%s">', $is_rejected ? ' post-is-rejected' : '' );
		printf(
			'<input type="submit" name="reject" id="reject-post" value="%s" class="button button-large">',
			$is_rejected ? esc_attr__( 'Update', 'wporg-photos' ) : esc_attr__( 'Reject', 'wporg-photos' )
		);
		echo '</div>';
	}

	/**
	 * Saves rejection-related metabox fields on post save.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_post_meta( $post_id, $post ) {
		// Bail if doing an autosave.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Bail if post is not rejectable.
		if ( ! self::is_post_rejectable( $post_id ) ) {
			return;
		}

		$nonce_field = 'photo-rejection-nonce';

		// Bail if not POST or no nonce provided.
		if ( ! $_POST || empty( $_POST[ $nonce_field ] ) ) {
			return;
		}

		// Bail if nonce check fails.
		if ( ! wp_verify_nonce( $_POST[ $nonce_field ], 'photo-rejection-post-save-' . $post_id ) ) {
			return;
		}

		// Bail if not allowed.
		if ( ! self::current_user_can_delete_photos() ) {
			return;
		}

		// Bail if auto-draft or trashed post.
		if ( in_array( get_post_status( $post_id ), [ 'auto-draft', 'trash' ] ) ) {
			return;
		}

		// Update the value of the custom fields.
		foreach ( self::get_meta_keys() as $meta_key => $config ) {
			// Skip if meta key is not intended as an input in post's metabox.
			if ( empty( $config[ 'input_in_metabox' ] ) ) {
				continue;
			}

			$value = $_POST[ $meta_key ] ?? '';

			if ( $value ) {
				$value = wp_strip_all_tags( $value );
			}

			if ( $value ) {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}
	}

	/**
	 * Fires off the 'wporg_photos_approve_post' or 'wporg_photos_reject_post'
	 * action if a post is getting approved/rejected.
	 *
	 * @param int     $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update
	 * @param WP_Post $post_before Post object before the update.
	 */
	public static function trigger_moderation_action( $post_id, $post_after, $post_before ) {
		// Bail if not a photo post.
		if ( get_post_type( $post_id ) !== Registrations::get_post_type() ) {
			return;
		}

		$old_status = $post_before->post_status;
		$new_status = $post_after->post_status;

		// Bail if status is not changing.
		if ( $old_status === $new_status ) {
			return;
		}

		// Bail if old status is trash.
		if ( 'trash' === $old_status ) {
			return;
		}

		if ( 'publish' === $new_status ) {
			do_action( 'wporg_photos_approve_post', $post_after );
		} elseif ( self::get_post_status() === $new_status ) {
			do_action( 'wporg_photos_reject_post', $post_after );
		}
	}

	/**
	 * Adds additional columns to the admin post listing table for rejected photos.
	 *
	 * Adds a column to display the name of the person who posted the job, as well as a
	 * a column with their email address.
	 *
	 * @param array $columns Associative array of column names and labels
	 * @return array Amended associated array of column names and labels
	 */
	public static function posts_columns( $columns, $post_type ) {
		if (
			Registrations::get_post_type() !== $post_type
			|| empty( $GLOBALS['post_status'] )
			|| self::get_post_status() !== $GLOBALS['post_status']
		) {
			return $columns;
		}

		$columns[ 'rejected_by' ] = __( 'Rejected by', 'wporg-photos' );
		$columns[ 'rejected_on' ] = __( 'Rejected on', 'wporg-photos' );
		$columns[ 'rejected_reason' ] = __( 'Rejected for', 'wporg-photos' );

		return $columns;
	}

	/**
	 * Outputs the contents of the custom admin post listing columns for a rejected photos.
	 *
	 * @param string $column_name The column name
	 * @param int $post_id The post ID
	 */
	public static function custom_rejection_columns( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'rejected_by':
				echo self::get_rejection_user( $post_id, 'link' );
				break;
			case 'rejected_on':
				echo self::get_rejection_date( $post_id );
				break;
			case 'rejected_reason':
				echo self::get_rejection_reason( $post_id );
				// Add asterisk to denote there was a moderator note to user.
				if ( self::get_moderator_note_to_user( $post_id ) ) {
					echo '*';
				}
				break;
		}
	}

	/**
	 * Hides 'Restore' (aka untrash) bulk action for deleted photo posts.
	 *
	 * A photo post needs an associated photo media. But the media has been
	 * deleted via the rejection process prior to the post appearing in the
	 * trash, so the post cannot be restored.
	 *
	 * @param array $actions An array of the available bulk actions.
	 * @return array
	 */
	public static function remove_bulk_restore( $actions ) {
		if ( 'trash' === get_query_var( 'post_status' ) ) {
			unset( $actions['untrash'] );
		}

		return $actions;
	}

	/**
	 * Records the date a post was rejected and the user who rejected the post.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function log_rejection( $post ) {
		// Can only save rejecting user ID if one can be obtained.
		if ( $current_user_id = get_current_user_id() ) {
			update_post_meta( $post->ID, 'rejected_by', $current_user_id );
		}

		// Save date of rejection.
		update_post_meta( $post->ID, 'rejected_on', current_time( 'mysql' ) );
	}

	/**
	 * Deletes the associated photo from the media library.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function delete_associated_photo_media( $post ) {
		Posts::delete_attachments( $post->ID, $post );
	}

	/**
	 * Deletes the data associated with a photo post.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function delete_taxonomy_associations( $post ) {
		wp_delete_object_term_relationships( $post->ID, Registrations::get_taxonomy( 'all' ) );
	}

	/**
	 * Outputs JS to modify the submitbox post status dropdown.
	 *
	 * - For rejected posts, removes all post status but 'Rejected'.
	 * - Adds the 'Rejected' post status.
	 */
	public static function output_js_to_modify_post_status_in_submitbox_dropdown() {
		global $post;

		// Bail if post is not rejectable.
		if ( ! self::is_post_rejectable( $post ) ) {
			return;
		}

		$status_label     = __( 'Rejected', 'wporg-photos' );
		$visibility_label = __( 'Hidden', 'wporg-photos' );
		$value = self::get_post_status();
		$selected = selected( $value, get_post_status( $post ), false );

		// If post is rejected, remove all existing post statuses from dropdown.
		if ( self::is_post_rejected( $post ) ) {
			echo <<<JS
			<script>
			document.addEventListener('DOMContentLoaded', function () {
				// Remove the 'Submit for Review' button.
				document.querySelector("#publishing-action").remove();

				// Remove the 'Preview' button.
				document.querySelector("#preview-action").remove();

				// Add rejected post status to status display.
				document.querySelector(".misc-pub-post-status #post-status-display").innerText = "{$status_label}";

				// Change visibility display to indicate it is hidden.
				document.querySelector(".misc-pub-visibility #post-visibility-display").innerText = "{$visibility_label}";
			} );
			</script>

JS;
		}
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Rejection', 'init' ] );
