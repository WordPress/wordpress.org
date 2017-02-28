<?php

// Anonnymous uploads of videos for WPTV
class WPTV_Anon_Upload {
	// hardcoded user_id for the fake contributor that owns the drafts (username: anonvideoupload, currently uses andrew.ozz@automattic email)
	private $drafts_author = 34340661;
	var $errors = false;
	var $success = false;

	function __construct() {
		$this->drafts_author = apply_filters( 'wptv_drafts_author_id', $this->drafts_author );  // this is filterable in order to support local development

		add_action( 'admin_post_wptv_video_upload', array( &$this, 'init' ) );
		add_action( 'admin_post_nopriv_wptv_video_upload', array( &$this, 'init' ) );
		add_action( 'dbx_post_sidebar', array( &$this, 'display' ) );
	}

	function init() {
		if ( ! empty( $_POST['wptvvideon'] ) && wp_verify_nonce( $_POST['wptvvideon'], 'wptv-upload-video' ) ) {
			$this->validate();

			if ( ! $this->errors ) {
				$this->success = $this->save();
			}

			$redir = home_url( 'submit-video' );

			if ( $this->success ) {
				$redir = add_query_arg( array( 'success' => 1 ), $redir );
			} elseif ( $this->errors ) {
				$redir = add_query_arg( array( 'error' => $this->errors ), $redir );
			} else {
				$redir = add_query_arg( array( 'error' => 5 ), $redir );
			}

			wp_redirect( $redir );
		} else {
			// no nonce, send them "home"?
			wp_redirect( home_url() );
		}

		exit;
	}

	// This should never trigger in "proper" use as there's JS validation on the form and JS is required.
	// If it triggers, consider it as a bot/improper use and exit?
	function validate() {
		$text_fields = array(
			'wptv_video_title',
			'wptv_producer_username',
			'wptv_speakers',
			'wptv_event',
			'wptv_slides_url'
		);

		// Normal users won't see the honeypot field, so if there's a value in it, then we can assume the submission is spam from a bot
		if ( ! isset( $_POST['wptv_honey'] ) || ! empty( $_POST['wptv_honey'] ) ) {
			bump_stats_extras( 'wptv-spam', 'honeypot_trapped_anon_upload' );

			return $this->error( 13 );
		}

		if ( ! empty( $_POST['wptv_producer_username'] ) && ! wporg_username_exists( $_POST['wptv_producer_username'] ) ) {
			return $this->error( 14 );
		}

		if ( ! is_user_logged_in() ) {
			if ( empty( $_POST['wptv_uploaded_by'] ) ) {
				return $this->error( 10 );
			}

			if ( empty( $_POST['wptv_email'] ) ) {
				return $this->error( 11 );
			} elseif ( ! is_email( $_POST['wptv_email'] ) ) {
				return $this->error( 12 );
			}

			$text_fields[] = 'wptv_uploaded_by';
			$text_fields[] = 'wptv_email';
		}

		foreach ( $text_fields as $field ) {
			if ( strlen( (string) $field ) > 150 ) {
				return $this->error( 6 );
			}
		}
	}

