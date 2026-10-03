<?php
/**
 * Template Name: Caption with DeltaScribe
 */

$wptv_deltascribe_css = get_template_directory() . '/plugins/wordpresstv-deltascribe/caption-with-deltascribe.css';

wp_enqueue_style(
	'wptv-caption-with-deltascribe',
	get_template_directory_uri() . '/plugins/wordpresstv-deltascribe/caption-with-deltascribe.css',
	array( 'wptv-style' ),
	file_exists( $wptv_deltascribe_css ) ? filemtime( $wptv_deltascribe_css ) : false
);

// Password-protect this form.
if ( post_password_required() ) :
	get_header();
	?>
	<div class="wptv-hero">
		<div class="single container">
			<h2><?php esc_html_e( 'Caption a Video with DeltaScribe', 'wptv' ); ?></h2>
		</div>
	</div>

	<div class="container">
		<div class="video-upload">
			<p>
				<?php
				printf(
					wp_kses_post( __( 'Hey there! If you&#8217;re interested in subtitling or captioning videos for WordPress.tv, please fill out the <a href="%s">contact form</a>, and we&#8217;ll be in touch.', 'wptv' ) ),
					esc_url( 'https://wordpress.tv/contact/' )
				);
				?>
			</p>
			<div class="pass-form">
				<?php echo wp_kses_post( get_the_password_form() ); ?>
			</div>
		</div>
	</div>
	<?php
	get_footer();
	return;
endif; // post_password_required

if ( ! class_exists( 'VideoPress_Subtitles' ) ) {
	wp_die( esc_html__( 'Not ready yet.', 'wptv' ) );
}

if ( empty( $_GET['video'] ) ) {
	wp_die( esc_html__( 'Needs a video context.', 'wptv' ) );
}

$video_id = absint( $_GET['video'] );
if ( ! wp_attachment_is_video( $video_id ) ) {
	wp_die( esc_html__( 'You can only caption videos.', 'wptv' ) );
}

$attachment = get_post( $video_id );
$parent     = get_post( $attachment->post_parent );

if ( ! $parent || ! in_array( $parent->post_status, array( 'publish', 'private' ), true ) ) {
	wp_die( esc_html__( 'You can not caption this video, sorry.', 'wptv' ) );
}

get_header();

$message = '';

if ( ! empty( $_REQUEST['error'] ) ) {
	$error_code = (int) $_REQUEST['error'];

	switch ( $error_code ) {
		case 1:
			$message = __( 'Error: please provide a WordPress.org username and a valid email address.', 'wptv' );
			break;
		case 2:
			$message = __( 'Error: invalid language.', 'wptv' );
			break;
		case 3:
			$message = __( 'Error: could not determine this video&#8217;s media URL. Please try again later.', 'wptv' );
			break;
		default:
			$message = __( 'Unknown error. Please try again later.', 'wptv' );
			break;
	}
	$message = '<div class="error"><p>' . esc_html( $message ) . '</p></div>';
}
?>

<div class="wptv-hero">
<div class="single container">
	<h2><?php esc_html_e( 'Caption a Video with DeltaScribe', 'wptv' ); ?></h2>
</div>
</div>

<div class="container">
	<div class="video-upload">
		<?php echo wp_kses_post( $message ); ?>

		<p>
			<?php
			printf(
				wp_kses_post( __( 'Captioning: <a href="%1$s">%2$s</a>', 'wptv' ) ),
				esc_url( get_permalink( $parent->ID ) ),
				esc_html( apply_filters( 'the_title', $parent->post_title ) )
			);
			?>
		</p>

		<p><?php esc_html_e( 'DeltaScribe is a free browser-based tool for timing captions to a video. Fill out the form below, and we&#8217;ll send you over to DeltaScribe with this video already loaded — when you&#8217;re done, click Submit there to send your captions back here for review.', 'wptv' ); ?></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="deltascribe-start-form">

			<?php wp_nonce_field( 'wptv-deltascribe-start', 'wptv-deltascribe-start-nonce' ); ?>
			<input type="hidden" name="action" value="wptv_deltascribe_start" />
			<input type="hidden" name="wptv_video_id" value="<?php echo absint( $video_id ); ?>" />

			<table>
				<tr>
					<th><label for="wptv_wporg_username"><?php esc_html_e( 'WordPress.org Username', 'wptv' ); ?><span class="required"> * </span></label></th>
					<td>
						<input type="text" id="wptv_wporg_username" name="wptv_wporg_username" /><br />
						<?php
						printf(
							wp_kses_post(
								/* translators: 1: WordPress.org URL, 2: WordPress.org login URL, 3: WordPress.org registration URL */
								__( 'To contribute captions, you must be a registered user at the <a href="%1$s">WordPress.org</a> website. Note that this is the username you use to log in at WordPress.org, not the username you use to log in on your own WordPress-powered site.<br />If you think you are registered but aren&#8217;t sure, you can try logging in at <a href="%2$s">login.WordPress.org</a>.<br />If you don&#8217;t have a WordPress.org username yet, you can <a href="%3$s">sign up for a free account</a>.', 'wptv' )
							),
							esc_url( 'https://wordpress.org' ),
							esc_url( 'https://login.wordpress.org/' ),
							esc_url( 'https://login.wordpress.org/register' )
						);
						?>
					</td>
				</tr>

				<tr>
					<th><label for="wptv_author_email"><?php esc_html_e( 'Email Address', 'wptv' ); ?><span class="required"> * </span></label></th>
					<td>
						<input type="text" id="wptv_author_email" name="wptv_author_email" />
					</td>
				</tr>

				<tr>
					<th><label for="wptv_language"><?php esc_html_e( 'Language', 'wptv' ); ?><span class="required"> * </span></label></th>
					<td>
						<select name="wptv_language" id="wptv_language">
							<?php foreach ( VideoPress_Subtitles::get_languages() as $value => $language ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" ><?php echo esc_html( $language['localized_label'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr>
					<td colspan="2"><em><?php esc_html_e( '* All fields are required', 'wptv' ); ?></em></td>
				</tr>

				<tr>
					<td colspan="2" class="last"><input type="submit" id="wptv_deltascribe_start" value="<?php esc_attr_e( 'Continue to DeltaScribe', 'wptv' ); ?>" /></td>
				</tr>
			</table>
		</form>
	</div>

	<div id="deltascribe-instructions">
		<h3><?php esc_html_e( 'Instructions', 'wptv' ); ?></h3>
		<p><?php esc_html_e( 'After you click Continue, DeltaScribe will open in a new page with this video already loaded. Time out your captions there, then click its Submit button to send them back to WordPress.tv for review.', 'wptv' ); ?></p>
	</div>
</div>

<?php get_footer();
