<?php
/**
 * Confirmation shown after a job has been submitted.
 *
 * @package jobswp
 */

?>
<div class="notice notice-success">
	<h2><?php esc_html_e( 'Thank you for submitting your job posting!', 'jobswp' ); ?></h2>

	<p>
		<?php
		printf(
			/* translators: %s: FAQ URL. */
			wp_kses_post( __( 'Your job posting will be reviewed in the next 24-48 hours to ensure it meets the criteria listed in our <a href="%s">FAQ</a>. After approval, your posting will stay on the job board for a total of 21 days.', 'jobswp' ) ),
			'/faq/'
		);
		?>
	</p>

	<p>
	<?php
	printf(
		/* translators: %s: Remove a job URL. */
		wp_kses_post( __( '<strong>Important!</strong> Take note of the following job token. It is your only means of removing the job from the site in a <em>timely</em> fashion prior to its automatic expiration. (Otherwise, you would have to make a request via the feedback form which could take 24-48 hours or longer to honor). The token is used on the <a href="%s">job removal page</a>.', 'jobswp' ) ),
		'/remove-a-job/'
	); ?></p>

	<p class="job-token">
		<?php
		/* translators: %s: Job token. */
		printf( esc_html__( 'Your job token is: %s', 'jobswp' ), esc_html( wp_unslash( $_POST['job_token'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Display-only form redisplay; the value is escaped with esc_html() at output.
		?>
	</p>

	<p>
		<?php
		printf(
			/* translators: %s: Feedback form URL. */
			wp_kses_post( __( 'If you would like to modify your posting, or you are having problems removing the job using the job token, please contact us using our <a href="%s">feedback form</a>. Be sure to specify the email address you supplied in your job posting.', 'jobswp' ) ),
			'/feedback/'
		);
		?>
	</p>

	<p><?php esc_html_e( 'Below you&#8217;ll find a preview of your job posting.', 'jobswp' ); ?></p>
</div>
