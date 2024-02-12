<?php

/**
 * Registers the upload shortcode.
 */
function wporg_themes_upload_shortcode() {
	add_shortcode( 'wporg-themes-upload', 'wporg_themes_render_upload_shortcode' );
}
add_action( 'init', 'wporg_themes_upload_shortcode' );

/**
 * Sets upload size limit to limit theme ZIP file uploads to 10 MB.
 */
function wporg_themes_upload_size_limit() {
	return 10 * MB_IN_BYTES;
}
add_filter( 'upload_size_limit', 'wporg_themes_upload_size_limit', 10, 0 );

/**
 * Allows upload of .zip files on Multisite.
 */
function wporg_themes_upload_allow_zip( $allowed ) {
	return "$allowed zip";
}
add_filter( 'site_option_upload_filetypes', 'wporg_themes_upload_allow_zip' );

/**
 * Renders the upload shortcode.
 */
function wporg_themes_render_upload_shortcode() {
	if ( ! is_user_logged_in() ) {
		return sprintf(
			'<p>' . __( 'Before you can upload a new theme, <a href="%s">please log in</a>.', 'wporg-themes' ) . '</p>',
			add_query_arg(
				'redirect_to',
				urlencode( 'https://wordpress.org/themes/upload/' ),
				'https://login.wordpress.org/'
			)
		);
	}

	$notice       = '';
	$terms_notice = '';

	if (
		! empty( $_POST['_wpnonce'] ) &&
		wp_verify_nonce( $_POST['_wpnonce'], 'wporg-themes-upload' ) &&
		'upload' === $_POST['action']
	) {
		$messages = wporg_themes_process_upload();

		$notice_content = '';
		$code           = '';

		if ( is_wp_error( $messages ) ) {
			foreach ( $messages->get_error_codes() as $code ) {
				$message         = $messages->get_error_message( $code );
				$notice_content .= "<li class='error-code-{$code}'>{$message}</li>";
			}
		} else {
			$notice_content = "<li>{$messages}</li>";
		}

		if ( 'pre_upload_terms' === $code ) {
			$terms_notice = "<div class='notice notice-error notice-large'><ul>{$notice_content}</ul></div>";
		} elseif ( is_wp_error( $messages ) ) {
			$notice = "<div class='notice notice-error notice-large'><ul>{$notice_content}</ul></div>";
		} else {
			$notice = "<div class='notice notice-warning notice-large'><ul>{$notice_content}</ul></div>";
		}
	}

	return $notice . '<h2>' . __( 'Select your zipped theme file', 'wporg-themes' ) . '</h2>
		<form enctype="multipart/form-data" id="upload_form" method="POST" action="" onsubmit="jQuery(\'#upload_button\').attr(\'disabled\',\'disabled\'); return true;">
			' . wp_nonce_field( 'wporg-themes-upload', '_wpnonce', true, false ) . '
			<input type="hidden" name="action" value="upload"/>
			<input type="file" id="zip_file" name="zip_file" size="25"/>
			<p>
				<small>' . sprintf( __( 'Maximum allowed file size: %s', 'wporg-themes' ), esc_html( size_format( wp_max_upload_size() ) ) ) . '</small>
			</p>

			' . $terms_notice . '

			<p class="upload-checkboxes">
				<label><input type="checkbox" required="required" name="required_terms[permission]"><span>' . __( 'I have permission to upload this theme to WordPress.org for others to use and share.', 'wporg-themes' ) . '</span></label>
				<label><input type="checkbox" required="required" name="required_terms[guidelines]"><span>' . sprintf( __( 'The theme complies with all <a href="%s">Theme Guidelines</a>.', 'wporg-themes' ), 'https://make.wordpress.org/themes/handbook/review/required/' ) . '</span></label>
				<label><input type="checkbox" required="required" name="required_terms[gpl]"><span>' . sprintf( __( 'The theme, and all included assets, <a href="%s">are licenced as GPL or are under a GPL compatible license</a>.', 'wporg-themes' ), 'https://make.wordpress.org/themes/handbook/review/required/#1-licensing-copyright' ) . '</span></label>
			</p>

			<button id="upload_button" class="button" type="submit" value="' . esc_attr__( 'Upload', 'wporg-themes' ) . '">' . esc_html__( 'Upload', 'wporg-themes' ) . '</button>
		</form>';
}

/**
 * Runs basic checks and hands off to the upload processor.
 *
 * @return WP_Error|string Failure or success message.
 */
function wporg_themes_process_upload( ) {
	if ( ! is_user_logged_in() ) {
		return new WP_Error(
			'not_logged_in',
			__( 'You must be logged in to upload a new theme.', 'wporg-themes' )
		);
	}

	if ( empty( $_FILES['zip_file'] ) ) {
		return new WP_Error(
			'invalid_upload',
			__( 'Error in file upload.', 'wporg-themes' )
		);
	}

	if ( empty( $_POST['required_terms'] ) || count( $_POST['required_terms'] ) !== 3 ) {
		return new WP_Error(
			'pre_upload_terms',
			__( 'Please agree to terms below.', 'wporg-themes' )
		);
	}

	if ( ! class_exists( 'WPORG_Themes_Upload' ) ) {
		include_once __DIR__ . '/class-wporg-themes-upload.php';
	}

	$upload  = new WPORG_Themes_Upload;
	$message = $upload->process_upload( $_FILES['zip_file'] );

	return $message;
}
