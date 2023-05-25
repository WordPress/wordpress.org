<?php
/**
 * Moderation functionality.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Moderation {

	const WPORG_PHOTO_DIRECTORY_ADMIN_EMAIL = 'photos@wordpress.org';

	/**
	 * User accounts registered within this number of days will get flagged as
	 * being new accounts.
	 *
	 * @var int
	 */
	const FLAG_IF_USER_ACCOUNT_NOT_THIS_MANY_DAYS_OLD = 14;

	/**
	 * The threshold percentage of the number of rejections relative to the
	 * number of approvals at which the user should be flagged as a warning
	 * (in orange). Should be a lower value than
	 * `FLAG_REJECTION_ALERT_THRESHOLD_PERCENTAGE`.
	 *
	 * @var float
	 */
	const FLAG_REJECTION_WARNING_THRESHOLD_PERCENTAGE = 0.1;

	/**
	 * The threshold percentage of the number of rejections relative to the
	 * number of approvals at which the user should be flagged as an alert
	 * (in red) rather than a warning (in orange). Should be a higher value than
	 * `FLAG_REJECTION_WARNING_THRESHOLD_PERCENTAGE`.
	 *
	 * @var float
	 */
	const FLAG_REJECTION_ALERT_THRESHOLD_PERCENTAGE = 0.25;

	/**
	 * Initializes component.
	 */
	public static function init() {
		$post_type = Registrations::get_post_type();

		add_filter( 'the_title',                     [ __CLASS__, 'change_prefix_from_private_to_awaiting_moderation' ], 10, 2 );
		add_filter( 'photo_column_data_end',         [ __CLASS__, 'output_moderation_flags' ] );
		add_action( 'wporg_photos_approve_post',     [ __CLASS__, 'send_approval_email' ] );
		add_action( 'wporg_photos_reject_post',      [ __CLASS__, 'send_rejection_email' ] );
		add_filter( 'user_has_cap',                  [ __CLASS__, 'maybe_grant_photo_moderator_caps' ], 11 );
		add_action( 'wporg_photos_flag_column_data', [ __CLASS__, 'show_flags' ] );
		add_action( 'wporg_photos_moderation_email_sent', [ __CLASS__, 'sent_user_email' ] );
		add_filter( 'wporg_photos_pre_upload_form',       [ __CLASS__, 'output_list_of_pending_submissions_for_user' ] );

		// Add column to users table with count of photos moderated.
		add_filter( 'manage_users_columns',               [ __CLASS__, 'add_moderated_count_column' ] );
		add_filter( 'manage_users_custom_column',         [ __CLASS__, 'handle_moderated_count_column_data' ], 10, 3 );

		// Modify Date column for photo posts table with name of moderator.
		add_action( 'post_date_column_time',              [ __CLASS__, 'add_moderator_to_date_column' ], 10, 3 );
	}

	/**
	 * Adds the 'Photos Moderator' role.
	 */
	public static function add_roles() {
		add_role(
			'photos_moderator',
			__( 'Photos Moderator', 'wporg-photos' ),
			[
				// Capabilities for photo posts.
				'read'                    => true,
				'edit_photos'             => true,
				'delete_photos'           => true,
				'publish_photos'          => true,
				'edit_others_photos'      => true,
				'delete_others_photos'    => true,
				'edit_published_photos'   => true,
				'delete_published_photos' => true,
				'edit_private_photos'     => true,
				'delete_private_photos'   => true,
				'read_private_photos'     => true,
				'upload_files'            => true,
				// Capabilities for posts and media.
				'edit_posts'              => true,
				'delete_posts'            => true,
				'publish_posts'           => true,
				'edit_others_posts'       => true,
				'delete_others_posts'     => true,
				'edit_published_posts'    => true,
				'delete_published_posts'  => true,
				'edit_private_posts'      => true,
				'delete_private_posts'    => true,
				'read_private_posts'      => true,
				'edit_post'               => true,
				'delete_post'             => true,
				'read_post'               => true,
			]
		);
	}

	/**
	 * Grants photos moderator caps to admins.
	 *
	 * @param  array $caps Array of user capabilities.
	 * @return array
	 */
	public static function maybe_grant_photo_moderator_caps( $caps ) {
		$is_caped = function_exists( 'is_caped' ) && is_caped( get_current_user_id() );

		if ( ! is_user_member_of_blog() && ! $is_caped ) {
			return $caps;
		}

		// Get current user's roles.
		$roles = (array) wp_get_current_user()->roles;

		if ( $is_caped || in_array( 'administrator', $roles ) ) {
			$photos_moderator_role = get_role( 'photos_moderator' );
			if ( $photos_moderator_role ) {
				$caps = array_merge( (array) $photos_moderator_role->capabilities, (array) $caps );
			}
		}

		return $caps;
	}

	/**
	 * Customizes the document title separator.
	 *
	 * @param string  $prepend Text displayed before the post title.
	 * @param WP_Post $post    Post object.
	 * @return string
	 */
	public static function change_prefix_from_private_to_awaiting_moderation( $prepend, $post ) {
		if (
			// Post is a photo post type.
			get_post_type( $post ) === Registrations::get_post_type()
		&&
			// Post is pending.
			isset( $post->post_status ) && 'pending' === $post->post_status
		) {
			/* translators: %s: Link to photo that is awaiting moderation. */
			$prepend = __( 'Awaiting Moderation: %s', 'wporg-photos' );
		}

		return $prepend;
	}

	/**
	 * Outputs list of moderation flags in the admin.
	 *
	 * @param WP_Post $post The post object.
	 * @param bool    $echo Echo list of moderation flags? Default true.
	 * @return string The list of moderation flags, empty if there aren't any.
	 */
	public static function output_moderation_flags( $post, $echo = true ) {
		$output = '';

		if (
			// Only do so in admin.
			is_admin()
		&&
			// Post exists.
			$post
		&&
			// Post is a photo post type.
			get_post_type( $post ) === Registrations::get_post_type()
		&&
			// Post is pending.
			'pending' === $post->post_status
		) {
			$flags = Photo::get_filtered_moderation_assessment( $post->ID );

			if ( $flags ) {
				$output = self::format_flags( $flags );

				if ( $echo ) {
					echo $output;
				}
			}
		}

		return $output;
	}

	/**
	 * Formats flags into a list for display.
	 *
	 * @param array $flags  Associative array of flags names (as keys) and
	 *                      severity (as values). Severity can be one of
	 *                      ['possible', 'likely', 'very_likely'].
	 * @return string
	 */
	public static function format_flags( $flags ) {
		if ( ! $flags ) {
			return '';
		}

		$formatted = '<ul class="photos-flagged">';
		foreach ( $flags as $flag => $class ) {
			$formatted .= sprintf(
				'<li class="dashicons-before dashicons-flag %s" title="%s">%s</li>' . "\n",
				esc_attr( $class ),
				/* translators: 1: Moderation category, 2: Likelihood of the image being of the given moderation category */
				sprintf( __( 'This image is flagged as potentially containing %1$s content: %2$s', 'wporg-photos' ), $flag, ucwords( str_replace( '_', ' ', $class ) ) ),
				ucwords( $flag )
			);
		}
		$formatted .= "</ul>\n";

		return $formatted;
	}

	/**
	 * Determines if a user has been emailed.
	 *
	 * @param WP_Post Post object.
	 * @return bool True if user has been emailed an approval or rejection,
	 *              else false.
	 */
	public static function has_user_been_emailed( $post ) {
		return (bool) get_post_meta( $post->ID, 'emailed_user', true );
	}

	/**
	 * Records that the contributing user was emailed about their submission.
	 *
	 * Currently does not differentiate between being emailed for an approval
	 * or a rejections.
	 *
	 * @param WP_Post Post object.
	 */
	public static function sent_user_email( $post ) {
		update_post_meta( $post->ID, 'emailed_user', true );
	}

	/**
	 * Sends the approval email for a photo.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function send_approval_email( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return;
		}

		// Bail if user has already been emailed.
		if  ( self::has_user_been_emailed( $post ) ) {
			return;
		}

		$user = get_user_by( 'id', $post->post_author );

		// Check for moderator's note to user.
		$mod_note = '';
		$mod_note_to_user = Rejection::get_moderator_note_to_user( $post );
		if ( $mod_note_to_user ) {
			$mod_note = "\n" . __( 'Message from the moderator:', 'wporg-photos' ) . "\n{$mod_note_to_user}\n";
		}

		// Get the content of the email.
		$subject = sprintf(
			'[%s] %s',
			__( 'WordPress Photo Directory', 'wporg-photos' ),
			__( 'Photo approved!', 'wporg-photos' )
		);
		$content = sprintf(
			/* translators: 1: user's display name, 2: original filename of photo, 3: URL to photo, 4: submission date, 5: image caption, 6: note to user from moderator */
			__(
'Hello %1$s,

Thank you for submitting a photo to the WordPress Photo Directory.

The photo (uploaded as %2$s) has been published and is now publicly available at:
%3$s

Submission date: %4$s
Caption: %5$s
%6$s

Feel free to submit another photo!

--
The WordPress Photo Directory Team
https://wordpress.org/photos/
', 'wporg-photos'
			),
			get_the_author_meta( 'display_name', $user->ID ),
			get_post_meta( $post->ID, Registrations::get_meta_key( 'original_filename' ), true ) ?: "(unknown)",
			get_permalink( $post ),
			get_the_date( 'Y-m-d', $post ),
			get_the_content( null, false, $post ) ?: __( '(none provided)', 'wporg-photos' ),
			$mod_note
		);

		wp_mail( $user->user_email, $subject, $content, 'From: ' . self::WPORG_PHOTO_DIRECTORY_ADMIN_EMAIL );

		do_action( 'wporg_photos_moderation_email_sent', $post, 'approval', $user );
	}

	/**
	 * Sends the rejection email for a photo.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function send_rejection_email( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return;
		}

		$user = get_user_by( 'id', $post->post_author );

		$rejection_message = '';

		// Add verbiage to acknowledge if photo was previously published prior
		// to its rejection.
		if ( self::has_user_been_emailed( $post ) ) {
			$rejection_message .= __( 'Though this photo had already been approved, we have since decided to decline it and have unpublished it from the site.', 'wporg-photos' ) . "\n";
		} else {
			$rejection_message .= __( 'The photo has been declined and will not be published to the site.', 'wporg-photos' ) . "\n";
		}

		// Check for specifics provided by the moderator regarding the rejection.
		$rejection_reason = Rejection::get_rejection_reason( $post );
		if ( $rejection_reason ) {
			// If rejecting due to 'submission-error', then send special email.
			if ( 'submission-error' === $rejection_reason ) {
				self::send_submission_error_email( $post );
				return;
			}

			$reason_explanation = Rejection::get_rejection_reasons( $rejection_reason, 'email' );
			if ( $reason_explanation ) {
				$rejection_message .= "\n" . $reason_explanation . "\n";
			}
		}

		// Check for moderator's note to user.
		$mod_note = Rejection::get_moderator_note_to_user( $post );
		if ( $mod_note ) {
			$rejection_message .= "\n" . __( 'Message from the moderator:', 'wporg-photos' ) . "\n" . $mod_note . "\n";
		}

		// Get the content of the email.
		$subject = sprintf(
			'[%s] %s',
			__( 'WordPress Photo Directory', 'wporg-photos' ),
			__( 'Photo declined.', 'wporg-photos' )
		);
		$content = sprintf(
			/* translators: 1: user's display name, 2: specific reason for rejection (already translated), 3: submission date, 4: original filename of photo, 5: image caption, 6: URL to guidelines */
			__(
'Hello %1$s,

Thank you for submitting a photo to the WordPress Photo Directory.

%2$s
Submission date: %3$s
Original filename: %4$s
Caption: %5$s

Please consult our submission guidelines at %6$s to ensure your next submission meets our criteria.

--
The WordPress Photo Directory Team
https://wordpress.org/photos/
', 'wporg-photos'
			),
			get_the_author_meta( 'display_name', $user->ID ),
			$rejection_message,
			get_the_date( 'Y-m-d', $post ),
			get_post_meta( $post->ID, Registrations::get_meta_key( 'original_filename' ), true ) ?: "(unknown)",
			get_the_content( null, false, $post ) ?: __( '(none provided)', 'wporg-photos' ),
			'https://wordpress.org/photos/guidelines/'
		);

		wp_mail( $user->user_email, $subject, $content, 'From: ' . self::WPORG_PHOTO_DIRECTORY_ADMIN_EMAIL );

		do_action( 'wporg_photos_moderation_email_sent', $post, 'rejection', $user );
	}

	/**
	 * Sends the submission error email for a photo.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function send_submission_error_email( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return;
		}

		// Bail if user has already been emailed.
		if  ( self::has_user_been_emailed( $post ) ) {
			return;
		}

		$user = get_user_by( 'id', $post->post_author );

		$rejection_message = '';

		// Check for specifics provided by the moderator regarding the rejection.
		$rejection_reason = Rejection::get_rejection_reason( $post );
		if ( $rejection_reason ) {
			$reason_explanation = Rejection::get_rejection_reasons( $rejection_reason, 'email' );
			if ( $reason_explanation ) {
				$rejection_message .= "\n" . $reason_explanation . "\n";
			}
		}

		// Check for moderator's note to user.
		$mod_note = Rejection::get_moderator_note_to_user( $post );
		if ( $mod_note ) {
			$rejection_message .= "\n" . __( 'Message from the moderator:', 'wporg-photos' ) . "\n" . $mod_note . "\n";
		}

		// Get the content of the email.
		$subject = sprintf(
			'[%s] %s',
			__( 'WordPress Photo Directory', 'wporg-photos' ),
			__( 'Submission error.', 'wporg-photos' )
		);
		$content = sprintf(
			/* translators: 1: user's display name, 2: specific reason for rejection (already translated), 3: submission date, 4: original filename of photo, 5: image caption, 6: URL to guidelines */
			__(
'Hello %1$s,

Thank you for attempting to submit a photo to the WordPress Photo Directory.
%2$s

Submission date: %3$s
Caption: %4$s


--
The WordPress Photo Directory Team
https://wordpress.org/photos/
', 'wporg-photos'
			),
			get_the_author_meta( 'display_name', $user->ID ),
			$rejection_message,
			get_the_date( 'Y-m-d', $post ),
			get_the_content( null, false, $post ) ?: __( '(none provided)', 'wporg-photos' )
		);

		wp_mail( $user->user_email, $subject, $content, 'From: ' . self::WPORG_PHOTO_DIRECTORY_ADMIN_EMAIL );

		do_action( 'wporg_photos_moderation_email_sent', $post, 'submission-error', $user );
	}

	/**
	 * Outputs all detected flags for a photo if the photo is still in moderation.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function show_flags( $post ) {
		$post_type = Registrations::get_post_type();

		// Bail if not photo post type or not pending.
		if ( get_post_type( $post ) !== $post_type || 'pending' !== $post->post_status ) {
			return;
		}

		$flags = [];

		// Flag if this is user's first submission.
		$published_photos_count = User::count_published_photos( $post->post_author );
		if ( ! $published_photos_count ) {
			$flags[ 'no published photo' ] = 'possible';
		}

		// Flag if a face has been detected in the photo (currently disallowed).
		if ( Photo::has_faces( $post ) ) {
			$flags[ 'face detected' ] = 'very_likely';
		}

		// Flag if user has past rejections.
		$rejections = Rejection::get_user_rejections( $post->post_author );
		if ( $rejections ) {
			$rejections_count = count( $rejections );

			// Don't count submission errors.
			$submission_errors_count = array_reduce( $rejections, function ( $count, $item ) {
				$reason = Rejection::get_rejection_reason( $item );
				if ( 'submission-error' === $reason ) {
					$count++;
				}
				return $count;
			}, 0 );
			$rejections_count -= $submission_errors_count;

			if ( $rejections_count > 0 ) {
				$rejections_level = '';

				// A user with more rejections than approvals should be an alert.
				if ( $rejections_count >= $published_photos_count ) {
					$rejections_level = 'very_likely';
				}
				// Specify as alert or warning based on count relative to alert threshold.
				else {
					$reject_pct = $rejections_count / $published_photos_count;
					if ( $reject_pct >= self::FLAG_REJECTION_ALERT_THRESHOLD_PERCENTAGE ) {
						$rejections_level = 'very_likely';
					}
					elseif ( $reject_pct >= self::FLAG_REJECTION_WARNING_THRESHOLD_PERCENTAGE ) {
						$rejections_level = 'possible';
					}
				}

				if ( $rejections_level ) {
					$flags[ sprintf( 'has rejections (<strong>%d</strong>)', $rejections_count ) ] = $rejections_level;
				}
			}
		}

		$user = get_user_by( 'id', $post->post_author );
		if ( ! $user ) {
			return;
		}

		// Flag if user account was created recently.
		$days_since_registration = round( ( time() - strtotime( $user->user_registered ) ) / ( 60 * 60 * 24 ) );
		if ( $days_since_registration <= self::FLAG_IF_USER_ACCOUNT_NOT_THIS_MANY_DAYS_OLD ) {
			$flags[ 'new user account' ] = 'possible';
		}

		echo self::format_flags( $flags );
	}

	/**
	 * Amends content with a list of submissions in the queue for a user.
	 *
	 * @param string $output  The content of the page so far.
	 * @param int    $user_id Optional. The user ID. Current user ID is used if not specified. Default ''.
	 * @return string
	 */
	public static function output_list_of_pending_submissions_for_user( $content, $user_id = '' ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Bail if no user.
		if ( ! $user_id ) {
			return $content;
		}

		$pending = get_posts( [
			'posts_per_page' => -1,
			'author'         => (int) $user_id,
			'post_status'    => 'pending',
			'post_type'      => Registrations::get_post_type(),
		] );

		// Bail if user does not have any pending posts.
		if ( ! $pending ) {
			return $content;
		}

		$content .= '<h2>' . __( 'Submissions awaiting moderation', 'wporg-photos' ) . "</h2>\n";
		$content .= '<p>';
		$max_pending_submissions = User::get_concurrent_submission_limit( $user_id );
		$content .= sprintf(
			_n( 'You can have up to <strong>%d</strong> photo in the moderation queue at a time. You currently have <strong>%d</strong>.', 'You can have up to <strong>%d</strong> photos in the moderation queue at a time. You currently have <strong>%d</strong>.', $max_pending_submissions,'wporg-photos' ),
			$max_pending_submissions,
			count( $pending )
		);
		$content .= "</p>\n";
		$content .= '<table id="wporg_photos_pending_submissions"><tr>';
		$content .= '<th>' . __( 'File', 'wporg-photos' ) . '</th>';
		$content .= '<th>' . __( 'Submission Date', 'wporg-photos' ) . '</th>';
		$content .= '<th>' . __( 'Caption', 'wporg-photos' ) . "</th></tr>\n";
		foreach ( $pending as $post ) {
			$content .= sprintf(
				"<tr><td>%s</td><td>%s</td><td>%s</td></tr>\n",
				get_post_meta( $post->ID, Registrations::get_meta_key( 'original_filename' ), true ) ?: __( "(unknown)", 'wporg-photos' ),
				get_the_date( 'Y-m-d', $post ),
				esc_html( get_the_content( null, false, $post ) ?: __( '(none provided)', 'wporg-photos' ) ),
			);
		}
		$content .= "</table>\n";

		return $content;
	}

	/**
	 * Adds a column to show the number of photos moderated by the user.
	 *
	 * @param array $posts_columns Array of post column titles.
	 * @return array
	 */
	public static function add_moderated_count_column( $column ) {
		$column[ 'moderated_count' ] = __( 'Moderated', 'wporg-photos' );

		return $column;
	}

	/**
	 * Outputs the Moderated column data for a particular user.
	 *
	 * @param string $output      Custom column output. Default empty.
	 * @param string $column_name Column name.
	 * @param int    $user_id     ID of the currently-listed user.
	 * @return string
	 */
	public static function handle_moderated_count_column_data( $output, $column_name, $user_id ) {
		if ( 'moderated_count' === $column_name ) {
			$query = new \WP_Query( [
				'fields'         => 'ids',
				'posts_per_page' => -1,
				'post_status'    => [ 'publish', Rejection::get_post_status() ],
				'post_type'      => Registrations::get_post_type(),
				'meta_query'     => [
					'relation' => 'OR',
					[
						'key'        => Registrations::get_meta_key( 'moderator' ),
						'value'      => $user_id,
					],
					[
						'key'        => 'rejected_by',
						'value'      => $user_id,
					],
				],
			] );

			$output = $query->found_posts;
		}

		return $output;
	}

	/**
	 * Amends the Date column for photo posts to include the moderator.
	 *
	 * @param string  $t_time      The published time.
	 * @param WP_Post $post        Post object.
	 * @param string  $column_name The column name.
	 * @return string
	 */
	public static function add_moderator_to_date_column( $t_time, $post, $column_name ) {
		if ( 'date' !== $column_name || Registrations::get_post_type() !== get_post_type( $post ) ) {
			return $t_time;
		}

		$moderator = Photo::get_moderator_link( $post );

		if ( $moderator ) {
			$t_time .= '<div class="photo-moderator">'
				. sprintf( __( 'Moderated by: %s', 'wporg-photos' ), $moderator )
				. '</div>';
		}

		return $t_time;
	}

}

register_activation_hook( WPORG_PHOTO_DIRECTORY_DIRECTORY . '/photo-directory.php', [ __NAMESPACE__ . '\Moderation', 'add_roles' ] );

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Moderation', 'init' ] );
