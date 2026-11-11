<?php
/**
 * The form for removing a job with its token.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

?>
<form class="post-job" method="post" action="">
	<?php jobswp_text_field( 'job_token', __( 'Job Token:', 'wporg-jobs-blocks' ) ); ?>
	<input type="hidden" name="removejob" value="1" />
	<?php wp_nonce_field( 'jobswpremovejob' ); ?>
	<?php
	/**
	 * Fires in the Remove a Job form, before the submit button.
	 */
	do_action( 'jobswp_remove_job_form' );
	?>
	<input class="btn btn-primary submit-job wp-element-button" type="submit" name="submitjob" value="<?php esc_attr_e( 'Remove job', 'wporg-jobs-blocks' ); ?>" />
</form>
