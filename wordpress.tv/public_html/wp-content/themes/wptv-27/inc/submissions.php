<?php
/**
 * Anonymous video submissions (intermediate release; docs/submission-form.md).
 *
 * Ported from wptv2's WPTV_Anon_Upload. Behavior is unchanged except that:
 * the post content is a VideoPress block (shortcode authoring is retired);
 * WordCampTV/Uncategorized resolve by slug/option, the same IDs in production;
 * WordPress.com-only functions are guarded; and the review box moved to the
 * block editor (assets/js/editor.js), which receives the submitted data here.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Submissions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner of anonymous submissions, resolved by login because imports remap user
 * IDs. Falls back to the production ID used by wptv2; filterable.
 */
function drafts_author() {
	$user = get_user_by( 'login', 'anonvideoupload' );
	return (int) apply_filters( 'wptv_drafts_author_id', $user ? $user->ID : 34340661 );
}

/**
 * Bumps a WordPress.com stat when available.
 *
 * @param string $group Stat group.
 * @param string $name  Stat name.
 */
function stat( $group, $name ) {
	if ( function_exists( 'bump_stats_extras' ) ) {
		bump_stats_extras( $group, $name );
	}
}

/**
 * Same sanitizer as wptv2: expects slashed, returns unslashed and HTML-escaped.
 *
 * @param string $str                Slashed text.
 * @param bool   $remove_line_breaks Whether to remove line breaks.
 * @return string
 */
function sanitize_text( $str, $remove_line_breaks = true ) {
	$str = str_replace( '\\', '', (string) $str );
	if ( $remove_line_breaks ) {
		$str = sanitize_text_field( $str );
	} else {
		$str = wp_strip_all_tags( wp_check_invalid_utf8( $str ) );
		while ( preg_match( '/%[a-f0-9]{2}/i', $str, $match ) ) {
			$str = str_replace( $match[0], '', $str );
		}
	}
	return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' );
}

