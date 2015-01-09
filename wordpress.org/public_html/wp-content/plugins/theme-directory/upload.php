<?php

/**
 * Registers the upload shortcode.
 */
function wporg_themes_upload_shortcode() {
	add_shortcode( 'wporg-themes-upload', 'wporg_themes_render_upload_shortcode' );
}
add_action( 'init', 'wporg_themes_upload_shortcode' );

/**
 * Renders the upload shortcode.
 */
function wporg_themes_render_upload_shortcode() {
	if ( ! defined( 'THEME_TRACBOT_PASSWORD' ) || ! defined( 'THEME_DROPBOX_PASSWORD' ) ) {
		printf( '<!-- %s -->', 'Please define SVN and Trac passwords.' );
		return;
	}

	if ( is_user_logged_in() ) :

		if ( ! empty( $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'wporg-themes-upload' ) && 'upload' === $_POST['action'] ) {
			$message = wporg_themes_process_upload();

			if ( ! empty( $message ) ) {
				echo "<div class='notice notice-warning'><p>{$message}</p></div>\n";
			}
		}
	?>
		<h4><?php _e( 'Select your zipped theme file', 'wporg-themes' ); ?></h4>
		<form enctype="multipart/form-data" id="upload_form" method="POST" action="">
			<?php wp_nonce_field( 'wporg-themes-upload' ); ?>
			<input type="hidden" name="action" value="upload"/>
			<input type="file" id="zip_file" name="zip_file" size="25"/>
			<input id="upload_button" class="button" type="submit" value="<?php esc_attr_e( 'Upload', 'wporg-themes' ); ?>"/>

			<p>
				<small><?php printf( __( 'Maximum allowed file size: %s', 'wporg-themes' ), esc_html( wporg_themes_get_max_allowed_file_size() ) ); ?></small>
			</p>
		</form>
	<?php else : ?>
		<p><?php printf( __( 'Before you can upload a new theme, <a href="%s">please log in</a>.', 'wporg-themes' ), esc_url( 'https://wordpress.org/support/bb-login.php' ) ); ?><p>
	<?php endif;
}

/**
 * Returns a human readable version of the max allowed upload size.
 *
 * @return string The allowed file size.
 */
function wporg_themes_get_max_allowed_file_size() {
	$upload_size_unit = wp_max_upload_size();
	$byte_sizes       = array( 'KB', 'MB', 'GB' );

	for ( $unit = - 1; $upload_size_unit > 1024 && $unit < count( $byte_sizes ) - 1; $unit++ ) {
		$upload_size_unit /= 1024;
	}
	if ( $unit < 0 ) {
		$upload_size_unit = $unit = 0;
	} else {
		$upload_size_unit = (int) $upload_size_unit;
	}
	return $upload_size_unit . $byte_sizes[ $unit ];
}

/**
 * Runs basic checks and hands off to the upload processor.
 *
 * @return string Failure or success message.
 */
function wporg_themes_process_upload( ) {
	if ( ! is_user_logged_in() ) {
		return __( 'You must logged in to upload a new theme.', 'wporg-themes' );
	}

	if ( 0 !== $_FILES['zip_file']['error'] ) {
		return __( 'Error in file upload.', 'wporg-themes' );
	}

	if ( ! class_exists( 'WPORG_Themes_Upload' ) ) {
		include_once plugin_dir_path( __FILE__ ) . 'class-wporg-themes-upload.php';
	}

	$upload = new WPORG_Themes_Upload;

	return $upload->process_upload();
}
