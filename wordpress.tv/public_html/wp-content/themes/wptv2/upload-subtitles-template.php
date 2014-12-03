<?php
/**
 * Template Name: Upload Subtitles
 */

function subtitles_upload_css() {
	?>
	<style type="text/css">
	<?php // theme structural css ?>
	html,
	body {
		width: 100%;
		height: 100%;
		margin: 0;
	}

	#page {
		height: auto;
		min-height: 100%;
		position: relative;
		width: 100%;
	}

	#header {
		margin: 0;
		padding-top: 10px;
	}

	#footer {
		position: absolute;
		bottom: 0;
		right: 0;
		left: 0;
	}

	.video-upload {

	}

	.noscript-show p {
		margin: 0 !important;
	}
	<?php // theme structural end ?>

	.container {
		overflow: hidden;
	}

	.video-upload h3 {
		font-size: 22px;
	}

	.video-upload div.error,
	.video-upload div.success {
		border: 1px solid #c00;
		border-radius: 3px;
		background-color: #ffebe8;
		padding: 0 10px;
		margin: 10px 0;
	}

	.video-upload div.success {
		background-color: #edfcd5;
		border-color: #d4ebaf;
	}

	.video-upload div.error p,
	.video-upload div.success p {
		margin: 0.5em 0;
	}

	.video-upload table tr th,
	.video-upload table tr td {
		padding: 7px 0;
		line-height: 1.4em;
	}

	.video-upload table tr th {
		width: 180px;
		font-weight: bold;
	}

	.video-upload p > label.wptv-video-wordcamp-cb {
		display: inline;
		float: none;
	}

	.video-upload table tr th label .required {
		line-height: 15px;
		vertical-align: bottom;
		margin: 0 3px;
	}

	.video-upload input[type="text"],
	.video-upload textarea,
	.video-upload ul.cats-checkboxes {
	    border-radius: 3px;
	    border: 1px solid #dfdfdf;
	    color: #333;
	    background-color: #fff;
	    padding: 4px;
	    width: 329px;
		max-width: 329px;
	}

	.video-upload ul.cats-checkboxes {
		margin-left: 130px;
		height: 150px;
		overflow: auto;
	}

	.video-upload ul.cats-checkboxes ul.children {
		margin-left: 15px;
	}

	.video-upload input[type="text"]:focus,
	.video-upload textarea:focus {
		border-color: #bbb;
	}

	#subtitle-instructions {
		overflow: auto;
	}

		#subtitle-instructions img {
			border: 1px solid #808080;
			max-width: 938px;
		}

	#video-upload-form p.last,
	#video-upload-form table tr td.last {
		padding: 15px 0;
		text-align: left;
	}

	#video-upload-form p .invalid {
		border: 1px solid red;
	}

	#video-upload-form input[type="submit"] {
		font-size: 15px;
		padding: 4px 12px;
	}

	.page-template-anon-upload-template-php .wptv-hero {
		padding: 20px;
	}

	.page-template-anon-upload-template-php .wptv-hero h2 {
		font-size: 24px;
	}

	.video-upload-right .accepted-formats {
		margin-left: 16px;
	}

	.video-upload-right .accepted-formats li {
		list-style: square;
	}

	.video-upload-right h3 {
		padding-bottom: 4px;
	}

	.video-upload .pass-form label {
		float: none;
		display: inline;
		width: auto;
	}
	</style>
	<?php
}

add_action( 'wp_head', 'subtitles_upload_css' );

// Password-protect this form.
if ( post_password_required() ) :
	get_header();
?>
	<div class="wptv-hero">
		<div class="single container">
			<h2><?php esc_html_e( 'Subtitle a Video', 'wptv' ); ?></h2>
		</div>
	</div>

	<div class="container">
		<div class="video-upload">
			<p><?php printf( __( 'Hey there! If you&#8217;re interested in subtitling or captioning videos for WordPress.tv, please fill out the <a href="%s">contact form</a>, and we&#8217;ll be in touch.', 'wptv' ), 'http://wordpress.tv/contact/' ); ?></p>
			<div class="pass-form">
				<?php echo get_the_password_form(); ?>
			</div>
		</div>
	</div>
<?php
	get_footer();
	return;
endif; // post_password_required

if ( ! class_exists( 'VideoPress_Subtitles' ) )
	wp_die( 'Not ready yet.' );

if ( empty( $_GET['video'] ) )
	wp_die( 'Needs a video context.' );

$video_id = absint( $_GET['video'] );
if ( ! wp_attachment_is_video( $video_id ) )
	wp_die( 'You can only subtitle videos.' );

