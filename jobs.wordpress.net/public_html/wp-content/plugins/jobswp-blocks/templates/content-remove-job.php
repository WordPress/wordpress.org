<?php
/**
 * The form for removing a job with its token.
 *
 * Ported from the jobswp classic theme's page-remove-a-job.php. The jobswp
 * plugin handles the submission in Jobs_Dot_WP::maybe_remove_job(). The
 * notice above the page's content is content-remove-job-notice.php.
 *
 * @package jobswp-blocks
 */

?>
<form class="post-job" method="post" action="">
	<?php jobswp_text_field( 'job_token', __( 'Job Token:', 'jobswp' ) ); ?>
	<input type="hidden" name="removejob" value="1" />
	<?php wp_nonce_field( 'jobswpremovejob' ); ?>
	<?php do_action( 'jobswp_remove_job_form' ); ?>
	<input class="btn btn-primary submit-job wp-element-button" type="submit" name="submitjob" value="<?php esc_attr_e( 'Remove job', 'jobswp' ); ?>" />
</form>
