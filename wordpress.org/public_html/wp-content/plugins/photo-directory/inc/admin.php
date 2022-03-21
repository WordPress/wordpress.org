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
		add_filter( 'post_row_actions',                        [ __CLASS__, 'add_post_action_photo_links' ], 10, 2 );
		add_filter( 'the_author',                              [ __CLASS__, 'add_published_photos_count_to_author' ] );
		add_filter( 'use_block_editor_for_post_type',          [ __CLASS__, 'disable_block_editor' ], 10, 2 );
		add_action( 'admin_notices',                           [ __CLASS__, 'add_notice_to_photo_media_if_pending' ] );
		add_filter( 'add_menu_classes',                        [ __CLASS__, 'add_admin_menu_pending_indicator' ] );

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

		// Admin notices
		add_action( 'admin_notices',                           [ __CLASS__, 'add_notice_if_killswitch_enabled' ] );

		// Modify admin menu links for photo posts.
		add_action( 'admin_menu',                              [ __CLASS__, 'modify_admin_menu_links' ] );
	}

	/**
	 * Returns the count of the number of photo submissions from a user that were rejected.
	 *
	 * @param int $user_id The user ID.
	 * @return int
	 */
	public static function count_user_rejections( $user_id ) {
		global $wpdb;

		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s AND post_status = %s AND post_author = %d",
			Registrations::get_post_type(),
			Rejection::get_post_status(),
			$user_id
		) );
	}

	/**
	 * Outputs admin notices.
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

	public static function admin_enqueue_scripts_and_styles() {
		wp_enqueue_style( 'wporg_photos_admin', plugins_url( 'assets/css/admin.css', WPORG_PHOTO_DIRECTORY_MAIN_FILE ), [], '20220126' );
	}

	protected static function should_include_photo_column() {
		$screen = get_current_screen();
		$post_type = Registrations::get_post_type();

		$pertinent_screen_ids = [
			$post_type,
			'edit-' . $post_type,
			'attachment'
		];

		$excluded_post_statuses = [ 'trash', Rejection::get_post_status() ];

		return ! empty( $screen->id ) && in_array( $screen->id, $pertinent_screen_ids ) && ( empty( $_GET['post_status'] ) || ! in_array( $_GET['post_status'], $excluded_post_statuses ) );
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
		if ( isset( $post->post_status ) && 'pending' === $post->post_status ) {
			// See if a rejection reason has been set.
			$reason = Rejection::get_rejection_reason( $post );
			if ( $reason ) {
				/* translators: %s: Reason for pending rejection. */
				$prefixed_format = '<span class="pending-rejection">' . sprintf( __( 'Pending rejection - %s:' , 'wporg-photos' ), $reason  ) . '%s<span>';
			}
			// Else, it is still awaiting moderation.
			else {
				/* translators: %s: Pending post title. */
				$prefixed_format = __( 'Pending: %s', 'wporg-photos' );
			}
		}

		$image_id = get_post_thumbnail_id( $post );
		$image = wp_get_attachment_image( $image_id );

		$can_edit_post = current_user_can( 'edit_post', $post->ID );

		if ( $can_edit_post && 'trash' !== $post->post_status ) {
			printf(
				$prefixed_format,
				sprintf(
					'<div><a class=row-title" href="%s" aria-label="%s">%s</a></div>',
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
		if ( empty( $_GET['post_status'] ) || 'pending' === $_GET['post_status'] ) {
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
		if ( ! in_array( get_post_status( $post ), [ 'draft', 'inherit', 'pending', 'private', 'publish' ] ) ) {
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
		$image_id = get_post_thumbnail_id( $post );
		if ( ! $image_id ) {
			return;
		}

		$thumb_url = wp_get_attachment_image_src( $image_id, [ 900, 450 ], true );

		printf(
			'<a class="row-title" href="%s" target="_blank" aria-label="%s"><img class="photo-thumbnail" src="%s" style="max-width:100%%" alt="" /></a>',
			wp_get_attachment_url( $image_id ),
			/* translators: %s: Post title. */
			esc_attr( sprintf( __( 'Edit photo associated with post &#8220;%s&#8221;', 'wporg-photos' ), $post->post_title ) ),
			set_url_scheme( $thumb_url[0] )
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
			sprintf( '<a href="%s">%d</a>', $approved_link, Photo::count_user_published_photos() )
		)
		. "</div>\n";

		// Show number of pending photos if there are any.
		$pending_count = Photo::count_user_pending_photos();
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
		$rejections = Rejection::get_user_rejections( $authordata->ID );
		if ( $rejections ) {
			$rejected_link = add_query_arg( [
				'post_type'   => Registrations::get_post_type(),
				'post_status' => Rejection::get_post_status(),
				'author'      => $authordata->ID,
			], 'edit.php' );
			$display_name .= '<div class="user-rejected-count">'
				. sprintf(
					/* translators: %s: Count of user rejections linked to listing of their rejections. */
					__( 'Rejected: <strong>%s</strong>', 'wporg-photos' ),
					sprintf( '<a href="%s">%d</a>', $rejected_link, count( $rejections ) )
				)
				. "</div>\n";
		}

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

			if ( 'pending' === $post->post_status ) {
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
		$photos_count = count_user_posts( $author->ID, Registrations::get_post_type(), true );
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
						$rejected_count = self::count_user_rejections( $author->ID );
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
						$pending_count = Photo::count_user_pending_photos( $author->ID );
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

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Admin', 'init' ] );