	function handle_upload( $parent_id ) {
		// allow only video mimes
		$overrides = array(
			'test_form' => false,
			'mimes'     => array(
				'avi'                 => 'video/avi',
				'mov|qt'              => 'video/quicktime',
				'mpeg|mpg|mpe'        => 'video/mpeg',
				'mp4|m4v'             => 'video/mp4',
				'asf|asx|wax|wmv|wmx' => 'video/asf',
				'ogv'                 => 'video/ogg',
				'3gp'                 => 'video/3gpp',
				'3g2'                 => 'video/3gpp2',
			),
		);

		unset( $_FILES['async-upload'] );

		if ( empty( $_FILES['wptv_file']['name'] ) ) {
			return new WP_Error( 'upload_error', 'Invalid file name.' );
		}

		$name = $_FILES['wptv_file']['name'];
		add_filter( 'wp_handle_upload_prefilter', array( &$this, 'video_filename' ), 5 );

		$file = wp_handle_upload( $_FILES['wptv_file'], $overrides );

		if ( isset( $file['error'] ) ) {
			return new WP_Error( 'upload_error', $file['error'] );
		}

		$filepath = $file['file'];

		$attachment = array(
			'post_title'     => $this->sanitize_text( $name ),
			'guid'           => $file['url'],
			'post_mime_type' => $file['type'],
			'post_content'   => '',
			'post_author'    => $this->drafts_author,
		);

		// expects slashed
		$attachment_id = wp_insert_attachment( add_magic_quotes( $attachment ), $filepath, $parent_id );

		if ( ! is_wp_error( $attachment_id ) ) {
			wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $filepath ) );
		}

		return $attachment_id;
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

	function error( $msg ) {
		bump_stats_extras( 'wptv-errors', 'video-upload-failed' );
		$this->errors = $msg;

		return false;
	}

	function video_filename( $file ) {
		$name_parts = pathinfo( $file['name'] );

		// this should never happen
		if ( empty( $name_parts['extension'] ) ) {
			die;
		}

		// random file name
		$str          = md5( time() . rand( 1, 1000000 ) );
		$file['name'] = 'video-' . substr( $str, rand( 5, 20 ), 10 ) . '.' . $name_parts['extension'];

		return $file;
	}

	function save() {
		// check
		if ( empty( $_FILES['wptv_file']['name'] ) ) {
			return $this->error( 1 );
		}

		// quick file extension check
		$name_parts = pathinfo( $_FILES['wptv_file']['name'] );

		if ( ! empty( $name_parts['extension'] ) ) {
			// Changes to this must be synced with the anonymous JavaScript function in anon-upload-template.php
			$valid_extensions = array(
				'avi', 'mov', 'qt', 'mpeg', 'mpg', 'mpe', 'mp4', 'm4v', 'asf', 'asx', 'wax', 'wmv', 'wmx', 'ogv',
				'3gp', '3g2',
			);

			if ( ! in_array( strtolower( $name_parts['extension'] ), $valid_extensions, true )
			) {
				return $this->error( 2 );
			}
		} else {
			return $this->error( 3 );
		}

		// empty the globals just in case
		$_posted    = $_POST;
		$_requested = $_REQUEST;
		$_got       = $_GET;
		$_POST      = $_REQUEST = $_GET = array();

		$blog_id = get_current_blog_id();

		$anon_post = get_default_post_to_edit( 'post' ); // without saving auto-draft
		$anon_post = get_object_vars( $anon_post );

		$anon_post['post_title']   = 'Uploaded video';
		$anon_post['post_excerpt'] = '';
		$anon_post['post_author']  = $this->drafts_author;
		$anon_post['post_status']  = 'pending';

		// Add default cat according to the "This is a WC video" checkbox
		if ( ! empty( $_posted['wptv_video_wordcamp'] ) ) {
			$anon_post['post_category'] = array( '12784353' ); // add the "WordCampTV" category
		}
		else {
			$anon_post['post_category'] = array( '1' ); // Uncategorized
		}

		// Insert the post and attachment
		$post_id = wp_insert_post( add_magic_quotes( $anon_post ) );

		if ( is_wp_error( $post_id ) ) {
			return $this->error( 5 );
		}

		$attachment_id = $this->handle_upload( $post_id );

		if ( is_wp_error( $attachment_id ) ) {
			return $this->error( 5 );
		}

		// Put the video shortcode in post_content (makes a standard post for wptv).
		$video_data = function_exists( 'video_get_info_by_blogpostid' ) ? video_get_info_by_blogpostid( $blog_id, $attachment_id ) : false;

		if ( ! $video_data || empty( $video_data->guid ) ) {
			return $this->error( 4 );
		}

		wp_update_post( array( 'ID' => $post_id, 'post_content' => '[wpvideo ' . $video_data->guid . ']' ) );

		// we have post_id, add post meta
		$current_user = wp_get_current_user();

		if ( ! empty( $current_user->ID ) ) {
			// use the logged in user name and email
			$anon_author       = $current_user->display_name;
			$anon_author_email = $current_user->user_email;
		} else {
			// validate these in the back-end?
			$anon_author       = $this->sanitize_text( $_posted['wptv_uploaded_by'] );
			$anon_author_email = $this->sanitize_text( $_posted['wptv_email'] );
		}

		$video_title       = $this->sanitize_text( $_posted['wptv_video_title'] );
		$producer_username = $this->sanitize_text( $_posted['wptv_producer_username'] );
		$speakers          = $this->sanitize_text( $_posted['wptv_speakers'] );
		$event             = $this->sanitize_text( $_posted['wptv_event'] );
		$description       = $this->sanitize_text( $_posted['wptv_video_description'], false );
		$language          = $this->sanitize_text( $_posted['wptv_language'] );
		$slides            = $this->sanitize_text( $_posted['wptv_slides_url'] );
		$ip                = $_SERVER['REMOTE_ADDR'];

		$categories = '';
		if ( ! empty( $_posted['post_category'] ) && is_array( $_posted['post_category'] ) ) {
			foreach ( $_posted['post_category'] as $cat ) {
				$cat = (int) $cat;
				if ( $cat ) {
					$categories .= "$cat,";
				}
			}
		}

		$post_meta = array(
			'attachment_id'     => $attachment_id,
			'submitted_by'      => $anon_author,
			'submitted_email'   => $anon_author_email,
			'title'             => $video_title,
			'producer_username' => $producer_username,
			'speakers'          => $speakers,
			'event'             => $event,
			'language'          => $language,
  			'categories'        => $categories,
			'description'       => $description,
			'slides'            => $slides,
			'ip'                => $ip,
		);

		$post_meta['video_guid'] = $video_data->guid;
		update_post_meta( $post_id, '_wptv_submitted_video', $post_meta );

		// put back the globals
		$_POST    = $_posted;
		$_REQUEST = $_requested;
		$_GET     = $_got;

		return true;
	}

	function display() {
		$post = get_post();

		if ( $post->post_author == $this->drafts_author ) {
			$meta = get_post_meta( $post->ID, '_wptv_submitted_video', true );
		}

		if ( empty( $meta ) ) {
			return;
		}

		$attachment_post = get_post( $meta['attachment_id'] );

		$embed_args = array(
			'format'  => 'fmt_std',
			'width'   => 600,
			'context' => 'admin',
		);
		$embed_args['blog_id'] = get_current_blog_id();
		$embed_args['post_id'] = $meta['attachment_id'];

		// Add missing indexes to $meta (necessary for posts that were uploaded before the fields were added)
		$new_fields = array( 'slides', 'producer_username' );
		foreach ( $new_fields as $field ) {
			if ( ! array_key_exists( $field, $meta ) ) {
				$meta[ $field ] = '';
			}
		}

		?>
		<div class="stuffbox" id="review-video">
			<style type="text/css" scoped="scoped">
				#poststuff #anon-data-wrap {
					padding: 5px 15px;
				}

				#review-video h3.hndle {
					cursor: pointer;
				}

				.anon-data {
					padding: 15px 0 0;
				}

				.anon-data p {
					margin: 8px 0;
				}

				.anon-data .label {
					display: inline-block;
					width: 23%;
				}

				.anon-data .data {
					display: inline-block;
					width: 75%;
				}

				.anon-data input[type="text"], .anon-data textarea {
					width: 80%;
				}

				.anon-data a.anon-approve {
					max-width: 19%;
					margin-left: 1%;
				}

				.anon-data .txtarea .data a.anon-approve {
					bottom: 6px;
					position: relative;
				}

				.anon-data .txtarea .label {
					padding: 10px 0;
					vertical-align: top;
				}

				.anon-data a.disabled {
					color: #888;
				}
			</style>
			<h3 class="hndle"><span>Submitted video</span></h3>

			<div id="anon-data-wrap" class="inside">

				<p>To change the default thumbnail image, play the video and click "Capture Thumbnail" button.</p>
				<table>
					<tr>
						<td>
							<?php
								if ( function_exists( 'video_embed' ) ) {
									echo video_embed( $embed_args );
								}
							?>
						</td>
					</tr>
				</table>

				<div class="anon-data">
					<div class="row">
						<p class="label">Submitted by:<br></p>
						<p class="data">
							<input type="text" readonly="readonly" value="<?php echo esc_attr( $meta['submitted_by'] ); ?>"/>
						</p>
					</div>

					<div class="row">
						<p class="label">Email:</p>
						<p class="data">
							<a href="mailto:<?php echo esc_attr( $meta['submitted_email'] ); ?>?Subject=Your%20WordPress.tv%20submission"><?php echo esc_html( $meta['submitted_email'] ); ?></a>
						</p>
					</div>

					<div class="row">
						<p class="label">IP Address:</p>
						<p class="data">
							<a href="<?php echo esc_url( add_query_arg( array( 'query' => $meta['ip'] ), 'http://en.utrace.de' ) ); ?>" target="_blank"><?php echo esc_html( $meta['ip'] ); ?></a> (opens in new tab, shows location of the IP)
						</p>
					</div>

					<div class="row">
						<p class="label">Title:</p>
						<p class="data">
							<input type="text" value="<?php echo esc_attr( $meta['title'] ); ?>"/>
							<a class="button-secondary anon-approve" href="#title">Approve</a>
						</p>
					</div>

					<div class="row">
						<p class="label">Language:</p>
						<p class="data">
							<input type="text" value="<?php echo esc_attr( $meta['language'] ); ?>"/>
							<a class="button-secondary anon-approve" href="#new-tag-language">Approve</a>
						</p>
					</div>

					<div class="row">
						<p class="label">Categories:</p>
						<p class="data" id="anon-approve-cats">
							<?php
								$cats = preg_replace( '/[^0-9,]+/', '', trim( $meta['categories'], ' ,' ) );
								$cats = explode( ',', $cats );
								foreach ( $cats as $cat ) {
									if ( intval( $cat ) ) {
										echo '<a href="#in-category-' . $cat . '" class="anon-cat-link" title="Click to approve">Unknown?</a>, ';
									}
								}
							?>
						</p>
					</div>

					<div class="row">
						<p class="label">Event:</p>
						<p class="data">
							<input type="text" value="<?php echo esc_attr( $meta['event'] ); ?>"/>
							<a class="button-secondary anon-approve" href="#new-tag-event">Approve</a>
						</p>
					</div>

					<div class="row">
						<p class="label">Producer WordPress.org Username:</p>

						<p class="data">
							<input type="text" value="<?php echo esc_attr( $meta['producer_username'] ); ?>"/>
							<a class="button-secondary anon-approve" href="#new-tag-producer-username">Approve</a>
						</p>
					</div>

					<div class="row">
						<p class="label">Speakers:</p>
						<p class="data">
							<input type="text" value="<?php echo esc_attr( $meta['speakers'] ); ?>"/>
							<a class="button-secondary anon-approve" href="#new-tag-speakers">Approve</a>
						</p>
					</div>

					<div class="row txtarea">
						<p class="label">Description:</p>
						<p class="data">
							<textarea rows="10"><?php echo esc_html( $meta['description'] ); ?></textarea>
							<a class="button-secondary anon-approve" href="#excerpt">Approve</a>
						</p>
					</div>

					<div class="row">
						<p class="label">Slides:</p>
						<p class="data">
							<input type="text" value="<?php echo esc_attr( $meta['slides'] ); ?>"/>
							<a class="button-secondary anon-approve" href="#wptv-slides-url">Approve</a>
						</p>
					</div>

					<div class="row">
						<p class="label">Edit attachment:</p>
						<p class="data">
							<a href="<?php echo esc_url( get_edit_post_link( $meta['attachment_id'] ) ); ?>" target="_blank"><?php echo esc_html( $attachment_post->post_title ); ?></a>
						</p>
					</div>
				</div>
			</div>
		</div>
		<script type="text/javascript">
			(function ($) {
				$('#post-body-content').prepend($('#review-video'));

				$(document).ready(function ($) {
					var default_cat = true;

					$('#review-video h3.hndle').bind('click.fold-anon-video', function (e) {
						$('#anon-data-wrap').slideToggle();
					});

					$('#anon-approve-cats a.anon-cat-link').each(function (i, el) {
						var id = el.href.replace(/.*?#/, '#');
						if (id)
							$(el).html($(id).parent().text());
					});

					$('div.anon-data a.anon-approve, #anon-approve-cats a.anon-cat-link').bind('click.anon-approve', function (e) {
						var target = $(e.target), id = target.attr('href'), el = $(id);

						if (target.hasClass('disabled'))
							return;

						target.addClass('disabled');

						if (id.indexOf('#new-tag-') != -1) {
							el.val(target.siblings('input[type="text"]').val());
							el.siblings('.tagadd').click();
						} else if ('#title' == id  || '#wptv-slides-url' == id) {
							el.val(target.siblings('input[type="text"]').val());
						} else if (id == '#excerpt') {
							el.val(target.siblings('textarea').val());
						} else if (target.is('a.anon-cat-link')) {
							if (default_cat) {
								// remove the default category only once
								$('#in-category-12784353').prop('checked', false);
								default_cat = false;
							}

							el.prop('checked', true);
						}

						e.preventDefault();
					});
				});
			})(jQuery);
		</script>
	<?php
	}
}

$_wptv_anon = new WPTV_Anon_Upload;
