<?php

/**
 * Subtitles Upload Handling
 *
 * Works with the form in the upload-subtitles-template.php page template.
 * Based on (copy pasted from) the anonymous video upload form by Androw Ozz.
 */
class WordPressTV_Subtitles_Upload {
	// hardcoded user_id for the fake contributor that owns the drafts (username: anonvideoupload)
	private $drafts_author = 34340661;

	private $video_id;

	function __construct() {
		add_action( 'admin_post_wptv_video_upload_subtitles', array( $this, 'post' ) );
		add_action( 'admin_post_nopriv_wptv_video_upload_subtitles', array( $this, 'post' ) );
		add_action( 'edit_form_after_title', array( $this, 'in_post_edit_form' ) );
		add_action( 'all_admin_notices', array( $this, 'pending_notice' ) );

		add_filter( 'attachment_fields_to_save', array( $this, 'moderate' ) );
		add_filter( 'views_upload', array( $this, 'views_links' ) );
		add_filter( 'post_mime_types', array( $this, 'post_mime_types' ) );
	}

	/**
	 * Creates the attachment if it's a valid file
	 *
	 * @uses wp_handle_upload
	 */
	function handle_upload() {
		// allow only video mimes
		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'ttml' => 'application/ttml+xml',
				'dfxp' => 'application/ttml+xml', // .dfxp is changed to .ttml in $this->generate_filename()
			),
		);

		unset( $_FILES['async-upload'] );

		if ( empty( $_FILES['wptv_subtitles_file']['name'] ) ) {
			return new WP_Error( 'upload_error', 'Invalid file name.' );
		}

		$name = $_FILES['wptv_subtitles_file']['name'];
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'generate_filename' ), 5 );

		$file = wp_handle_upload( $_FILES['wptv_subtitles_file'], $overrides );

		if ( isset( $file['error'] ) ) {
			return new WP_Error( 'upload_error', $file['error'] );
		}

		$filepath = $file['file'];

		$attachment                   = array();
		$attachment['post_title']     = $this->sanitize_text( $name );
		$attachment['guid']           = $file['url'];
		$attachment['post_mime_type'] = $file['type'];
		$attachment['post_content']   = '';
		$attachment['post_author']    = $this->drafts_author;

		// expects slashed
		$attachment_id = wp_insert_attachment( add_magic_quotes( $attachment ), $filepath );

		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $filepath ) );
		}

		return $attachment_id;
	}

	/**
	 * Generate a non-guessable filename for uploaded files.
	 *
	 * @param string $file
	 *
	 * @return string
	 */
	function generate_filename( $file ) {
		$name_parts = pathinfo( $file['name'] );

		// this should never happen
		if ( empty( $name_parts['extension'] ) ) {
			die;
		}

		// Change .dfxp to .ttml
		if ( 'dfxp' == strtolower( $name_parts['extension'] ) ) {
			$name_parts['extension'] = 'ttml';
		}

		// random file name
		$str          = md5( time() . rand( 1, 1000000 ) );
		$file['name'] = 'subtitles-' . substr( $str, rand( 5, 20 ), 10 ) . '.' . $name_parts['extension'];

		return $file;
	}

	/**
	 * When the POST request is fired with the subtitles form and action.
	 */
	function post() {
		if ( empty( $_POST['wptv-upload-subtitles-nonce'] ) || ! wp_verify_nonce( $_POST['wptv-upload-subtitles-nonce'], 'wptv-upload-subtitles' ) ) {
			wp_die( 'Invalid form data. Please go back and try again.' );
		}

		if ( empty( $_POST['wptv_video_id'] ) ) {
			wp_die( 'Requires a video context.' );
		}

		$video_id       = absint( $_POST['wptv_video_id'] );
		$this->video_id = $video_id;

		if ( function_exists( 'wp_attachment_is_video' ) && ! wp_attachment_is_video( $video_id ) ) {
			wp_die( 'You can only subtitle videos.' );
		}

		if ( empty( $_POST['wptv_wporg_username'] ) || empty( $_POST['wptv_author_email'] ) || ! is_email( $_POST['wptv_author_email'] ) ) {
			$this->error( 4 );
		}

		$wporg_username = $this->sanitize_text( $_POST['wptv_wporg_username'] );
		$author_email   = $this->sanitize_text( $_POST['wptv_author_email'] );

		if ( empty( $_POST['wptv_language'] ) ) {
			$this->error( 8 );
		}

		$language            = $_POST['wptv_language'];
		$available_languages = class_exists( 'VideoPress_Subtitles' ) ? VideoPress_Subtitles::get_languages() : array();

		if ( ! array_key_exists( $language, $available_languages ) ) {
			$this->error( 7 );
		}

		$language = $available_languages[ $language ];

		$video_data       = function_exists( 'video_get_info_by_blogpostid' ) ? video_get_info_by_blogpostid( get_current_blog_id(), $video_id ) : new StdClass;
		$video_attachment = get_post( $video_id );
		if ( empty( $video_data ) || empty( $video_attachment ) ) {
			wp_die( 'Invalid form data.' );
		}

		$parent = get_post( $video_attachment->post_parent );

		if ( ! $parent || 'publish' != $parent->post_status ) {
			wp_die( 'You can not subtitle this video.' );
		}

		$tracks = class_exists( 'VideoPress_Subtitles' ) ? VideoPress_Subtitles::get_tracks( $video_data->guid ) : array();
		if ( ! empty( $tracks[ $language['key'] ] ) ) {
			$this->error( 8 );
		}

		if ( empty( $_FILES['wptv_subtitles_file']['name'] ) ) {
			$this->error( 1 );
		}

		// quick file extension check
		$name_parts = pathinfo( $_FILES['wptv_subtitles_file']['name'] );

		if ( ! empty( $name_parts['extension'] ) ) {
			if ( ! in_array( strtolower( $name_parts['extension'] ), array( 'ttml', 'dfxp' ), true ) ) {
				$this->error( 2 );
			}
		} else {
			$this->error( 3 );
		}

		// empty the globals just in case
		$_POST = $_REQUEST = $_GET = array();

		$subs_attachment_id = $this->handle_upload();

		if ( is_wp_error( $subs_attachment_id ) ) {
			$this->error( 5 );
		}

		// TODO: needed?? Better to test in $this->handle_upload() and return WP_Error
		// Link the uploaded attachment
		/*		if ( get_post_mime_type( $subs_attachment_id ) != 'application/ttml+xml' ) {
					wp_delete_attachment( $subs_attachment_id );
					$this->error( 2 );
				}
		*/
		// TODO: needed??
		// Not visible on wordpress.tv but is in the HTML source. However 'post_content' is public data.
		// Used for <meta name="description" and "og:description".
		$post_content = sprintf( "Uploaded by: %s\nLanguage: %s",
			$wporg_username,
			$language['label']
		);

		wp_update_post( array(
			'ID'           => $subs_attachment_id,
			'post_content' => $post_content,
			'post_title'   => sprintf( 'Subtitles: %s (%s)', $parent->post_title, $language['label'] ),
		//	'post_parent'  => $parent->ID, // easier to look for unapproved subtitles attachment if they are "unattached"?
		) );

		$subs_attachment_meta = array(
			'video_attachment_id' => $video_attachment->ID,
			'video_post_id'       => $parent->ID,
			'video_guid'          => $video_data->guid,
			'submitted_by'        => $wporg_username,
			'submitted_email'     => $author_email,
			'language_key'        => $language['key'],
		//	'ip'                  => $_SERVER['REMOTE_ADDR'], // keep this for ref?
		);

		update_post_meta( $subs_attachment_id, '_wptv_submitted_subtitles', $subs_attachment_meta );

		/*		// TODO: can add this on upload to indicate that there is a file being moderated.
				// This will block uploads of other files for the same language,
				// but needs change in VideoPress_Subtitles::get_tracks() in /videopress/subtitles.php
				// to bypass the trac when 'subtitles_post_id' == 0.
				// Alternatively can add some meta on the video attachment post.
				$subtitles = get_post_meta( $video_attachment->ID, '_videopress_subtitles', true );
				if ( empty( $subtitles ) )
					$subtitles = array();

				$subtitles[ $language['key'] ] = array(
					'language' => $language['key'],
					'subtitles_post_id' => 0,
					'pending_subtitles_post_id' => $subs_attachment_id,
				);

				if ( ! update_post_meta( $video_attachment->ID, '_videopress_subtitles', $subtitles ) ) {
					wp_delete_attachment( $subs_attachment_id );
					$this->error( 5 );
				}
		*/
		// success() redirects to the 'subtitle' page with "Thank you for uploading" message and exits.
		$this->success();
	}

	// Runs on 'attachment_fields_to_save' (an attachment post is being saved)
	function moderate( $post_data ) {
		if ( empty( $post_data['wptv-subtitles'] ) ) {
			return $post_data;
		}

		$approve       = ! empty( $post_data['wptv-approve-subtitles'] );
		$attachment_id = $post_data['ID'];

		$attachment_meta = get_post_meta( $attachment_id, '_wptv_submitted_subtitles', true );
		if ( empty( $attachment_meta ) ) {
			wp_die( 'Missing attachment metadata.' ); // Cannot show errors other than die(...)
		}

		$parent_id           = (int) $attachment_meta['video_post_id'];
		$video_attachment_id = (int) $attachment_meta['video_attachment_id'];
		$language_key        = $attachment_meta['language_key'];
		$subtitles           = $_subtitles = get_post_meta( $video_attachment_id, '_videopress_subtitles', true );

		if ( empty( $subtitles ) ) {
			$subtitles = array();
		}

		if ( $approve ) {
			$subtitles[ $language_key ] = array(
				'language'          => $language_key,
				'subtitles_post_id' => $attachment_id,
			);
			// Attach the attachment
			$post_data['post_parent'] = $parent_id;
		} else {
			unset( $subtitles[ $language_key ] );
			/*
			$subtitles[ $language_key ] = array(
				'language' => $language_key,
				'subtitles_post_id' => 0,
				'pending_subtitles_post_id' => $attachment_id,
			);
			*/
			// Detach the attachment
			$post_data['post_parent'] = 0;
		}

		if ( $subtitles != $_subtitles ) {
			if ( empty( $subtitles ) ) {
				delete_post_meta( $video_attachment_id, '_videopress_subtitles' );
			} else {
				update_post_meta( $video_attachment_id, '_videopress_subtitles', $subtitles );
			}
		}

		return $post_data;
	}

	// Output the HTML for moderation
	function in_post_edit_form( $attachment_post ) {
		if ( $attachment_post->post_type != 'attachment' || $attachment_post->post_mime_type != 'application/ttml+xml' ) {
			return;
		}

		// Added meta to these existing subtitles posts:
		// done: 17732,21488,21578,21987,22063,22064,22065,22066,22144,
		// remain: 17295,17297,22459 // all are tests

		$file_content = file_get_contents( wp_get_attachment_url( $attachment_post->ID ) );
		if ( ! $file_content ) {
			echo '<div class="error"><p>ERROR: the attached file doesn\'t exist or is empty.</p></div>';

			return;
		}

		$attachment_meta = get_post_meta( $attachment_post->ID, '_wptv_submitted_subtitles', true );
		if ( empty( $attachment_meta ) ) {
			echo '<div class="error"><p>ERROR: the attachment post metadata is missing.</p></div>';

			return;
		}

		$is_approved         = $another_approved = false;
		$video_attachment_id = (int) $attachment_meta['video_attachment_id'];
		$parent_id           = (int) $attachment_meta['video_post_id'];
		$language_key        = $attachment_meta['language_key'];
		$subtitles           = get_post_meta( $video_attachment_id, '_videopress_subtitles', true );

		if ( is_array( $subtitles ) && ! empty( $subtitles[ $language_key ]['subtitles_post_id'] ) ) {
			if ( $subtitles[ $language_key ]['subtitles_post_id'] == $attachment_post->ID ) {
				$is_approved = true;
			} else {
				// Has another approved subtitles file
				$another_approved = (int) $subtitles[ $language_key ]['subtitles_post_id'];
			}
		}

		// Add some line breaks to make it easier to read
		$file_content = str_replace( array( "\r", '>', '<' ), array( '', ">\n", "\n<" ), $file_content );
		$file_content = preg_replace( '/\n\n+/', "\n\n", trim( $file_content ) );

		// Replace any urlencoded bits with '?'. Maybe flag this too, there shouldn't be any?
		$match = array();
		while ( preg_match( '/%[a-f0-9]{2}/i', $file_content, $match ) ) {
			$file_content = str_replace( $match[0], '?', $file_content );
		}

		// Replace any escaped <br/> inside the subtitles strings. Makes it easier to read.
		$file_content = str_replace( array( '&lt;br /&gt;', '&lt;br/&gt;' ), "<br />\n", $file_content );

		$file_content = htmlspecialchars( $file_content, ENT_QUOTES, 'UTF-8' );
		$file_content = str_replace( "\n", '<br>', $file_content );

		?>
		<div id="subs-wrapper">
			<style type="text/css" scoped="">
				#subs-wrapper {
					margin: 20px 0;
				}

				#subs-content {
					padding: 10px;
					border: 1px solid #ccc;
					margin-top: 0;
					overflow: auto;
					max-height: 400px;
					background-color: #fff;
					font-family: Consolas, Monaco, monospace;
				}

				#subs-wrapper .subs-approved {
					background-color: #f9f9f9;
					border: 1px solid #dfdfdf;
					border-radius: 3px;
					padding: 10px;
				}

				#subs-wrapper .subs-approved label {
					font-size: 120%;
				}

				#subs-wrapper .subs-approved input[type="checkbox"] {
					margin: 1px 5px;
				}

				#subs-wrapper .warning {
					color: #dd0000;
				}
			</style>
			<strong>Content of the subtitles file</strong><br>

			<div id="subs-content"><?php echo $file_content; ?></div>

			<div class="subs-info">
				<input type="hidden" name="wptv-subtitles" value="1"/>

				<p><a href="<?php echo esc_url( get_permalink( $parent_id ) ); ?>" target="_blank">Preview</a> the video (opens in
					new tab).</p>

				<p>Submitted by: <a
						href="http://profiles.wordpress.org/<?php echo esc_attr( $attachment_meta['submitted_by'] ); ?>/"><?php echo esc_html( $attachment_meta['submitted_by'] ); ?></a>,
					email: <a
						href="mailto:<?php echo esc_attr( $attachment_meta['submitted_email'] ); ?>"><?php echo esc_html( $attachment_meta['submitted_email'] ); ?></a>
				</p>

				<p><a href="<?php echo esc_url( get_edit_post_link( $video_attachment_id ) ); ?>">Edit</a> the video attachment
					post.</p>
				<?php

				if ( $another_approved ) {
					?>
					<p class="subs-approved">
						<span class="warning">WARNING:</span> There is <a href="<?php echo esc_url( get_edit_post_link( $another_approved ) ); ?>">another approved subtitles
							file</a>
						for this video and language. If you approve the current file, the other will be automatically
						unapproved.</p>
				<?php
				}

				?>
				<p class="subs-approved"><label><input type="checkbox"
				                                       name="wptv-approve-subtitles"<?php echo $is_approved ? ' checked="checked"' : ''; ?> />
						Approved</label></p>
			</div>
		</div>
	<?php
	}

	/**
	 * Add "Subtitles (..)" link to the view links on the Media Library screen.
	 *
	 * If post_mime_types in wp_match_mime_types() and
	 * class-wp-media-list-table.php were working well, this would not
	 * be needed.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	function views_links( $links ) {
		$mime_type = 'application/ttml+xml';

		$all_subs = wp_count_attachments( $mime_type );
		$class    = '';

		if ( ! empty( $_GET['post_mime_type'] ) && $mime_type == $_GET['post_mime_type'] ) {
			$class = ' class="current"';
		}

		$links['wptv_subs'] = '<a href="upload.php?post_mime_type=' . urlencode( $mime_type ) . '"' . $class . '>Subtitles <span class="count">(' . $all_subs->$mime_type . ')</span></a>';

		return $links;
	}

	/**
	 * Output "Need moderation" message
	 */
	function pending_notice() {
		global $wpdb, $pagenow;

		$where_to_show = array( 'index.php', 'edit.php', 'upload.php' );

		if ( in_array( $pagenow, $where_to_show, true ) ) {
			$pending_subs = $wpdb->get_var( "SELECT COUNT( 1 ) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_status != 'trash' AND post_parent = 0 AND post_mime_type = 'application/ttml+xml'" );

			if ( $pending_subs ) {
				echo '<div class="updated"><p><a href="upload.php?post_mime_type=' . urlencode( 'application/ttml+xml' ) .
				     '&amp;detached=1">Subtitles awaiting moderation (' . $pending_subs . ')</a></p></div>';
			}
		}
	}

	/**
	 * Add support fot the subtitles mime type. This should work better in core...
	 *
	 * @param $mime_types
	 *
	 * @return mixed
	 */
	function post_mime_types( $mime_types ) {
		$mime_types['application/ttml+xml'] = array(
			__( 'Subtitles' ),
			__( 'Manage Subtitles' ),
			_n_noop( 'Subtitles <span class="count">(%s)</span>', 'Subtitles <span class="count">(%s)</span>' )
		);

		return $mime_types;
	}

	/**
	 * Create an error and redirect.
	 *
	 * @param string $message
	 */
	function error( $message ) {
		wp_safe_redirect( add_query_arg( array(
			'video' => $this->video_id,
			'error' => $message,
		), home_url( 'subtitle' ) ) );
		die();
	}

	/**
	 * Redirect to a success page.
	 */
	function success() {
		wp_safe_redirect( add_query_arg( array(
			'video'   => $this->video_id,
			'success' => 1,
		), home_url( 'subtitle' ) ) );
		exit;
	}

	// expects slashed, returns unslashed
	function sanitize_text( $str, $remove_line_breaks = true ) {
		$str = str_replace( '\\', '', $str );

		if ( $remove_line_breaks ) {
			$str = sanitize_text_field( $str );
		} else {
			$str = wp_check_invalid_utf8( $str );
			$str = wp_strip_all_tags( $str );

			$match = array();
			while ( preg_match( '/%[a-f0-9]{2}/i', $str, $match ) ) {
				$str = str_replace( $match[0], '', $str );
			}
		}

		return htmlspecialchars( $str, ENT_QUOTES, 'UTF-8' );
	}
}

new WordPressTV_Subtitles_Upload;
