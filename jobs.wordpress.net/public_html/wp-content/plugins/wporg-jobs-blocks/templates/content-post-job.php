<?php
/**
 * The form for posting a job.
 *
 * @package wporg-jobs-blocks
 */

declare( strict_types = 1 );

?>
<div class="items-required">* <?php esc_html_e( 'Items are required.', 'wporg-jobs-blocks' ); ?></div>

<?php if ( isset( $_POST['errors'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The Jobs plugin checks its nonce before it sets this flag; display only. ?>

	<div class="notice notice-error">
		<?php
		if ( is_string( $_POST['errors'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Display-only form redisplay; the value is only type-checked here and escaped at output.
			/* translators: %s: Error message. */
			printf( wp_kses_post( __( '<strong>ERROR:</strong> %s', 'wporg-jobs-blocks' ) ), esc_html( wp_unslash( $_POST['errors'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Display-only form redisplay; the value is escaped with esc_html() at output.
		} else {
			echo wp_kses_post( __( '<strong>ERROR:</strong> One or more required fields are missing a value.', 'wporg-jobs-blocks' ) );
		}

		/**
		 * Fires inside a form notice, after its message.
		 *
		 * @param string $type Notice type: 'error' or 'verify'.
		 */
		do_action( 'jobswp_notice', 'error' );
		?>
	</div>

<?php elseif ( isset( $_POST['verify'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The Jobs plugin checks its nonce before it sets this flag; display only. ?>

	<div class="notice notice-info">
		<div>
		<?php esc_html_e( 'Please review the data you submitted for accuracy. Make any necessary corrections, then re-submit this form.', 'wporg-jobs-blocks' ); ?>
		<?php
		/** This action is documented in wp-content/plugins/wporg-jobs-blocks/templates/content-post-job.php */
		do_action( 'jobswp_notice', 'verify' );
		?>
		</div>
	</div>

<?php endif; ?>

<form class="post-job" method="post" action="">

<div class="post-job-contact-info">
	<h3 class="post-field-section-header"><?php esc_html_e( 'Job Poster Contact Information', 'wporg-jobs-blocks' ); ?></h3>
	<div class="post-field-section-subheader"><?php esc_html_e( '(this information is not publicly displayed)', 'wporg-jobs-blocks' ); ?></div>

	<?php jobswp_text_field( 'first_name', __( 'First Name', 'wporg-jobs-blocks' ), true ); ?>

	<?php jobswp_text_field( 'last_name', __( 'Last Name', 'wporg-jobs-blocks' ), true ); ?>

	<?php jobswp_text_field( 'email', __( 'Email Address', 'wporg-jobs-blocks' ), true, 'email', __( 'This is the email address you would use in contacting us and for us to contact you.', 'wporg-jobs-blocks' ) ); ?>
</div>

<div class="post-job-company-info">

	<h3 class="post-field-section-header"><?php esc_html_e( 'Company Information', 'wporg-jobs-blocks' ); ?></h3>
	<div class="post-field-section-subheader"><?php esc_html_e( '(publicly displayed)', 'wporg-jobs-blocks' ); ?></div>

	<?php jobswp_text_field( 'company', __( 'Company Name', 'wporg-jobs-blocks' ), true ); ?>

	<?php jobswp_text_field( 'location', __( 'Location', 'wporg-jobs-blocks' ), false, 'text', __( 'The desired location for any applicants and not necessarily your business location. Use \'N/A\' or leave blank if allowing a remote worker from anywhere.', 'wporg-jobs-blocks' ) ); ?>

	<?php $howtoapply_methods = jobswp_get_howtoapply_methods(); ?>
	<div class="post-job-input">
		<label id="howtoapply-label" for="howtoapply"><?php esc_html_e( 'How to Apply', 'wporg-jobs-blocks' ); ?>* <span><?php esc_html_e( '(also specify)', 'wporg-jobs-blocks' ); ?></span></label>
		<div class="howtoapply-inputs">
			<select name="howtoapply_method" id="howtoapply_method" aria-labelledby="howtoapply-label" class="<?php echo esc_attr( jobswp_required_field_classes( 'howtoapply_method' ) ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<?php foreach ( $howtoapply_methods as $method => $label ) : ?>
					<option value="<?php echo esc_attr( $method ); ?>" <?php echo jobswp_field_value( 'howtoapply_method', $method ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select> :
			<input type="text" name="howtoapply" id="howtoapply" class="<?php echo esc_attr( jobswp_required_field_classes( 'howtoapply' ) ); ?>" <?php echo jobswp_field_value( 'howtoapply' ); ?> />
		</div>

		<div class="job-help-text">
			<?php
			printf(
				/* translators: %s: Label for the email how-to-apply method. */
				esc_html__( 'If choosing "%s", use an email address you are comfortable exposing to any visitor of the site. It need not match the private email address asked for in the Contact Information section.', 'wporg-jobs-blocks' ),
				esc_html( $howtoapply_methods['email'] )
			);
			?>
		</div>
	</div>

</div>

<div class="clear"></div>

<div class="post-job-job-info">
	<h3 class="post-field-section-header"><?php esc_html_e( 'Job Details', 'wporg-jobs-blocks' ); ?></h3>

	<div class="post-job-half">

		<?php jobswp_text_field( 'job_title', __( 'Job Title', 'wporg-jobs-blocks' ), true ); ?>

		<?php jobswp_text_field( 'budget', __( 'Budget', 'wporg-jobs-blocks' ) ); ?>

	</div>

	<div class="post-job-half">

		<div class="post-job-input">
			<label for="category"><?php esc_html_e( 'Category', 'wporg-jobs-blocks' ); ?>*</label>
			<select name="category" id="category" class="<?php echo esc_attr( jobswp_required_field_classes( 'category' ) ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<?php foreach ( Jobs_Dot_WP::get_job_categories() as $job_category ) : ?>
					<option value="<?php echo esc_attr( $job_category->slug ); ?>" <?php echo jobswp_field_value( 'category', esc_attr( $job_category->slug ) ); ?>><?php echo esc_html( $job_category->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="post-job-input">
			<label for="jobtype"><?php esc_html_e( 'Job Type', 'wporg-jobs-blocks' ); ?>*</label>
			<select name="jobtype" id="jobtype" class="<?php echo esc_attr( jobswp_required_field_classes( 'jobtype' ) ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<option value="ft" <?php echo jobswp_field_value( 'jobtype', 'ft' ); ?>><?php esc_html_e( 'Full Time', 'wporg-jobs-blocks' ); ?></option>
				<option value="pt" <?php echo jobswp_field_value( 'jobtype', 'pt' ); ?>><?php esc_html_e( 'Part Time', 'wporg-jobs-blocks' ); ?></option>
				<option value="ppt" <?php echo jobswp_field_value( 'jobtype', 'ppt' ); ?>><?php esc_html_e( 'Project', 'wporg-jobs-blocks' ); ?></option>
			</select>
		</div>
	</div>

	<div class="clear"></div>

	<div class="post-job-full">

		<div class="post-job-input">
			<label for="job_description"><?php esc_html_e( 'Job Description', 'wporg-jobs-blocks' ); ?>*</label>
			<textarea name="job_description" id="job_description" rows="10" class="<?php echo esc_attr( jobswp_required_field_classes( 'job_description' ) ); ?>"><?php echo jobswp_field_description_value(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered with wp_filter_kses() for this textarea body. ?></textarea>
			<?php /* translators: %s: List of allowed HTML tags. */ ?>
			<p><?php printf( wp_kses_post( __( 'Line and paragraph breaks are automatic. <acronym title="Hypertext Markup Language">HTML</acronym> allowed: <code>%s</code>', 'wporg-jobs-blocks' ) ), esc_html( jobswp_allowed_tags() ) ); ?></p>
			<p><?php esc_html_e( 'All job postings are moderated prior to appearing on the site.', 'wporg-jobs-blocks' ); ?></p>

			<p><?php esc_html_e( 'Please review your job posting for accuracy. Once submitted, you will not be able to make any changes unless you do so by submitting a contact form request which can take 24 hours or longer to fulfill.', 'wporg-jobs-blocks' ); ?></p>
		</div>

	</div>

	<div class="clear"></div>

	<input type="hidden" name="postjob" value="1" />
	<?php wp_nonce_field( 'jobswppostjob' ); ?>

	<?php
	/**
	 * Fires at the end of the Post a Job form's fields, before the submit button.
	 */
	do_action( 'jobswp_post_job_form' );
	?>

	<?php
	if ( isset( $_POST['verify'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The Jobs plugin checks its nonce before it sets this flag; display only.
		$button_label = __( 'Verify Job', 'wporg-jobs-blocks' );
		?>
		<input type="hidden" name="verify" value="1" />
		<div class="notice notice-info accept">
			<p><?php esc_html_e( 'By submitting a job to this site you acknowledge the following:', 'wporg-jobs-blocks' ); ?></p>
			<ul>
				<li>
					<?php
					/* translators: %s: URL of the FAQ page. */
					printf( wp_kses( __( 'You have read the <a href="%s">FAQ</a> and understand everything listed, especially pertaining to what is unacceptable for a job posting.', 'wporg-jobs-blocks' ), 'jobswp-ui' ), esc_url( home_url( '/faq/' ) ) );
					?>
				</li>
				<li><?php esc_html_e( 'If you provided a contact email address as your method of contact for job seekers, that email address will be made publicly available and you will likely receive a lot of email.', 'wporg-jobs-blocks' ); ?></li>
				<li><?php esc_html_e( 'Upon successful submission, you will not be able to make any edits. Proofread everything again to make sure it&#8217;s what you want.', 'wporg-jobs-blocks' ); ?></li>
				<li><?php echo wp_kses_post( __( 'It rests on you to vet applicants in whatever manner you see fit. We make absolutely no claims or guarantees as to the identity, capabilities, or reliability of applicants. <strong>Hire at your own risk.</strong>', 'wporg-jobs-blocks' ) ); ?></li>
				<li><?php echo wp_kses_post( __( 'Upon successful submission, you will be immediately presented with a job token. <strong>MAKE NOTE OF THE TOKEN</strong>. It is your only means of removing the job from the site in a <em>timely</em> fashion.', 'wporg-jobs-blocks' ) ); ?></li>
			</ul>
			<p>
				<input type="checkbox" name="accept" id="accept" value="1" required /><label for="accept"><?php esc_html_e( 'I agree to the terms stated above.', 'wporg-jobs-blocks' ); ?>*</label>
			</p>
		</div>
		<?php
	} else {
		$button_label = __( 'Submit Job', 'wporg-jobs-blocks' );
	}
	?>
	<input class="btn btn-primary submit-job wp-element-button" id="submit-job" type="submit" name="submitjob" value="<?php echo esc_attr( $button_label ); ?>" />

	<p class="post-job-remove-note">
		<?php
		echo wp_kses(
			sprintf(
				/* translators: %s: URL to the remove a job page */
				__( 'Looking to remove a job? Visit the <a href="%s">job removal page</a>.', 'wporg-jobs-blocks' ),
				esc_url( home_url( '/remove-a-job/' ) )
			),
			'jobswp-ui'
		);
		?>
	</p>
</div>

</form>
