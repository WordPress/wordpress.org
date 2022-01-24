<div class="items-required">* <?php _e( 'Items are required.', 'jobswp' ); ?></div>

<?php if ( isset( $_POST['errors'] ) ) : ?>

	<div class="notice notice-error">
		<?php if ( is_string( $_POST['errors'] ) )
			echo sprintf( __( '<strong>ERROR:</strong> %s', 'jobswp' ), esc_html( $_POST['errors'] ) );
		else
			_e( '<strong>ERROR:</strong> One or more required fields are missing a value.', 'jobswp' );
		?>
		<?php do_action( 'jobswp_notice', 'error' ); ?>
	</div>

<?php elseif ( isset( $_POST['verify'] ) ) : ?>

	<div class="notice notice-info">
		<div>
		<?php _e( 'Please review the data you submitted for accuracy. Make any necessary corrections, then re-submit this form.', 'jobswp' ); ?>
		<?php do_action( 'jobswp_notice', 'verify' ); ?>
		</div>
	</div>

<?php endif; ?>

<form class="post-job" method="post" action="">

<div class="post-job-contact-info grid_5 alpha omega">
	<h3 class="post-field-section-header"><?php _e( 'Job Poster Contact Information', 'jobswp' ); ?></h3>
	<div class="post-field-section-subheader"><?php _e( '(this information is not publicly displayed)', 'jobswp' ); ?></div>

	<?php jobswp_text_field( 'first_name', __( 'First Name', 'jobswp' ), true ); ?>

	<?php jobswp_text_field( 'last_name', __( 'Last Name', 'jobswp' ), true ); ?>

	<?php jobswp_text_field( 'email', __( 'Email Address', 'jobswp' ), true, 'email', __( 'This is the email address you would use in contacting us and for us to contact you.', 'jobswp' ) ); ?>
</div>

<div class="post-job-company-info grid_4 alpha omega">

	<h3 class="post-field-section-header"><?php _e( 'Company Information', 'jobswp' ); ?></h3>
	<div class="post-field-section-subheader"><?php _e( '(publicly displayed)', 'jobswp' ); ?></div>

	<?php jobswp_text_field( 'company', __( 'Company Name', 'jobswp' ), true ); ?>

	<?php jobswp_text_field( 'location', __( 'Location', 'jobswp' ), false, 'text',  __( 'The desired location for any applicants and not necessarily your business location. Use \'N/A\' or leave blank if allowing a remote worker from anywhere.', 'jobswp' ) ); ?>

	<div class="post-job-input">
		<label for="howtoapply"><?php _e( 'How to Apply', 'jobswp' ); ?>* <span><?php _e( '(also specify)', 'jobswp' ); ?></span></label>
		<div class="howtoapply-inputs">
			<select name="howtoapply_method" class="<?php echo jobswp_required_field_classes( 'howtoapply_method' ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<option value="email" <?php echo jobswp_field_value( 'howtoapply_method', 'email' ); ?>><?php _e( 'Email Address', 'jobswp' ); ?></option>
				<option value="phone" <?php echo jobswp_field_value( 'howtoapply_method', 'phone' ); ?>><?php _e( 'Phone Number', 'jobswp' ); ?></option>
				<option value="web" <?php echo jobswp_field_value( 'howtoapply_method', 'web' ); ?>><?php _e( 'Online Form', 'jobswp' ); ?></option>
			</select> :
			<input type="text" name="howtoapply" class="<?php echo jobswp_required_field_classes( 'howtoapply' ); ?>" <?php echo jobswp_field_value( 'howtoapply' ); ?> />
		</div>

		<div class="job-help-text">
			<?php _e( 'If choosing "Email Address", use an email address you are comfortable exposing to any visitor of the site. It need not match the private email address asked for in the Contact Information section.', 'jobswp' ); ?>
		</div>
	</div>

</div>

<div class="clear"></div>