/** Handles the form POST, then redirects back with success or error codes (wptv2 init()). */
function handle() {
	$redir = home_url( '/submit-video/' );
	$error = false;
	$saved = false;
	$nonce = isset( $_POST['wptvvideon'] ) ? sanitize_text_field( wp_unslash( $_POST['wptvvideon'] ) ) : '';
	// Logged-in users always need a valid nonce. As in wptv2, anonymous visitors
	// who fill in their name may send an expired one from a cached form page.
	$must_verify = is_user_logged_in() || empty( $_POST['wptv_uploaded_by'] );
	if ( '' === $nonce || ( $must_verify && ! wp_verify_nonce( $nonce, 'wptv-upload-video' ) ) ) {
		$error = 15;
	} else {
		$error = validate();
		if ( ! $error ) {
			$saved = save();
			$error = true === $saved ? false : $saved;
		}
	}
	if ( $error ) {
		stat( 'wptv-errors', 'video-upload-failed' );
	}

	$keep = array( 'wptv_video_wordcamp', 'wptv_uploaded_by', 'wptv_email', 'wptv_language', 'wptv_date', 'post_category', 'wptv_producer_username', 'wptv_event' );
	if ( true === $saved ) {
		$redir = add_query_arg( array( 'success' => 1 ), $redir );
	} elseif ( $error ) {
		$redir = add_query_arg( array( 'error' => $error ), $redir );
		$keep  = array_merge( $keep, array( 'wptv_video_title', 'wptv_speakers', 'wptv_video_description', 'wptv_slides_url' ) );
	} else {
		$keep  = array();
		$redir = add_query_arg( array( 'error' => 5 ), $redir );
	}
	if ( $keep ) {
		$redir = add_query_arg( urlencode_deep( array_intersect_key( $_POST, array_flip( $keep ) ) ), $redir );
	}
	// For an XHR request, send the redirect location instead of redirecting.
	if ( isset( $_GET['xhr'] ) ) {
		die( $redir ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Encoded URL response, as in wptv2.
	}
	wp_safe_redirect( $redir );
	exit;
}
add_action( 'admin_post_wptv_video_upload', __NAMESPACE__ . '\\handle' );
add_action( 'admin_post_nopriv_wptv_video_upload', __NAMESPACE__ . '\\handle' );

/**
 * Checks a WordPress.org username using the same redirect endpoint as wptv2.
 * Missing Location headers and HTTP errors fail validation, as in the legacy form.
 *
 * @param string $username WordPress.org username.
 * @return bool
 */
function wporg_username_exists( $username ) {
	$url      = add_query_arg( 'user', $username, 'https://wordpress.org/grav-redirect.php' );
	$response = wp_remote_get( $url, array( 'redirection' => 0 ) );
	$location = wp_remote_retrieve_header( $response, 'location' );
	return is_string( $location ) && '' !== $location && false === strpos( $location, 'd=mm' );
}

/*
 * validate(), save() and handle_upload() only run from handle(), after its
 * nonce check: always for logged-in users, and for anonymous visitors unless
 * they come from a cached form (see handle() and docs/review-security.md, R1).
 */
// phpcs:disable WordPress.Security.NonceVerification

/**
 * Maximum lengths of the text fields, in characters.
 *
 * Must stay in sync with the form's JavaScript (assets/js/submission-form.js).
 * Speakers and title are limited separately, before they are joined into the
 * post title.
 *
 * @return int[] Lengths keyed by field name.
 */
function field_lengths() {
	return array(
		'wptv_uploaded_by'       => 100,
		'wptv_email'             => 100,
		'wptv_video_title'       => 100,
		'wptv_speakers'          => 100,
		'wptv_language'          => 100,
		'wptv_event'             => 100,
		'wptv_producer_username' => 100,
		'wptv_slides_url'        => 200,
		'wptv_video_description' => 1500,
	);
}

/** Server checks behind the form's JavaScript validation. Returns an error code or false. */
function validate() {
	// Bots fill the hidden honeypot.
	if ( ! isset( $_POST['wptv_honey'] ) || ! empty( $_POST['wptv_honey'] ) ) {
		stat( 'wptv-spam', 'honeypot_trapped_anon_upload' );
		return 13;
	}
	// Browsers count a line break as one character but send it as two (\r\n).
	foreach ( field_lengths() as $field => $length ) {
		$value = wp_unslash( $_POST[ $field ] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only measured.
		if ( ! is_string( $value ) || mb_strlen( str_replace( "\r\n", "\n", $value ), 'UTF-8' ) > $length ) {
			return 6;
		}
	}
	if ( ! empty( $_POST['wptv_producer_username'] ) && ! wporg_username_exists( sanitize_text_field( wp_unslash( $_POST['wptv_producer_username'] ) ) ) ) {
		return 14;
	}
	if ( ! is_user_logged_in() ) {
		if ( empty( $_POST['wptv_uploaded_by'] ) ) {
			return 10;
		}
		if ( empty( $_POST['wptv_email'] ) ) {
			return 11;
		}
		if ( ! is_email( sanitize_email( wp_unslash( $_POST['wptv_email'] ) ) ) ) {
			return 12;
		}
	}
	return false;
}

/** Creates the pending post, attachment and submission meta. Returns true or an error code. */
function save() {
	if ( empty( $_FILES['wptv_file']['name'] ) ) {
		return 1;
	}
	// Must stay in sync with the form's JavaScript.
	$extension = strtolower( (string) pathinfo( sanitize_text_field( $_FILES['wptv_file']['name'] ), PATHINFO_EXTENSION ) );
	if ( '' === $extension ) {
		return 3;
	}
	if ( ! in_array( $extension, array( 'avi', 'mov', 'qt', 'mpeg', 'mpg', 'mpe', 'mp4', 'm4v', 'wmv', 'ogv', '3gp', '3g2' ), true ) ) {
		return 2;
	}

	// Empty the request globals while inserting, as wptv2 did.
	$posted = $_POST;
	$saved  = array( $_POST, $_REQUEST, $_GET );

	$_POST    = array();
	$_REQUEST = array();
	$_GET     = array();

	$result = insert_submission( $posted );

	list( $_POST, $_REQUEST, $_GET ) = $saved;
	return $result;
}

/**
 * Inserts the submission from the saved POST copy.
 *
 * @param array $posted Slashed POST data.
 * @return true|int True, or an error code.
 */
function insert_submission( $posted ) {
	$post                 = get_object_vars( get_default_post_to_edit( 'post' ) );
	$post['post_title']   = sanitize_text( $posted['wptv_speakers'] ?? '' ) . ': ' . sanitize_text( $posted['wptv_video_title'] ?? '' );
	$post['post_excerpt'] = '';
	$post['post_author']  = drafts_author();
	$post['post_status']  = 'pending';
	// "This is a WordCamp video" picks WordCampTV, otherwise the default category.
	$wordcamp              = get_term_by( 'slug', 'wordcamptv', 'category' );
	$post['post_category'] = array( ! empty( $posted['wptv_video_wordcamp'] ) && $wordcamp ? $wordcamp->term_id : (int) get_option( 'default_category', 1 ) );

	$post_id = wp_insert_post( add_magic_quotes( $post ) );
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return 5;
	}
	$attachment_id = handle_upload( $post_id );
	if ( is_wp_error( $attachment_id ) ) {
		return 5;
	}

	/** Test-only short-circuit for the VideoPress GUID; null continues. */
	$guid = apply_filters( 'wptv_pre_submission_video_guid', null, $attachment_id );
	if ( null === $guid && function_exists( 'video_get_info_by_blogpostid' ) ) {
		$video = video_get_info_by_blogpostid( get_current_blog_id(), $attachment_id );
		$guid  = $video->guid ?? null;
	}
	if ( ! is_string( $guid ) || ! preg_match( '/^[A-Za-z0-9]{8}$/D', $guid ) ) {
		return 4;
	}
	$block = array(
		'blockName'    => 'videopress/video',
		'attrs'        => array( 'guid' => $guid ),
		'innerBlocks'  => array(),
		'innerHTML'    => '',
		'innerContent' => array(),
	);
	wp_update_post(
		wp_slash(
			array(
				'ID'           => $post_id,
				'post_content' => serialize_block( $block ),
			)
		)
	);

	$user       = wp_get_current_user();
	$recorded   = sanitize_text( $posted['wptv_date'] ?? '' ); // Format: yyyy-mm-dd.
	$categories = '';
	foreach ( (array) ( $posted['post_category'] ?? array() ) as $cat ) {
		if ( (int) $cat ) {
			$categories .= (int) $cat . ',';
		}
	}
	// Add the year category.
	$year = $recorded ? get_term_by( 'name', substr( $recorded, 0, 4 ), 'category' ) : false;
	if ( $year ) {
		$categories .= $year->term_id;
	}
	update_post_meta(
		$post_id,
		'_wptv_submitted_video',
		array(
			'attachment_id'     => $attachment_id,
			'submitted_by'      => $user->ID ? $user->display_name : sanitize_text( $posted['wptv_uploaded_by'] ?? '' ),
			'submitted_email'   => $user->ID ? $user->user_email : sanitize_text( $posted['wptv_email'] ?? '' ),
			'title'             => sanitize_text( $posted['wptv_video_title'] ?? '' ),
			'producer_username' => sanitize_text( $posted['wptv_producer_username'] ?? '' ),
			'speakers'          => sanitize_text( $posted['wptv_speakers'] ?? '' ),
			'event'             => sanitize_text( $posted['wptv_event'] ?? '' ),
			'language'          => sanitize_text( $posted['wptv_language'] ?? '' ),
			'categories'        => $categories,
			'description'       => sanitize_text( $posted['wptv_video_description'] ?? '', false ),
			'slides'            => sanitize_text( $posted['wptv_slides_url'] ?? '' ),
			'ip'                => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			'recorded'          => $recorded,
			'video_guid'        => $guid,
		)
	);
	return true;
}

/**
 * Stores the uploaded video as an attachment with a random file name.
 *
 * @param int $parent_id Post ID the attachment belongs to.
 * @return int|\WP_Error Attachment ID or error.
 */
function handle_upload( $parent_id ) {
	unset( $_FILES['async-upload'] );
	if ( empty( $_FILES['wptv_file']['name'] ) ) {
		return new \WP_Error( 'upload_error', 'Invalid file name.' );
	}
	$name   = sanitize_file_name( $_FILES['wptv_file']['name'] );
	$rename = static function ( $file ) {
		$file['name'] = 'video-' . substr( md5( time() . wp_rand( 1, 1000000 ) ), wp_rand( 5, 20 ), 10 ) . '.' . strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		return $file;
	};
	add_filter( 'wp_handle_upload_prefilter', $rename, 5 );
	$file = wp_handle_upload(
		$_FILES['wptv_file'],
		array(
			'test_form' => false,
			'mimes'     => array(
				'avi'          => 'video/avi',
				'mov|qt'       => 'video/quicktime',
				'mpeg|mpg|mpe' => 'video/mpeg',
				'mp4|m4v'      => 'video/mp4',
				'wmv'          => 'video/asf',
				'ogv'          => 'video/ogg',
				'3gp'          => 'video/3gpp',
				'3g2'          => 'video/3gpp2',
			),
		)
	);
	remove_filter( 'wp_handle_upload_prefilter', $rename, 5 );
	if ( isset( $file['error'] ) ) {
		return new \WP_Error( 'upload_error', $file['error'] );
	}
	$parent        = get_post( $parent_id );
	$attachment_id = wp_insert_attachment(
		add_magic_quotes(
			array(
				'post_title'     => $parent->post_title ? $parent->post_title : sanitize_file_name( $name ),
				'post_name'      => sanitize_title_with_dashes( sanitize_file_name( $name ) ),
				'guid'           => $file['url'],
				'post_mime_type' => $file['type'],
				'post_content'   => '',
				'post_author'    => drafts_author(),
			)
		),
		$file['file'],
		$parent_id
	);
	if ( ! is_wp_error( $attachment_id ) ) {
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file['file'] ) );
	}
	return $attachment_id;
}
// phpcs:enable WordPress.Security.NonceVerification

