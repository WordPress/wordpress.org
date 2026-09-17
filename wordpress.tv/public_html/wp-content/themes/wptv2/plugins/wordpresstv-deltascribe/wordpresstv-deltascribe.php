<?php

/**
 * DeltaScribe Captioning Integration
 *
 * Offers a link from each video's page out to DeltaScribe (a client-side
 * subtitle timing tool) pre-loaded with the video's media file, and accepts
 * the finished captions back via a signed, CORS-enabled callback.
 *
 * Submissions are funnelled into the exact same pending-attachment /
 * moderation pipeline as wordpresstv-upload-subtitles.php (same
 * `_wptv_submitted_subtitles` postmeta shape), so no changes are needed
 * there to review and approve them.
 */
class WordPressTV_DeltaScribe {
	// Same fake contributor used by WordPressTV_Subtitles_Upload for pending subtitle attachments.
	const DRAFTS_AUTHOR = 34340661;

	const DEFAULT_URL = 'https://deltascribe.stephanis.me/';

	private $video_id;

	/**
	 * Wires up the settings field and the two admin-post endpoints
	 * (start/submit) used by the bridge page and by DeltaScribe itself.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_setting' ) );

		add_action( 'admin_post_wptv_deltascribe_start', array( $this, 'start' ) );
		add_action( 'admin_post_nopriv_wptv_deltascribe_start', array( $this, 'start' ) );

		add_action( 'admin_post_wptv_deltascribe_submit', array( $this, 'submit' ) );
		add_action( 'admin_post_nopriv_wptv_deltascribe_submit', array( $this, 'submit' ) );
	}

	/**
	 * Registers the "DeltaScribe URL" field on Settings -> General, so the
	 * hosted instance can be repointed without a code change.
	 */
	public function register_setting() {
		register_setting(
			'general',
			'wptv_deltascribe_url',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'default'           => self::DEFAULT_URL,
			)
		);