<div class="post-job-job-info">
	<h3 class="post-field-section-header"><?php _e( 'Job Details', 'jobswp' ); ?></h3>

	<div class="grid_5 alpha omega">

		<?php jobswp_text_field( 'job_title', __( 'Job Title', 'jobswp' ), true ); ?>

		<?php jobswp_text_field( 'budget', __( 'Budget', 'jobswp' ) ); ?>

	</div>

	<div class="grid_3 alpha omega">

		<div class="post-job-input">
			<label for="category"><?php _e( 'Category', 'jobswp' ); ?>*</label>
			<select name="category" class="<?php echo jobswp_required_field_classes( 'category' ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<?php foreach ( Jobs_Dot_WP::get_job_categories() as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->slug ); ?>" <?php echo jobswp_field_value( 'category', esc_attr( $cat->slug ) ); ?>><?php echo $cat->name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="post-job-input">
			<label for="jobtype"><?php _e( 'Job Type', 'jobswp' ); ?>*</label>
			<select name="jobtype" class="<?php echo jobswp_required_field_classes( 'jobtype' ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<option value="ft" <?php echo jobswp_field_value( 'jobtype', 'ft' ); ?>><?php _e( 'Full Time', 'jobswp' ); ?></option>
				<option value="pt" <?php echo jobswp_field_value( 'jobtype', 'pt' ); ?>><?php _e( 'Part Time', 'jobswp' ); ?></option>
				<option value="ppt" <?php echo jobswp_field_value( 'jobtype', 'ppt' ); ?>><?php _e( 'Project', 'jobswp' ); ?></option>
			</select>
		</div>
	</div>

	<div class="clear"></div>

	<div class="grid_9 alpha omega">

		<div class="post-job-input">
			<label for="job_title"><?php _e( 'Job Description', 'jobswp' ); ?>*</label>
			<textarea name="job_description" rows="10" class="<?php echo jobswp_required_field_classes( 'job_description' ); ?>"><?php echo jobswp_field_value( 'job_description' ); ?></textarea>
			<p><?php echo sprintf( __( 'Line and paragraph breaks are automatic. <acronym title="Hypertext Markup Language">HTML</acronym> allowed: <code>%s</code>', 'jobswp' ), jobswp_allowed_tags() ); ?></p>
			<p><?php _e( 'All job postings are moderated prior to appearing on the site.', 'jobswp' ); ?></p>

			<p><?php _e( 'Please review your job posting for accuracy. Once submitted, you will not be able to make any changes unless you do so by submitting a contact form request which can take 24 hours or longer to fulfill.', 'jobswp' ); ?></p>
		</div>

	</div>

	<div class="clear"></div>

	<input type="hidden" name="postjob" value="1" />
	<?php wp_nonce_field( 'jobswppostjob' ); ?>

	<?php do_action( 'jobswp_post_job_form' ); ?>

	<?php if ( isset( $_POST['verify'] ) ) {
		$button_label = __( 'Verify Job', 'jobswp' );
	?>
		<input type="hidden" name="verify" value="1" />
		<div class="notice notice-info accept">
			<p><?php _e( 'By submitting a job to this site you acknowledge the following:', 'jobswp' ); ?></p>
			<ul>
				<li><?php printf( __( 'You have read the <a href="%s">FAQ</a> and understand everything listed, especially pertaining to what is unacceptable for a job posting.', 'jobswp' ), home_url( '/faq/' ) ); ?></li>
				<li><?php _e( 'If you provided a contact email address as your method of contact for job seekers, that email address will be made publicly available and you will likely receive a lot of email.', 'jobswp' );?></li>
				<li><?php _e( 'Upon successful submission, you will not be able to make any edits. Proofread everything again to make sure it&#8217;s what you want.', 'jobswp' ); ?></li>
				<li><?php _e( 'It rests on you to vet applicants in whatever manner you see fit. We make absolutely no claims or guarantees as to the identity, capabilities, or reliability of applicants. <strong>Hire at your own risk.</strong>', 'jobswp' ); ?></li>
				<li><?php _e( 'Upon successful submission, you will be immediately presented with a job token. <strong>MAKE NOTE OF THE TOKEN</strong>. It is your only means of removing the job from the site in a <em>timely</em> fashion.', 'jobswp' ); ?></li>
			</ul>
			<p>
				<input type="checkbox" name="accept" value="1" required /><label for="accept"><?php _e( 'I agree to the terms stated above.', 'jobswp' ); ?>*</label>
			</p>
		</div>
	<?php } else {
		$button_label = __( 'Submit Job', 'jobswp' );
	} ?>
	<input class="submit-job" type="submit" name="submitjob" value="<?php echo esc_attr( $button_label ); ?>" />
</div>

</div>

</form>