$video = video_get_info_by_blogpostid( get_current_blog_id(), $video_id );
$attachment = get_post( $video_id );
$parent = get_post( $attachment->post_parent );

if ( ! $parent || 'publish' != $parent->post_status )
	wp_die( 'You can not subtitle this video, sorry.' );

get_header();

$message = '';

if ( ! empty( $_REQUEST['error'] ) ) {
	$message = (int) $_REQUEST['error'];

	switch ( $message ) {
		case 1:
			$message = 'Error: please provide a subtitles file.';
			break;
		case 2:
			$message = 'Error: invalid file type.';
			break;
		case 3:
			$message = 'Error: unknown file type.';
			break;
		case 4:
			$message = 'Error: please provide a WordPress.org username and a valid email address.';
			break;
		case 5:
			$message = 'Unknown error. Please try again later.';
			break;
		case 6:
			$message = 'Error: invalid submission.';
			break;
		case 7:
			$message = 'Error: invalid language.';
			break;
		case 8:
			$message = 'Error: it looks like there already is a subtitles file for the selected language.';
			break;
	}
	$message = '<div class="error"><p>' . $message . '</p></div>';
} elseif ( ! empty( $_REQUEST['success'] ) ) {
	$message = '<div class="success"><p>Your subtitles file has been submitted successfully and is awaiting moderation. Thank you!</p></div>';
}
?>

<div class="wptv-hero">
<div class="single container">
	<h2><?php esc_html_e( 'Subtitle a Video', 'wptv' ); ?></h2>
</div>
</div>

<div class="container">
	<div class="video-upload">
		<?php echo $message; ?>

		<p>Subtitling: <a href="<?php echo esc_url( get_permalink( $parent->ID ) ); ?>"><?php echo apply_filters( 'the_title', $parent->post_title ); ?></a></p>

		<form method="post" action="<?php echo admin_url( 'admin-post.php' ); ?>" id="video-upload-form" enctype="multipart/form-data">

			<?php wp_nonce_field( 'wptv-upload-subtitles', 'wptv-upload-subtitles-nonce' ); ?>
			<input type="hidden" name="action" value="wptv_video_upload_subtitles" />
			<input type="hidden" name="wptv_video_id" value="<?php echo absint( $video_id ); ?>" />

			<table>
				<tr>
					<th><label for="wptv_wporg_username"><?php _e( 'WordPress.org Username' ); ?><span class="required"> * </span></label></th>
					<td>
						<input type="text" id="wptv_wporg_username" name="wptv_wporg_username" /><br />
						To contribute subtitles, you must be a registered user at the <a href="http://wordpress.org">WordPress.org</a> website. Note that this is the username you use to log in at WordPress.org, not the username you use to log in on your own WordPress-powered site.<br />
						If you think you are registered but aren't sure, you can try logging in at <a href="http://wordpress.org/support/bb-login.php">WordPress.org/support</a>.<br />
						If you don't have a WordPress.org username yet, you can <a href="http://wordpress.org/support/register.php">sign up for a free account</a>.
					</td>
				</tr>

				<tr>
					<th><label for="wptv_author_email"><?php esc_html_e( 'Email Address', 'wptv' ); ?><span class="required"> * </span></label></th>
					<td>
						<input type="text" id="wptv_author_email" name="wptv_author_email" />
					</td>
				</tr>

				<tr>
					<th><label for="wptv_language"><?php _e( 'Language' ); ?><span class="required"> * </span></label></th>
					<td>
						<select name="wptv_language">
							<?php $tracks = VideoPress_Subtitles::get_tracks( $video->guid ); ?>
							<?php foreach ( VideoPress_Subtitles::get_languages() as $value => $language ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php disabled( ! empty( $tracks[ $value ] ) ); ?> ><?php echo esc_html( $language['localized_label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<th><label for="wptv_subtitles_file"><?php _e( 'Subtitles File' ); ?><span class="required"> * </span></label></th>
					<td><input type="file" name="wptv_subtitles_file" id="wptv_subtitles_file" /></td>
				</tr>

				<tr>
					<td colspan="2"><em>* All field are required</em></td>
				</tr>

				<tr>
					<td colspan="2" class="last"><input type="submit" id="wptv_subtitles_upload" value="<?php esc_attr_e( 'Submit' ); ?>" /></td>
				</tr>
			</table>
		</form>
	</div>

	<div id="subtitle-instructions">
		<h3><?php esc_html_e( 'Instructions', 'wptv' ); ?></h3>

		<?php
			$instructions = get_post( 17639 );
			setup_postdata( $instructions );
			the_content();
			wp_reset_postdata();
		?>
	</div>
</div>

<?php get_footer();