		add_settings_field(
			'wptv_deltascribe_url',
			__( 'DeltaScribe URL', 'wptv' ),
			array( $this, 'setting_field' ),
			'general'
		);
	}

	/**
	 * Renders the "DeltaScribe URL" text field on Settings -> General.
	 */
	public function setting_field() {
		?>
		<input type="url" id="wptv_deltascribe_url" name="wptv_deltascribe_url"
			class="regular-text code" placeholder="<?php echo esc_attr( self::DEFAULT_URL ); ?>"
			value="<?php echo esc_attr( get_option( 'wptv_deltascribe_url', '' ) ); ?>" />
		<p class="description"><?php esc_html_e( 'Root URL of the DeltaScribe instance used for the "Caption with DeltaScribe" link on video pages.', 'wptv' ); ?></p>
		<?php
	}

	/**
	 * The configured DeltaScribe root URL, falling back to the default instance.
	 *
	 * @return string
	 */
	public static function get_url() {
		$url = get_option( 'wptv_deltascribe_url', '' );

		return $url ? $url : self::DEFAULT_URL;
	}

	/**
	 * Validates that $video_id is a video attachment whose parent post is public.
	 *
	 * @param int $video_id
	 *
	 * @return WP_Post|false The parent post, or false if invalid.
	 */
	private function get_valid_video_parent( $video_id ) {
		if ( ! $video_id || ! function_exists( 'wp_attachment_is_video' ) || ! wp_attachment_is_video( $video_id ) ) {
			return false;
		}

		$attachment = get_post( $video_id );
		if ( ! $attachment ) {
			return false;
		}

		$parent = get_post( $attachment->post_parent );
		if ( ! $parent || ! in_array( $parent->post_status, array( 'publish', 'private' ), true ) ) {
			return false;
		}

		return $parent;
	}

	/**
	 * Builds the HMAC payload string used to sign (and later verify) a submit-callback URL.
	 *
	 * @param array $fields Must contain video, lang, user, email, expires.
	 *
	 * @return string
	 */
	private function token_payload( $fields ) {
		return implode(
			'|',
			array(
				$fields['video'],
				$fields['lang'],
				$fields['user'],
				$fields['email'],
				$fields['expires'],
			)
		);
	}

	/**
	 * Signs a token payload with the site's auth salt, so a submit-callback
	 * URL can be verified later without any server-side session/DB state.
	 *
	 * @param array $fields Same shape as token_payload().
	 *
	 * @return string HMAC-SHA256 hex digest.
	 */
	private function sign( $fields ) {
		return hash_hmac( 'sha256', $this->token_payload( $fields ), wp_salt( 'auth' ) );
	}

	/**
	 * Handles the bridge page's form POST: validates the submitted metadata,
	 * then redirects out to DeltaScribe with a signed submit-callback URL.
	 *
	 * Note: start_error()/submit_error() below always exit(), so calls to
	 * them don't need a following `return` to stop execution.
	 */
	public function start() {
		if ( empty( $_POST['wptv-deltascribe-start-nonce'] ) || ! wp_verify_nonce( $_POST['wptv-deltascribe-start-nonce'], 'wptv-deltascribe-start' ) ) {
			wp_die( esc_html__( 'Invalid form data. Please go back and try again.', 'wptv' ) );
		}

		if ( empty( $_POST['wptv_video_id'] ) ) {
			wp_die( esc_html__( 'Requires a video context.', 'wptv' ) );
		}

		$video_id       = absint( $_POST['wptv_video_id'] );
		$this->video_id = $video_id;

		$parent = $this->get_valid_video_parent( $video_id );
		if ( ! $parent ) {
			wp_die( esc_html__( 'You can not caption this video, sorry.', 'wptv' ) );
		}

		if ( empty( $_POST['wptv_wporg_username'] ) || empty( $_POST['wptv_author_email'] ) || ! is_email( $_POST['wptv_author_email'] ) ) {
			$this->start_error( 1 );
		}

		$wporg_username = sanitize_text_field( wp_unslash( $_POST['wptv_wporg_username'] ) );
		$author_email   = sanitize_text_field( wp_unslash( $_POST['wptv_author_email'] ) );

		$available_languages = class_exists( 'VideoPress_Subtitles' ) ? VideoPress_Subtitles::get_languages() : array();

		if ( empty( $_POST['wptv_language'] ) || ! array_key_exists( $_POST['wptv_language'], $available_languages ) ) {
			$this->start_error( 2 );
		}

		$language_key = $_POST['wptv_language'];

		global $wptv;
		$media_url = $wptv ? $wptv->get_video_attachment_url( $video_id ) : wp_get_attachment_url( $video_id );

		if ( ! $media_url ) {
			$this->start_error( 3 );
		}

		$fields = array(
			'video'   => $video_id,
			'lang'    => $language_key,
			'user'    => $wporg_username,
			'email'   => $author_email,
			'expires' => time() + WEEK_IN_SECONDS,
		);

		$submit_url = add_query_arg(
			array_merge(
				$fields,
				array(
					'action' => 'wptv_deltascribe_submit',
					'token'  => $this->sign( $fields ),
				)
			),
			admin_url( 'admin-post.php' )
		);

		$deltascribe_url = add_query_arg(
			array(
				'media'  => rawurlencode( $media_url ),
				'submit' => rawurlencode( $submit_url ),
				'lang'   => rawurlencode( $language_key ),
				'format' => 'ttml',
			),
			self::get_url()
		);

		// Intentionally not wp_safe_redirect(): the destination is an
		// admin-configured, external, third-party host by design, and
		// wp_safe_redirect() would silently fall back to the home URL
		// unless that host is also added to allowed_redirect_hosts.
		wp_redirect( $deltascribe_url ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * The CORS-enabled JSON callback DeltaScribe POSTs finished captions to.
	 */
	public function submit() {
		$this->send_cors_headers();

		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'OPTIONS' === $_SERVER['REQUEST_METHOD'] ) {
			status_header( 204 );
			exit;
		}

		$fields = array(
			'video'   => isset( $_GET['video'] ) ? absint( $_GET['video'] ) : 0,
			'lang'    => isset( $_GET['lang'] ) ? sanitize_text_field( wp_unslash( $_GET['lang'] ) ) : '',
			'user'    => isset( $_GET['user'] ) ? sanitize_text_field( wp_unslash( $_GET['user'] ) ) : '',
			'email'   => isset( $_GET['email'] ) ? sanitize_text_field( wp_unslash( $_GET['email'] ) ) : '',
			'expires' => isset( $_GET['expires'] ) ? absint( $_GET['expires'] ) : 0,
		);

		$this->video_id = $fields['video'];

		$token = isset( $_GET['token'] ) ? $_GET['token'] : '';

		if ( ! $token || ! hash_equals( $this->sign( $fields ), $token ) ) {
			$this->submit_error( __( 'Invalid or tampered submission link.', 'wptv' ), 403 );
		}

		if ( $fields['expires'] < time() ) {
			$this->submit_error( __( 'This submission link has expired. Please start over from the video page.', 'wptv' ), 403 );
		}

		$parent = $this->get_valid_video_parent( $fields['video'] );
		if ( ! $parent ) {
			$this->submit_error( __( 'This video is no longer available for captioning.', 'wptv' ), 404 );
		}

		$available_languages = class_exists( 'VideoPress_Subtitles' ) ? VideoPress_Subtitles::get_languages() : array();
		if ( ! array_key_exists( $fields['lang'], $available_languages ) ) {
			$this->submit_error( __( 'Invalid language.', 'wptv' ), 400 );
		}

		$language = $available_languages[ $fields['lang'] ];

		$body = json_decode( file_get_contents( 'php://input' ), true );

		if ( empty( $body['format'] ) || 'ttml' !== $body['format'] ) {
			$this->submit_error( __( 'Unsupported format. This endpoint only accepts TTML; pass &format=ttml through to DeltaScribe.', 'wptv' ), 422 );
		}

		if ( empty( $body['subtitles'] ) || ! is_string( $body['subtitles'] ) ) {
			$this->submit_error( __( 'Missing subtitles content.', 'wptv' ), 422 );
		}

		$video_data       = function_exists( 'video_get_info_by_blogpostid' ) ? video_get_info_by_blogpostid( get_current_blog_id(), $fields['video'] ) : new StdClass();
		$video_attachment = get_post( $fields['video'] );

		if ( empty( $video_data ) || empty( $video_attachment ) ) {
			$this->submit_error( __( 'Invalid video.', 'wptv' ), 404 );
		}

		$subs_attachment_id = $this->save_subtitles_file( $body['subtitles'] );

		if ( is_wp_error( $subs_attachment_id ) ) {
			$this->submit_error( __( 'Could not save the submitted subtitles file.', 'wptv' ), 500 );
		}

		// save_subtitles_file() only knows the raw TTML; the title/content
		// need the video and language context we have here, so set them
		// via a follow-up update rather than passing them into the insert.
		$post_content = sprintf(
			/* translators: 1: contributor's WordPress.org username, 2: subtitle language label */
			__( "Uploaded by: %1\$s\nLanguage: %2\$s\nSubmitted via: DeltaScribe", 'wptv' ),
			$fields['user'],
			$language['label']
		);

		wp_update_post( array(
			'ID'           => $subs_attachment_id,
			'post_content' => $post_content,
			'post_title'   => sprintf(
				/* translators: 1: video title, 2: subtitle language label */
				__( 'Subtitles: %1$s (%2$s)', 'wptv' ),
				$parent->post_title,
				$language['label']
			),
		) );

		update_post_meta(
			$subs_attachment_id,
			'_wptv_submitted_subtitles',
			array(
				'video_attachment_id' => $video_attachment->ID,
				'video_post_id'       => $parent->ID,
				'video_guid'          => $video_data->guid,
				'submitted_by'        => $fields['user'],
				'submitted_email'     => $fields['email'],
				'language_key'        => $language['key'],
				'submitted_via'       => 'deltascribe',
			)
		);

		bump_stats_extras( 'wptv-activity', 'subtitle-uploaded-deltascribe' );

		$this->respond_json( array( 'success' => true ), 200 );
	}

	/**
	 * Writes the submitted TTML text to a new pending attachment.
	 *
	 * Mirrors WordPressTV_Subtitles_Upload::handle_upload(), but via
	 * wp_upload_bits() since there is no $_FILES entry to work from here.
	 *
	 * @param string $ttml
	 *
	 * @return int|WP_Error
	 */
	private function save_subtitles_file( $ttml ) {
		$str      = md5( time() . wp_rand( 1, 1000000 ) );
		$filename = 'subtitles-' . substr( $str, wp_rand( 5, 20 ), 10 ) . '.ttml';

		$file = wp_upload_bits( $filename, null, $ttml );

		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', $file['error'] );
		}

		$attachment = array(
			'post_title'     => $filename,
			'guid'           => $file['url'],
			'post_mime_type' => 'application/ttml+xml',
			'post_content'   => '',
			'post_author'    => self::DRAFTS_AUTHOR,
		);

		$attachment_id = wp_insert_attachment( $attachment, $file['file'] );

		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file['file'] ) );
		}

		return $attachment_id;
	}

	/**
	 * Sends the CORS headers allowing the configured DeltaScribe origin to call this endpoint.
	 */
	private function send_cors_headers() {
		$allowed_origin = untrailingslashit( self::get_url() );
		$parsed         = wp_parse_url( $allowed_origin );

		if ( empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return;
		}

		$allowed_origin = $parsed['scheme'] . '://' . $parsed['host'] . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' );

		$request_origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? $_SERVER['HTTP_ORIGIN'] : '';

		if ( $request_origin && strtolower( $request_origin ) === strtolower( $allowed_origin ) ) {
			header( 'Access-Control-Allow-Origin: ' . $allowed_origin );
			header( 'Access-Control-Allow-Methods: POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type' );
			header( 'Vary: Origin' );
		}
	}

	/**
	 * Sends a JSON response and exits.
	 *
	 * @param array $data
	 * @param int   $status
	 */
	private function respond_json( $data, $status ) {
		status_header( $status );
		header( 'Content-Type: application/json; charset=utf-8' );
		echo wp_json_encode( $data );
		exit;
	}

	/**
	 * Sends a JSON error response (`{ success: false, message }`) and exits.
	 *
	 * @param string $message Human-readable, already-translated error message.
	 * @param int    $status  HTTP status code.
	 */
	private function submit_error( $message, $status ) {
		bump_stats_extras( 'wptv-errors', 'subtitle-upload-failed-deltascribe' );

		$this->respond_json(
			array(
				'success' => false,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Redirects back to the bridge page with an error code, mirroring
	 * WordPressTV_Subtitles_Upload::error().
	 *
	 * @param int $code
	 */
	private function start_error( $code ) {
		bump_stats_extras( 'wptv-errors', 'caption-with-deltascribe-start-failed' );

		wp_safe_redirect(
			add_query_arg(
				array(
					'video' => $this->video_id,
					'error' => $code,
				),
				home_url( 'caption-with-deltascribe' )
			)
		);
		exit;
	}
}

new WordPressTV_DeltaScribe();