/**
 * Returns the submitted data for the review panel, decoded for display, or
 * null. Shown on every post with submission data, whatever its author.
 *
 * @param \WP_Post|null $post Post.
 * @return array|null
 */
function review_data( $post ) {
	if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
		return null;
	}
	$meta = get_post_meta( $post->ID, '_wptv_submitted_video', true );
	if ( ! is_array( $meta ) || ! $meta ) {
		return null;
	}
	$text     = static function ( $key ) use ( $meta ) {
		return html_entity_decode( (string) ( $meta[ $key ] ?? '' ), ENT_QUOTES, 'UTF-8' );
	};
	$term     = static function ( $id ) {
		$term = get_term( (int) $id, 'category' );
		return $term && ! is_wp_error( $term ) ? array(
			'id'   => $term->term_id,
			'name' => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
		) : null;
	};
	$year     = $text( 'recorded' ) ? get_term_by( 'name', substr( $text( 'recorded' ), 0, 4 ), 'category' ) : false;
	$ids      = array_filter( array_map( 'intval', explode( ',', preg_replace( '/[^0-9,]+/', '', trim( (string) ( $meta['categories'] ?? '' ), ' ,' ) ) ) ) );
	$wordcamp = get_term_by( 'slug', 'wordcamptv', 'category' );
	$id       = (int) ( $meta['attachment_id'] ?? 0 );
	$data     = array( 'defaultCategory' => $wordcamp ? $wordcamp->term_id : 0 );
	foreach ( array( 'submitted_by', 'submitted_email', 'ip', 'title', 'language', 'recorded', 'event', 'producer_username', 'speakers', 'description', 'slides' ) as $key ) {
		$data[ $key ] = $text( $key );
	}
	$data['yearCategory'] = $year ? $term( $year->term_id ) : null;
	// The year category is offered beside the recorded date, so list it only once.
	$data['categories'] = array_values( array_filter( array_map( $term, array_diff( $ids, $year ? array( $year->term_id ) : array() ) ) ) );
	$data['attachment'] = get_post( $id ) ? array(
		'title' => html_entity_decode( get_post( $id )->post_title, ENT_QUOTES, 'UTF-8' ),
		'url'   => wp_get_attachment_url( $id ),
		'edit'  => get_edit_post_link( $id, 'raw' ),
	) : null;
	return $data;
}

/** Hands the review data to the video editor script without exposing it through REST. */
function enqueue_review_data() {
	if ( ! wp_script_is( 'wptv-editor', 'enqueued' ) ) {
		return;
	}
	$data = review_data( get_post() );
	if ( $data ) {
		wp_add_inline_script( 'wptv-editor', 'window.wptvSubmission = ' . wp_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE ) . ';', 'before' );
	}
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\\enqueue_review_data', 6 );

/**
 * Places the review panel below the content, with the meta boxes. The box has
 * no form fields: assets/js/editor.js renders the panel into its root.
 *
 * @param \WP_Post $post Post.
 */
function add_review_box( $post ) {
	if ( ! use_block_editor_for_post( $post ) || ! review_data( $post ) ) {
		return;
	}
	add_meta_box(
		'wptv-submission',
		__( 'Submitted video', 'wptv' ),
		static function () {
			echo '<div id="wptv-submission-root"></div>';
		},
		'post',
		'normal',
		'high',
		array( '__back_compat_meta_box' => false )
	);
}
add_action( 'add_meta_boxes_post', __NAMESPACE__ . '\\add_review_box' );
