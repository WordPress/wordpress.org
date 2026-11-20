<?php
/**
 * The Remove a Job page's notice: the error after a failed removal, or the confirmation after a successful one.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

?>
<?php if ( isset( $_POST['errors'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Display-only redisplay of the plugin's error. ?>
	<div class="notice notice-error">
		<?php
		if ( is_string( $_POST['errors'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Type check only; escaped at output.
			/* translators: %s: Error message. */
			printf( wp_kses_post( __( '<strong>ERROR:</strong> %s', 'wporg-jobs-blocks' ) ), esc_html( wp_unslash( $_POST['errors'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Escaped with esc_html().
		} else {
			echo wp_kses_post( __( '<strong>ERROR:</strong> One or more required fields are missing a value.', 'wporg-jobs-blocks' ) );
		}

		/** This action is documented in wp-content/plugins/wporg-jobs-blocks/templates/content-post-job.php */
		do_action( 'jobswp_notice', 'error' );
		?>
	</div>
<?php elseif ( isset( $_GET['removedjob'] ) && '1' === $_GET['removedjob'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only status flag set by the plugin's redirect. ?>
	<div class="notice notice-success">
		<strong><?php esc_html_e( 'Your job posting has been successfully removed.', 'wporg-jobs-blocks' ); ?></strong>
	</div>
<?php endif; ?>
