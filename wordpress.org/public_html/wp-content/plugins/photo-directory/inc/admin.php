<?php
/**
 * Admin customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Admin {

	const COL_NAME_FLAG  = 'wporg-flags';
	const COL_NAME_PHOTO = 'wporg-photo';
	const COL_NAME_CONTRIBUTOR_IP = 'wporg-contributor-ip';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		$post_type = Registrations::get_post_type();

		add_action( 'admin_init',                              [ __CLASS__, 'remove_menu_pages' ] );
		add_filter( 'is_protected_meta',                       [ __CLASS__, 'is_protected_meta' ], 10, 2 );
		add_action( 'add_meta_boxes',                          [ __CLASS__, 'add_photos_meta_boxes' ], 10, 2 );
		add_action( 'load-edit.php',                           [ __CLASS__, 'add_admin_css' ] );
		add_action( 'load-post.php',                           [ __CLASS__, 'add_admin_css' ] );
		add_filter( "manage_{$post_type}_posts_columns",       [ __CLASS__, 'add_photo_column' ] );
		add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'handle_photo_column_data' ], 10, 2 );
		add_filter( "manage_{$post_type}_posts_columns",       [ __CLASS__, 'add_flags_column' ] );
		add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'handle_flags_column_data' ], 10, 2 );
		add_filter( "manage_edit-{$post_type}_columns",        [ __CLASS__, 'remove_columns_from_pending_photos' ] );
		add_filter( 'post_row_actions',                        [ __CLASS__, 'add_post_action_photo_links' ], 10, 2 );
		add_filter( 'the_author',                              [ __CLASS__, 'add_published_photos_count_to_author' ] );
		add_filter( 'use_block_editor_for_post_type',          [ __CLASS__, 'disable_block_editor' ], 10, 2 );
		add_action( 'admin_notices',                           [ __CLASS__, 'add_notice_to_photo_media_if_pending' ] );
		add_filter( 'add_menu_classes',                        [ __CLASS__, 'add_admin_menu_pending_indicator' ] );
		add_filter( "manage_taxonomies_for_{$post_type}_columns", [ __CLASS__, 'remove_orientations_column' ], 10, 2 );

		// Record and display photo contributor IP address.
		add_action( 'transition_post_status',                  [ __CLASS__, 'record_contributor_ip' ], 10, 3 );
		add_filter( "manage_{$post_type}_posts_columns",       [ __CLASS__, 'add_contributor_ip_column' ] );
		add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'handle_contributor_ip_column_data' ], 10, 2 );

		// Record and display moderator.
		add_action( 'transition_post_status',                  [ __CLASS__, 'record_moderator' ], 10, 3 );
		add_action( 'post_submitbox_misc_actions',             [ __CLASS__, 'show_moderator' ] );
		add_action( 'attachment_submitbox_misc_actions',       [ __CLASS__, 'attachment_submitbox_additions' ], 11 );

		// Show user card for photo contributor.
		add_action( 'do_meta_boxes',                           [ __CLASS__, 'register_photo_contributor_metabox' ], 1, 3 );

		// Admin notice if killswitch enabled.
		add_action( 'admin_notices',                           [ __CLASS__, 'add_notice_if_killswitch_enabled' ] );

		// Admin notice related to flagging/unflagging.
		add_action( 'admin_notices',                           [ __CLASS__, 'add_notice_if_flagged_or_unflagged' ] );

		// Admin notice related to failed publication due to missing taxonomy values.
		add_action( 'admin_notices',                           [ __CLASS__, 'add_notice_if_publish_failed_due_to_missing_taxonomies' ] );

		// Modify admin menu links for photo posts.
		add_action( 'admin_menu',                              [ __CLASS__, 'modify_admin_menu_links' ] );

		// Restrict Media Library access.
		add_action( 'admin_init',                              [ __CLASS__, 'restrict_media_library_access' ] );
		add_action( 'admin_menu',                              [ __CLASS__, 'disable_media_library' ] );

		// Navigate to next post after moderation.
		add_action( 'edit_form_top',                           [ __CLASS__, 'show_moderation_message' ] );
		add_filter( 'redirect_post_location',                  [ __CLASS__, 'redirect_to_next_post_after_moderation' ], 5, 2 );

		// Add class(es) to body tag.
		add_filter( 'admin_body_class',                        [ __CLASS__, 'add_body_class' ] );

		// Disable visual editor.
			// Prevent the visual editor from being loaded.
			add_filter( 'user_can_richedit',                   [ __CLASS__, 'disable_rich_editing' ] );
			// Force the default editor to be the HTML editor.
			add_filter( 'wp_default_editor',                   [ __CLASS__, 'force_text_editor' ], 99 );
	}

	/**
	 * Outputs admin notice if submissions are currently disabled due to the killswitch.
	 */
	public static function add_notice_if_killswitch_enabled() {
		if ( ! Settings::is_killswitch_enabled() ) {
			return;
		}

		printf(
			'<div id="message" class="notice notice-warning"><p>%s</p></div>' . "\n",
			/* translators: %s: URL to settings page for enabling/disabling photo uploads. */
			sprintf(
				__( '<strong>Photo uploads are currently disabled for all users!</strong> Uncheck <a href="%s">the setting</a> to re-enable uploading.', 'wporg-photos' ),
				esc_url( admin_url( 'options-media.php' ) . '#' . Settings::KILLSWITCH_OPTION_NAME )
			)
		);
	}

	/**
	 * Outputs admin notice indicating if a photo post is flagged or has been unflagged.
	 */
	public static function add_notice_if_flagged_or_unflagged() {
		$screen = get_current_screen();

		// Only add notice when editing single photo.
		$post_type = Registrations::get_post_type();
		if ( ! $screen || $post_type !== $screen->id || $post_type !== $screen->post_type ) {
			return;
		}

		$notice = $user = $user_id = '';
		$notice_type = 'warning';

		$post = get_post();
		if ( ! $post ) {
			return;
		}

		// Don't need to report flagging/unflagging for published posts.
		if ( 'published' === $post->post_status ) {
			return;
		}

		// Note: Not reporting who flagged a post once it has been unflagged.
		if ( $user_id = Flagged::get_unflagger( $post ) ) {
			/* translators: 1: URL to the profile of the user who unflagged the photo, 2: The name of the user who unflagged the photo. */
			$notice = __( '<strong>This photo was unflagged by <a href="%1$s">%2$s</a> and is safe to moderate.', 'wporg-photos' );
			$notice_type = 'success';
		}
		elseif ( $user_id = Flagged::get_flagger( $post ) ) {
			// A user can't actually flag their own submission. This results from auto-flagging.
			if ( $user_id === $post->post_author ) {
				$notice = __( '<strong>This photo was automatically flagged due to potential concerns after image analysis.', 'wporg-photos' );
			} else {
				/* translators: 1: URL to the profile of the user who flagged the photo, 2: The name of the user who flagged the photo. */
				$notice = __( '<strong>This photo was flagged by <a href="%1$s">%2$s</a>.', 'wporg-photos' );
			}
		}

		if ( $user_id ) {
			$user = new \WP_User( $user_id );
		}

		if ( ! $user  || ! $notice ) {
			return;
		}

		printf(
			'<div id="message" class="notice notice-%s"><p>%s</p></div>' . "\n",
			esc_attr( $notice_type ),
			sprintf(
				$notice,
				'https://profiles.wordpress.org/' . $user->user_nicename . '/',
				sanitize_text_field( $user->display_name )
			)
		);
	}

	/**
	 * Outputs admin notice if a photo post publication failed due to any custom
	 * taxonomy not having a value assigned.
	 */
	public static function add_notice_if_publish_failed_due_to_missing_taxonomies() {
		global $post;

		$meta_key = Posts::META_KEY_MISSING_TAXONOMIES;

		if ( isset( $post->ID ) ) {
			$missing_taxonomies = get_post_meta( $post->ID, $meta_key, true );

			if ( $missing_taxonomies ) {
				echo '<div class="notice notice-error is-dismissible notice-missing-taxonomies"><p>';
				printf(
					__( '<strong>Error:</strong> Photo was not published because the following taxonomies are missing terms: %s', 'wporg-photos' ),
					'<strong>' . implode( '</strong>, <strong>', $missing_taxonomies ) . '</strong>'
				);

				echo '</p></div>' . "\n";
				delete_post_meta( $post->ID, $meta_key );
			}
		}
	}

	/**
	 * Removes menu pages added by Frontend Uploader.
	 */
	public static function remove_menu_pages() {
		$post_type = Registrations::get_post_type();

		remove_submenu_page( "edit.php?post_type={$post_type}", "manage_frontend_uploader_{$post_type}s" );
		remove_submenu_page( 'upload.php', 'manage_frontend_uploader' );
	}

	/**
	 * Hides the meta key from the custom field dropdown.
	 *
	 * @param  bool   $protected Is the meta key protected?
	 * @param  string $meta_key  The meta key.
	 * @return bool True if meta key is protected, else false.
	 */
	public static function is_protected_meta( $protected, $meta_key ) {
		return in_array( $meta_key, Registrations::get_meta_key() ) ? true : $protected;
	}

	/**
	 * Adds hook to outputs CSS.
	 */
	public static function add_admin_css() {
		if ( ! self::should_include_photo_column() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'admin_enqueue_scripts_and_styles' ] );
	}

	/**
	 * Enqueues admin scripts and styles.
	 */
	public static function admin_enqueue_scripts_and_styles() {
		wp_enqueue_script( 'wporg-photos', plugins_url( 'assets/js/admin.js', dirname( __FILE__ ) ), [], filemtime( WPORG_PHOTO_DIRECTORY_DIRECTORY . '/assets/js/admin.js' ), true );
		wp_enqueue_style( 'wporg_photos_admin', plugins_url( 'assets/css/admin.css', WPORG_PHOTO_DIRECTORY_MAIN_FILE ), [], filemtime( WPORG_PHOTO_DIRECTORY_DIRECTORY . '/assets/css/admin.css' ) );
	}

	/**
	 * Determines if the 'Photo' column should be added to a post listing table.
	 *
	 * @return bool True if the 'Photo' column should be added; else false.
	 */
	public static function should_include_photo_column() {
		$screen = get_current_screen();
		$post_type = Registrations::get_post_type();

		$pertinent_screen_ids = [
			$post_type,
			'edit-' . $post_type,
			'attachment'
		];

		$post_statuses = Photo::get_post_statuses_with_photo();

		return (
			// Screen is known.
			! empty( $screen->id )
		&&
			// Screen is one that could show the photo column.
			in_array( $screen->id, $pertinent_screen_ids )
		&&
			// No post status is explicitly requested OR the post status is one that supports photos.
			( empty( $_GET['post_status'] ) || in_array( $_GET['post_status'], $post_statuses ) )
		);
	}

	/**
	 * Adds a column to show the featured photo.
	 *
	 * @param  array $posts_columns Array of post column titles.
	 * @return array The $posts_columns array with the photo column added.
	 */
	public static function add_photo_column( $posts_columns ) {
		if ( ! self::should_include_photo_column() ) {
			return $posts_columns;
		}

		return array_slice( $posts_columns, 0, 1 )
			+ [ self::COL_NAME_PHOTO => __( 'Photo', 'wporg-photos' ) ]
			+ array_slice( $posts_columns, 1 );
	}

	/**
	 * Outputs the featured photo for the post.
	 *
	 * @since 1.0
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The id of the post being displayed.
	 */
	public static function handle_photo_column_data( $column_name, $post_id ) {
		if ( self::COL_NAME_PHOTO !== $column_name ) {
			return;
		}

		$post = get_post( $post_id );

		// Mimic standard posts by adding a prefix if post is pending.
		$prefixed_format = '%s';
		if ( isset( $post->post_status ) && in_array( $post->post_status, Photo::get_pending_post_statuses() ) ) {
			// See if a rejection reason has been set.
			$reason = Rejection::get_rejection_reason( $post );
			if ( $reason ) {
				/* translators: %s: Reason for pending rejection. */
				$prefixed_format = '<span class="pending-rejection">' . sprintf( __( 'Pending rejection - %s:' , 'wporg-photos' ), $reason  ) . '%s<span>';
			}
			elseif ( Flagged::is_post_flagged( $post ) ) {
				/* translators: %s: Pending post title. */
				$prefixed_format = __( 'Flagged: %s', 'wporg-photos' );
			}
			// Else, it is still awaiting moderation.
			else {
				/* translators: %s: Pending post title. */
				$prefixed_format = __( 'Pending: %s', 'wporg-photos' );
			}
		}

		$image_id = get_post_thumbnail_id( $post );
		$classes = '';
		if ( Photo::is_controversial( $post ) ) {
			$classes .= ' blurred';
		}
		$image = wp_get_attachment_image( $image_id, 'thumbnail', false, [ 'class' => trim( $classes ) ] );

		$can_edit_post = current_user_can( 'edit_post', $post->ID );

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			printf(
				$prefixed_format,
				sprintf(
					'<div><a class="photos-photo-link row-title" href="%s" aria-label="%s">%s</a></div>',
					get_edit_post_link( $post_id ),
					/* translators: %s: Post title. */
					esc_attr( sprintf( __( 'Edit photo associated with post &#8220;%s&#8221;', 'wporg-photos' ), $post->post_title ) ),
					$image
				)
			);
		} else {
			printf(
					'<div>%s</div>',
					sprintf( $prefixed_format, $image )
			);
		}

		/**
		 * Fires at the end of the photo column content.
		 *
		 * @param WP_Post The post object.
		 */
		do_action( 'photo_column_data_end', $post );
	}

	/**
	 * Adds a column to show flags pertaining to the photo and its submitter.
	 *
	 * @param  array $posts_columns Array of post column titles.
	 * @return array The $posts_columns array with the photo column added.
	 */
	public static function add_flags_column( $posts_columns ) {
		if ( in_array( filter_input( INPUT_GET, 'post_status' ), Photo::get_pending_post_statuses() ) ) {
			$posts_columns[ self::COL_NAME_FLAG ] = __( 'Flags', 'wporg-photos' );
		}

		return $posts_columns;
	}

	/**
	 * Outputs the content for the flags column for the post.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The id of the post being displayed.
	 */
	public static function handle_flags_column_data( $column_name, $post_id ) {
		if ( self::COL_NAME_FLAG !== $column_name ) {
			return;
		}

		$post = get_post( $post_id );

		do_action( 'wporg_photos_flag_column_data', $post );
	}

	/**
	 * Removes certain columns from the listing of pending photos.
	 *
	 * Removes these columns:
	 * - The colors column. (Currently colors aren't auto-assigned, so rarely
	 *   is there antyhing to show.)
	 * - The number of likes, as provided by Jetpack.
	 *
	 * @param  array $columns Array of post column titles.
	 * @return array
	 */
	public static function remove_columns_from_pending_photos( $columns ) {
		if (
			filter_input( INPUT_GET, 'post_type' ) === Registrations::get_post_type()
		&&
			in_array( filter_input( INPUT_GET, 'post_status' ), Photo::get_pending_post_statuses() )
		) {
			unset( $columns[ 'taxonomy-' . Registrations::get_taxonomy( 'colors' ) ] );
			unset( $columns['likes'] );
			unset( $columns['stats'] );
		}

		return $columns;
	}

	/**
	 * Adds photo media-related links to post row actions for photo posts.
	 *
	 * @param string[] $actions An array of row action links.
	 * @param WP_Post  $post    The post object.
	 * @return string[]
	 */
	public static function add_post_action_photo_links( $actions, $post ) {
		// Bail if not a photo post.
		if ( ! self::should_include_photo_column() ) {
			return $actions;
		}

		// Bail if no assciated image.
		$image_id = get_post_thumbnail_id( $post );
		if ( ! $image_id ) {
			return $actions;
		}

		// Add 'Edit Photo' link to edit photo media if user can edit the media.
		if ( current_user_can( 'edit_post', $image_id ) ) {
			$actions[] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $image_id ),
				/* translators: %s: Post title. */
				esc_attr( sprintf( __( 'Edit photo associated with post &#8220;%s&#8221;', 'wporg-photos' ), $post->post_title ) ),
				__( 'Edit Photo', 'wporg-photos' )
			);
		}

		// Add 'View Photo' link, which links directly to photo.
		$actions[] = sprintf(
			'<a href="%s">%s</a>',
			wp_get_attachment_url( $image_id ),
			__( 'View Photo', 'wporg-photos' )
		);

		return $actions;
	}

	/**
	 * Adds the metaboxes.
	 *
	 * @since 2.2
	 *
	 * @param string  $post_type Post type.
	 * @param WP_Post $post      Post object.
	 */
	public static function add_photos_meta_boxes( $post_type, $post ) {
		// Certain metaboxes shouldn't be shown unless in contexts where a photo is expected.
		$show = true;
		if ( ! in_array( get_post_status( $post ), Photo::get_post_statuses_with_photo() ) ) {
			$show = false;
		}

		if ( 'attachment' === $post_type  ) {
			$parent_id = wp_get_post_parent_id( $post );
			if ( ! $parent_id || Registrations::get_post_type() !== get_post_type( $parent_id ) ) {
				return;
			}
		} elseif ( Registrations::get_post_type() === get_post_type( $post->ID ) ) {
			if ( $show ) {
				add_meta_box( 'photos_photo', __( 'Photo', 'wporg-photos' ), [ __CLASS__, 'meta_box_photo' ], $post_type, 'normal' );
				add_meta_box( 'photos_by_contributor', __( 'Other Recent Photo Submissions by Contributor', 'wporg-photos' ), [ __CLASS__, 'meta_box_photos_by_contributor' ], $post_type, 'normal' );
			}
			add_meta_box( 'photos_info', __( 'Photo Info', 'wporg-photos' ), [ __CLASS__, 'meta_box_info' ], $post_type, 'side' );
		} else {
			return;
		}

		add_meta_box( 'photos_mod_flags', 'Moderation Flags', [ __CLASS__, 'meta_box_moderation_flags' ], $post_type, 'side' );

		if ( $show ) {
			add_meta_box( 'photos_exif', 'EXIF Data', [ __CLASS__, 'meta_box_exif' ], $post_type, 'side' );
		}
	}

	/**
	 * Outputs the contents for the Moderation Flag metabox.
	 *
	 * Note: Presumes that the metabox is being shown in the proper context and
	 * that 'parent_id' is included as a callback arg.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Associative array of additional data.
	 */
	public static function meta_box_moderation_flags( $post, $args ) {
		$parent_id = 'attachment' === $post->post_type ? wp_get_post_parent_id( $post ) : $post->ID;

		if ( ! $parent_id ) {
			return;
		}

		$flags = Photo::get_filtered_moderation_assessment( $parent_id, [] );

		if ( ! $flags ) {
			return;
		}

		echo '<dl class="photos-flagged">';
		foreach ( $flags as $flag => $class ) {
			echo '<dt>' . ucfirst( $flag ) . ':</dt>';
			echo '<dd>' . ucwords( str_replace( '_', ' ', $class ) ) . '</dd>';
		}
		echo "</dl>\n";

		Moderation::show_flags( $post );
	}

	/**
	 * Outputs the contents for the EXIF Data metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Associative array of additional data.
	 */
	public static function meta_box_exif( $post, $args ) {
		$parent_id = 'attachment' === $post->post_type ? wp_get_post_parent_id( $post ) : $post->ID;

		if ( ! $parent_id ) {
			return;
		}

		$exif = Photo::get_exif( $parent_id );

		if ( ! $exif ) {
			return;
		}

		echo '<dl>';
		foreach ( $exif as $key => $data ) {
			echo "<dt>{$data['label']}</dt>\n";
			echo "<dd>{$data['value']}</dd>\n";
		}
		echo "</dl>\n";
	}

	/**
	 * Outputs the contents for the Photo Info metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Associative array of additional data.
	 */
	public static function meta_box_info( $post, $args ) {
		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return;
		}

		$photo_id = get_post_thumbnail_id();
		$photo    = get_post( $photo_id );

		$uploaded_on = sprintf(
			/* translators: Publish box date string. 1: Date, 2: Time. See https://www.php.net/manual/datetime.format.php */
			__( '%1$s at %2$s' ),
			/* translators: Publish box date format, see https://www.php.net/manual/datetime.format.php */
			date_i18n( _x( 'M j, Y', 'publish box date format', 'wporg-photos' ), strtotime( $photo->post_date ) ),
			/* translators: Publish box time format, see https://www.php.net/manual/datetime.format.php */
			date_i18n( _x( 'H:i', 'publish box time format', 'wporg-photos' ), strtotime( $photo->post_date ) )
		);

		$info = [];

		// Certain fields shouldn't be shown unless in contexts where a photo is expected.
		if ( $photo_id ) {
			$photo_file = get_attached_file( $photo_id );
			$meta       = wp_get_attachment_metadata( $photo_id );

			$file_size = false;

			if ( isset( $meta['filesize'] ) ) {
				$file_size = $meta['filesize'];
			} elseif ( file_exists( $photo_file ) ) {
				$file_size = filesize( $photo_file );
			}

			if ( isset( $meta['width'], $meta['height'] ) ) {
				$file_dims = "<span id='media-dims-$photo_id'>{$meta['width']}&nbsp;&times;&nbsp;{$meta['height']}</span> ";
			} else {
				$file_dims = __( 'unknown', 'wporg-photos' );
			}

			$info = [
				'dimensions' => [
					'label' => __( 'Dimensions', 'wporg-photos' ),
					'value' => $file_dims,
				],
				'filesize' => [
					'label' => __( 'File size', 'wporg-photos' ),
					'value' => $file_size ? size_format( $file_size ) : __( 'unknown', 'wporg-photos' ),
				],
				'filetype' => [
					'label' => __( 'File type', 'wporg-photos' ),
					'value' => get_post_mime_type( $photo_id ),
				],
				'filename' => [
					'label' => __( 'File name', 'wporg-photos' ),
					'value' => sprintf(
						'<a href="%s">%s</a>',
						esc_url( wp_get_original_image_url( $photo_id ) ),
						esc_html( wp_basename( $photo_file ) )
					),
				],
			];
		}

		$info = array_merge( $info, [
			'orig-filename' => [
				'label' => __( 'Original file name', 'wporg-photos' ),
				'value' => esc_html( get_post_meta( $post->ID, Registrations::get_meta_key( 'original_filename' ), true ) ),
			],
			'uploaded-at' => [
				'label' => __( 'Uploaded on', 'wporg-photos' ),
				'value' => $uploaded_on,
			],
		] );

		echo '<dl>';
		foreach ( $info as $key => $data ) {
			echo "<dt>{$data['label']}</dt>\n";
			echo "<dd>{$data['value']}</dd>\n";
		}
		echo "</dl>\n";
	}

	/**
	 * Outputs the contents for the Photo metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Associative array of additional data.
	 */
	public static function meta_box_photo( $post, $args ) {
		self::output_photo_in_metabox( $post, [ 900, 450 ], true );
	}

	/**
	 * Outputs the contents for the Recent Photo Submissions by Contributor metabox.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Associative array of additional data.
	 */
	public static function meta_box_photos_by_contributor( $post, $args ) {
		$photos_in_grid = 24;

		// Request one more photo than is intended to be shown to determine if the contributor
		// has more photos than will be shown. Also, as the current photo will be excluded from
		// the grid, this also allows for its slot in the grid to be replaced without coming up
		// short or making an additional query.
		$recent_subs = User::get_recent_photos( $post->post_author, $photos_in_grid + 1, true );

		// Front-load all pending photos.
		usort( $recent_subs, function ( $a, $b ) {
			$a_status = $a->post_status ?? '';
			$b_status = $b->post_status ?? '';

			if ( $a_status === $b_status ) {
				return ( ( $a->post_date ?? '' ) > ( $b->post_date ?? '' ) ) ? -1 : 1;
			} elseif ( 'publish' === $a_status ) {
				return 1;
			} else {
				return -1;
			}
		} );

		echo '<div class="photos-grid">' . "\n";

		$shown_photos = 0;
		foreach ( $recent_subs as $photo ) {
			// Don't show more photos than intended.
			// An extra photo was requested to determine if the contributor has even more photos.
			if ( $shown_photos >= $photos_in_grid ) {
				break;
			}

			// Don't show the current photo in the grid.
			if ( $photo->ID === $post->ID ) {
				continue;
			}

			// Show the photo.
			self::output_photo_in_metabox( $photo, 'medium', false );

			$shown_photos++;
		}

		echo '</div>' . "\n";

		if ( count( $recent_subs ) > $photos_in_grid ) {
			echo '<div class="view-all-contributor-photos">';
			$link = add_query_arg( [
				'post_type'   => Registrations::get_post_type(),
				'author'      => $post->post_author,
			], 'edit.php' );
			printf(
				'<a href="%s">%s</a>',
				esc_url( $link ),
				__( "View all photos from this contributor &rarr;", 'wporg-photos' )
			);
			echo '</div>' . "\n";
		}
	}

	/**
	 * Outputs markup for a photo intended to be shown in an admin metabox.
	 *
	 * @param WP_Post      $post             Photo post object.
	 * @param string|int[] $size             Image size. Accepts any registered image size name, or an
	 *                                       array of width and height values in pixels (in that order).
	 * @param bool         $link_to_fullsize Should the image link to its full-sized version? If not, it
	 *                                       will link to edit the photo post. Default true;
	 */
	protected static function output_photo_in_metabox( $post, $size, $link_to_fullsize = true ) {
		$image_id = get_post_thumbnail_id( $post );
		if ( ! $image_id ) {
			return;
		}

		$pending_notice = '';
		$classes = 'photo-thumbnail';

		if ( Photo::is_controversial( $image_id ) ) {
			$classes .= ' blurred';
		}

		if ( 'pending' === $post->post_status ) {
			$classes .= ' pending';
			if ( ! $link_to_fullsize ) {
				$pending_notice = '<div class="pending-notice">' . __( 'Pending', 'wporg-photos' ) . '</div>';
			}
		}

		if ( $link_to_fullsize ) {
			$link_url = wp_get_attachment_url( $image_id );
			$label = __( 'View full-sized version of the photo.', 'wporg-photos' );
		} else {
			$link_url = get_edit_post_link( $post );
			$label = sprintf( __( 'Edit photo post &#8220;%s&#8221;', 'wporg-photos' ), $post->post_title );
		}

		printf(
			'<span><a class="photos-photo-link row-title" href="%s" target="_blank" aria-label="%s"><img class="%s" src="%s" alt="" /></a>%s</span>',
			esc_url( $link_url ),
			/* translators: %s: Post title. */
			esc_attr( $label ),
			esc_attr( $classes ),
			esc_url( get_the_post_thumbnail_url( $post->ID, $size ) ),
			$pending_notice
		);
	}

	/**
	 * Appends the count of the published photos to author names in photo post
	 * listings.
	 *
	 * @param string $display_name The author's display name.
	 * @return string
	 */
	public static function add_published_photos_count_to_author( $display_name ) {
		global $authordata;

		if ( ! is_admin() || ! self::should_include_photo_column() ) {
			return $display_name;
		}

		// Close link to contributor's listing of photos.
		$display_name .= '</a>';

		// Show number of approved photos.
		$approved_link = add_query_arg( [
			'post_type'   => Registrations::get_post_type(),
			'post_status' => 'publish',
			'author'      => $authordata->ID,
		], 'edit.php' );
		$display_name .= '<div class="user-approved-count">'
		. sprintf(
			__( 'Approved: <strong>%s</strong>', 'wporg-photos' ),
			sprintf( '<a href="%s">%d</a>', $approved_link, User::count_published_photos() )
		)
		. "</div>\n";

		// Show number of pending photos if there are any.
		$pending_count = User::count_pending_photos();
		if ( $pending_count ) {
			$pending_link = add_query_arg( [
				'post_type'   => Registrations::get_post_type(),
				'post_status' => 'pending',
				'author'      => $authordata->ID,
			], 'edit.php' );

			$display_name .= '<div class="user-pending-count">'
				. sprintf(
					__( 'Pending: <strong>%s</strong>', 'wporg-photos' ),
					sprintf( '<a href="%s">%d</a>', $pending_link, $pending_count )
				)
				. "</div>\n";
		}

		// Show number of rejected photos.
		$rejection_count = User::count_rejected_photos( $authordata->ID );
		if ( $rejection_count ) {
			$rejected_link = add_query_arg( [
				'post_type'   => Registrations::get_post_type(),
				'post_status' => Rejection::get_post_status(),
				'author'      => $authordata->ID,
			], 'edit.php' );
			$display_name .= '<div class="user-rejected-count">'
				. sprintf(
					/* translators: %s: Count of user rejections linked to listing of their rejections. */
					_n( 'Rejected: <strong>%s</strong>', 'Rejected: <strong>%s</strong>', $rejection_count, 'wporg-photos' ),
					sprintf( '<a href="%s">%d</a>', $rejected_link, $rejection_count )
				)
				. "</div>\n";
		}

		// Prevent unbalanced tag.
		$display_name .= '<a>';

		return $display_name;
	}

	/**
	 * Disables the block editor for photo posts.
	 *
	 * @param bool   $use_block_editor Whether the block editor should be used
	 *                                 when editing the post type or not.
	 * @param string $post_type        The post type being checked.
	 * @return bool
	 */
	public static function disable_block_editor( $use_block_editor, $post_type ) {
		if ( Registrations::get_post_type() === $post_type ) {
			$use_block_editor = false;
		}

		return $use_block_editor;
	}

	/**
	 * Outputs admin notices.
	 */
	public static function add_notice_to_photo_media_if_pending() {
		$screen = get_current_screen();
		$post_type = Registrations::get_post_type();
		$msg = '';
		$notice_type = 'info';

		if ( empty( $_GET['post' ] ) ) {
			return;
		}

		if ( 'attachment' === $screen->id ) {
			$post_id = wp_get_post_parent_id( $_GET['post'] );
			if ( ! $post_id ) {
				return;
			}

			if ( get_post_type( $post_id ) !== $post_type ) {
				return;
			}

			$post = get_post( $post_id );

			if ( in_array( $post->post_status, Photo::get_pending_post_statuses() ) ) {
				$msg = sprintf(
					__( 'This photo is private (though technically directly available should its obfuscated path be known) until officially published to the site. To do so, moderate its <a href="%s">associated post</a>.', 'wporg-photos' ),
					get_edit_post_link( $post_id )
				);
				$notice_type = 'warning';
			} else {
				$msg = sprintf(
					__( 'View the photo\'s <a href="%s">associated post</a> for more information and to moderate.', 'wporg-photos' ),
					get_edit_post_link( $post_id )
				);
			}
		}

		if ( $msg ) {
			printf( '<div id="message" class="notice notice-%s"><p>%s</p></div>' . "\n", esc_attr( $notice_type ), $msg );
		}
	}

	/**
	 * Amends photo post type admin menu name with indicator count for pending
	 * posts.
	 *
	 * @param array $menu Associative array of administration menu items.
	 * @return array
	 */
	public static function add_admin_menu_pending_indicator( $menu ) {
		$post_type = Registrations::get_post_type();
		$menu_path = 'edit.php?post_type=' . $post_type;

		foreach ( $menu as $menu_key => $menu_data ) {
			if ( $menu_data[2] !== $menu_path ) {
				continue;
			}

			$post_counts = wp_count_posts( $post_type, 'readable' );
			$pending_count = 0;
			if ( ! empty( $post_counts->pending ) ) {
				$pending_count = $post_counts->pending;
			}

			if ( $pending_count ) {
				$indicator = sprintf(
					'<span class="update-plugins count-%s"><span class="plugin-count">%s</span></span>',
					$pending_count,
					number_format_i18n( $pending_count )
				);

				/* translators: %s: Markup indicator denoting number of pending photos. */
				$menu[ $menu_key ][0] = sprintf( __( 'Photos %s', 'wporg-photos' ), $indicator );
			}

			break;
		}

		return $menu;
	}

	/**
	 * Records the moderator of the post.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Old post status.
	 */
	public static function record_moderator( $new_status, $old_status, $post ) {
		// Only concerned with photo post type.
		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return;
		}

		// Only concerned with posts changing post status
		if ( $new_status === $old_status ) {
			return;
		}

		// Only concerned with posts being published
		if ( ! in_array( $new_status, [ 'publish', 'trash' ] ) ) {
			return;
		}

		// Can only save moderating user ID if one can be obtained
		if ( $current_user_id = get_current_user_id() ) {
			update_post_meta( $post->ID, Registrations::get_meta_key( 'moderator' ), $current_user_id );
		}
	}

	/**
	 * Displays the moderator of the post in the publish metabox.
	 */
	public static function show_moderator() {
		global $post;

		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}

		$moderator_link = Photo::get_moderator_link( $post );
		if ( $moderator_link ) {
			echo '<div class="misc-pub-section curtime misc-pub-curtime">';
			printf( __( 'Moderated by: %s', 'wporg-photos' ), $moderator_link );
			echo '</div>';
		}
	}

	/**
	 * Registers photo contributor meta box.
	 *
	 * @param string  $post_type The post type.
	 * @param string  $type      The mode for the meta box (normal, advanced, or side).
	 * @param WP_Post $post      The post.
	 */
	public static function register_photo_contributor_metabox( $post_type, $type, $post ) {
		// If attachment, check if it is attached to photo post type.
		if ( 'attachment' === $post_type ) {
			$parent = get_post_parent( $post );
			if ( ! $parent || Registrations::get_post_type() !== get_post_type( $parent ) ) {
				return;
			}
		}
		// Otherwise only concerned with photo post type.
		elseif ( Registrations::get_post_type() !== $post_type ) {
			return;
		}

		add_meta_box(
			'photo_contributor_card',
			__( 'Photo Contributor', 'wporg-photos' ),
			[ __CLASS__, 'add_photo_contributor_metabox' ],
			$post_type,
			'side',
			'high'
		);
	}

	/**
	 * Adds the content for the photo contributor metabox.
	 *
	 * @param object $object
	 * @param array  $box
	 */
	public static function add_photo_contributor_metabox( $object, $box ) {
		$post = $GLOBALS['post'];

		if ( 'attachment' === get_post_type( $post ) ) {
			$post = get_post_parent( $post );
		}

		$author = get_user_by( 'id', $post->post_author );
		$photos_count = User::count_published_photos( $author->ID );
		$account_created = explode( ' ', $author->user_registered )[0];
		?>
		<style>
			.photo-contributor-card {
				display: flex;
				flex-flow: row wrap;
				gap: 10px;
			}
			.photo-contributor-card .avatar,
			.photo-contributor-info {
				flex: 0 1 auto;
			}
			.photo-contributor-url:after {
				font-family: 'dashicons';
				content: " \f504";
			}
		</style>
		<div class="photo-contributor-card">
			<?php echo get_avatar( $author->ID, 48 ); ?>
			<div class="photo-contributor-info">
				<strong>
					<?php if ( $author->user_url ) { ?><a class="photo-contributor-url" rel="noopener noreferrer" href="<?php echo esc_url( $author->user_url ); ?>"><?php } ?>
					<?php echo $author->display_name; ?>
					<?php if ( $author->user_url ) { ?></a><?php } ?>
					<div class="photo-contributor-profile"><a href="https://profiles.wordpress.org/<?php esc_attr_e( $author->user_nicename ); ?>/">@<?php echo $author->user_nicename; ?></a></div>
				</strong>
				<ul>
					<li><?php
						/* translators: %s: Linked number of photos submitted by user. */
						printf(
							__( 'Published photos: <strong>%s</strong>', 'wporg-photos' ),
							( 0 === $photos_count )
								? $photos_count
								: sprintf( '<a href="%s">%s</a>', get_author_posts_url( $author->ID ), $photos_count )
						);
					?></li>
					<li><?php
						$rejected_count = User::count_rejected_photos( $author->ID );
						$link_args = [
							'post_type'   => Registrations::get_post_type(),
							'post_status' => Rejection::get_post_status(),
							'author'      => $author->ID,
						];
						/* translators: %s: Linked number of photos submitted by user that have been rejected. */
						printf(
							__( 'Rejected photos: <strong>%s</strong>', 'wporg-photos' ),
							( 0 === $rejected_count )
								? $rejected_count
								: sprintf( '<a href="%s">%d</a>', add_query_arg( $link_args, 'edit.php' ), $rejected_count )
						);
					?></li>
					<li><?php
						$flagged_count = User::count_flagged_photos( $author->ID );
						$flagged_link = '';
						if ( $flagged_count && current_user_can( Flagged::get_capability() )) {
							$link_args = [
								'post_type'   => Registrations::get_post_type(),
								'post_status' => Flagged::get_post_status(),
								'author'      => $author->ID,
							];
							$flagged_link = add_query_arg( $link_args, 'edit.php' );
						}
						printf(
							/* translators: %s: Count of user's flagged photos possibly linked to listing of their flagged photos. */
							_n( 'Flagged photos: <strong>%s</strong>', 'Flagged photos: <strong>%s</strong>', $flagged_count, 'wporg-photos' ),
							$flagged_link ? sprintf( '<a href="%s">%d</a>', $flagged_link, $flagged_count ) : $flagged_count
						);
					?></li>
					<li><?php
						$pending_count = User::count_pending_photos( $author->ID );
						$link_args = [
							'post_type'   => Registrations::get_post_type(),
							'post_status' => 'pending',
							'author'      => $author->ID,
						];
						/* translators: %s: Linked number of photos submitted by user that have been rejected. */
						printf(
							__( 'Pending photos: <strong>%s</strong>', 'wporg-photos' ),
							( 0 === $pending_count )
								? $pending_count
								: sprintf( '<a href="%s">%d</a>', add_query_arg( $link_args, 'edit.php' ), $pending_count )
						);
					?></li>
					<li><?php
						/* translators: %s: Date user account was created. */
						printf( __( 'Created: <strong>%s</strong>', 'wporg-photos' ), $account_created ); ?></li>
				</ul>
			</div>
			<div class="photo-contributor-more-info">
			<?php
				// Output photo contributor IP address.
				if ( $contrib_ip = Photo::get_contributor_ip( $post->ID ) ) {
					/* translators: %s: IP address for contributor. */
					printf( __( 'Contributor IP address: <strong>%s</strong>', 'wporg-photos' ), sanitize_text_field( $contrib_ip ) );
				}
			?>
			</div>
		</div>

		<?php
	}

	/**
	 * Outputs additional metadata to attachment submitbox.
	 *
	 * @param WP_Post $post Attachment post ID.
	 */
	public static function attachment_submitbox_additions( $post ) {
		$post_id = wp_get_post_parent_id( $post->ID );

		if ( ! $post_id ) {
			return;
		}

		$format = '<div class="misc-pub-section misc-pub-%s">%s: <strong>%s</strong></div>';

		// Output file hash.
		if ( $file_hash = get_post_meta( $post_id, Registrations::get_meta_key( 'file_hash' ), true ) ) {
			printf( $format, 'file-hash', __( 'File hash', 'wporg-photos' ), $file_hash );
		}

		// Output original filename.
		if ( $orig_filename = get_post_meta( $post_id, Registrations::get_meta_key( 'original_filename' ), true ) ) {
			printf( $format, 'original-filename', __( 'Original file name', 'wporg-photos' ), $orig_filename );
		}

		// Output moderator.
		if ( $mod_link = Photo::get_moderator_link( $post_id ) ) {
			printf( $format, 'moderator', __( 'Moderator', 'wporg-photos' ), $mod_link );
		}
	}

	/**
	 * Records the IP address of the photo contributor.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function record_contributor_ip( $new_status, $old_status, $post ) {
		// Only concerned with photo post type.
		if ( Registrations::get_post_type() !== get_post_type( $post ) ) {
			return;
		}

		// Only concerned with posts on creation.
		if ( 'new' !== $old_status || 'revision' === get_post_type( $post ) ) {
			return;
		}

		$photo_contrib_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( $_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP ) : '';
		if ( $photo_contrib_ip ) {
			update_post_meta( $post->ID, Registrations::get_meta_key( 'contributor_ip' ), $photo_contrib_ip );
		}
	}

	/**
	 * Adds a column to show the IP address of the photo contributor.
	 *
	 * Inserts the columns immediately after the Author column. If that's not
	 * present, then appends to end of the columns.
	 *
	 * @param array $posts_columns Array of post column titles.
	 * @return array
	 */
	public static function add_contributor_ip_column( $posts_columns ) {
		$pos = array_search( 'author', array_keys( $posts_columns ) );

		if ( $pos ) {
			$pos++;
			$posts_columns = array_slice( $posts_columns, 0, $pos, true )
				+ [ self::COL_NAME_CONTRIBUTOR_IP => __( 'Contributor IP', 'wporg-photos' ) ]
				+ array_slice( $posts_columns, 3, count( $posts_columns ) - 1, true );
		} else {
			$posts_columns[ self::COL_NAME_CONTRIBUTOR_IP ] = __( 'Contributor IP', 'wporg-photos' );
		}

		return $posts_columns;
	}

	/**
	 * Outputs the IP address of the photo contributor.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The id of the post being displayed.
	 */
	public static function handle_contributor_ip_column_data( $column_name, $post_id ) {
		if ( self::COL_NAME_CONTRIBUTOR_IP !== $column_name ) {
			return;
		}

		$photo_contrib_ip = Photo::get_contributor_ip( $post_id );

		if ( $photo_contrib_ip ) {
			echo '<span>' . sanitize_text_field( $photo_contrib_ip ) . '</span>';
		}
	}

	/**
	 * Restricts direct access to the Media Library by moderators.
	 */
	public static function restrict_media_library_access() {
		global $pagenow;
		if (
			'upload.php' === $pagenow
		&&
			! current_user_can( get_post_type_object( 'post' )->cap->create_posts )
		) {
			wp_die( __( 'Sorry, you are not allowed to access the media library.', 'wporg-photos' ) );
		}
	}

	/**
	 * Removes "Media Library" from the admin menu for moderators.
	 */
	public static function disable_media_library() {
		if ( ! current_user_can( get_post_type_object( 'post' )->cap->create_posts ) ) {
			remove_menu_page( 'upload.php' );
		}
	}

	/**
	 * Modifies admin menu links for photo posts.
	 *
	 * - Adds 'Queue' link to link to posts in moderation/pending
	 * - Remove 'Add New' link
	 */
	public static function modify_admin_menu_links() {
		$post_type = Registrations::get_post_type();
		$path      = 'edit.php?post_type=' . $post_type;

		// Remove 'Add New' link.
		remove_submenu_page( $path, 'post-new.php?post_type=' . $post_type );

		// Add 'Queue' link.
		$post_type_obj = get_post_type_object( $post_type );
		add_submenu_page(
			$path,
			__( 'Photos In Moderation', 'wporg-photos' ),
			__( 'Queue', 'wporg-photos' ),
			$post_type_obj->cap->edit_posts,
			esc_url( add_query_arg( [ 'post_status' => 'pending' ], $path ) ),
			'',
			1
		);
	}

	/**
	 * Outputs an admin notice when a photo post has been moderated and the
	 * moderator has been redirected to the next photo in the queue.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function show_moderation_message( $post ) {
		if (
			empty( $_GET['photomoderated'] )
		||
			empty( $_GET['photoaction'] )
		||
			Registrations::get_post_type() !== get_post_type( $post )
		) {
			return;
		}

		$moderated_post = get_post( (int) $_GET['photomoderated'] );

		if ( ! $moderated_post ) {
			return;
		}

		$message = '';
		$edit_link = get_edit_post_link( $moderated_post );

		switch ( $_GET['photoaction'] ) {
			case 'approval':
				$message = sprintf(
					/* translators: 1: Link markup to view photo post, 2: Link markup to edit photo post. */
					__( 'Photo post approved. %1$s &mdash; %2$s', 'wporg-photos' ),
					sprintf(
						' <a href="%s">%s</a>',
						esc_url( get_post_permalink( $moderated_post ) ),
						__( 'View photo post', 'wporg-photos' )
					),
					sprintf(
						' <a href="%s">%s</a>',
						esc_url( $edit_link ),
						__( 'Edit photo post', 'wporg-photos' )
					)
				);
				break;
			case 'rejection':
				$message = sprintf(
					/* translators: %s: Link markup to view photo post. */
					__( 'Photo post rejected. %s', 'wporg-photos' ),
					sprintf(
						' <a href="%s">%s</a>',
						esc_url( $edit_link ),
						__( 'Edit photo post', 'wporg-photos' )
					)
				);
				break;
			default:
				$message = '';
		}

		if ( $message ) {
			printf(
				'<div id="message" class="updated notice notice-success is-dismissible"><p>%s</p></div>',
				$message
			);
		}
	}

	/**
	 * Overrides the redirect after moderating a post to load the next post in
	 * the queue.
	 *
	 * Only redirects if a photo post is initially published or rejected.
	 *
	 * @param string $location The destination URL.
	 * @param int    $post_id  The post ID.
	 * @return string
	 */
	public static function redirect_to_next_post_after_moderation( $location, $post_id ) {
		$is_rejection = isset( $_POST[ Rejection::$action ] );

		if (
			( isset( $_POST['publish'] ) || $is_rejection )
		&&
			Registrations::get_post_type() === get_post_type( $post_id )
		) {
			$action = $is_rejection ? 'rejection' : 'approval';
			$next_post = Posts::get_next_post_in_queue();
			if ( $next_post ) {
				$location = add_query_arg( 'photomoderated', $post_id, get_edit_post_link( $next_post, 'url' ) );
				$location = add_query_arg( 'photoaction', $action, $location );
			}
		}

		return $location;
	}

	/**
	 * Removes the 'Orientations' column from post listings.
	 *
	 * The column doesn't represent information that needs to be gleaned from a
	 * post listing overview.
	 *
	 * @param string[] $taxonomies Array of taxonomy names to show columns for.
	 * @param string   $post_type  The post type.
	 * @return string[]
	 */
	public static function remove_orientations_column( $taxonomies, $post_type ) {
		if ( Registrations::get_post_type() === $post_type ) {
			unset( $taxonomies[ Registrations::get_taxonomy( 'orientations' ) ] );
		}

		return $taxonomies;
	}

	/**
	 * Amends body tag with additional classes.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function add_body_class( $classes ) {
		$post_type = Registrations::get_post_type();
		if (
			// Post listing of photos.
			filter_input(INPUT_GET, 'post_type') === $post_type
		||
			// Editing a photo post.
			( ( $post_id = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT) ) && get_post_type( $post_id ) === $post_type )
		) {
			$classes .= ' post-type-photo';
		}

		return $classes;
	}

	/**
	 * Disables rich editor for photo posts.
	 *
	 * @param bool $user_can Can the user rich edit?
	 * @return bool
	 */
	public static function disable_rich_editing( $user_can ) {
		$post = get_post();
		if ( $user_can && $post && get_post_type( $post ) === Registrations::get_post_type() ) {
			$user_can = false;
		}

		return $user_can;
	}

	/**
	 * Forces use of the text editor for photo posts.
	 *
	 * @param string $default The default editor as chosen by user or defaulted by WP.
	 * @return string 'html'
	 */
	public static function force_text_editor( $default ) {
		$post = get_post();
		if ( $post && get_post_type( $post ) === Registrations::get_post_type() ) {
			$default = 'html';
		}

		return $default;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Admin', 'init' ] );
