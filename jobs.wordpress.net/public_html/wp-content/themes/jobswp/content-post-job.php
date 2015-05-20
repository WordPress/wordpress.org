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
		<span>!</span><span><?php _e( 'Please review the data you submitted for accuracy. Make any necessary corrections, then re-submit this form.', 'jobswp' ); ?></span>
		<?php do_action( 'jobswp_notice', 'verify' ); ?>
	</div>

<?php endif; ?>

<form class="post-job" method="post" action="">

<div class="post-job-contact-info grid_5 alpha omega">
	<h3 class="post-field-section-header"><?php _e( 'Job Poster Contact Information', 'jobswp' ); ?></h3>
	<div class="post-field-section-subheader"><?php _e( '(this information is not publicly displayed)', 'jobswp' ); ?></div>

	<?php jobswp_text_field( 'first_name', __( 'First Name', 'jobswp' ), true ); ?>

	<?php jobswp_text_field( 'last_name', __( 'Last Name', 'jobswp' ), true ); ?>

	<?php jobswp_text_field( 'email', __( 'Email Address', 'jobswp' ), true, 'email' ); ?>

	<?php jobswp_text_field( 'phone', __( 'Phone Number', 'jobswp' ), true, 'tel' ); ?>

</div>

<div class="post-job-company-info grid_4 alpha omega">

	<h3 class="post-field-section-header"><?php _e( 'Company Information', 'jobswp' ); ?></h3>
	<div class="post-field-section-subheader"><?php _e( '(publicly displayed)', 'jobswp' ); ?></div>

	<?php jobswp_text_field( 'company', __( 'Company Name', 'jobswp' ), true ); ?>

	<?php jobswp_text_field( 'location', __( 'Location', 'jobswp' ) ); ?>

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
					<option value="<?php esc_attr_e( $cat->slug ); ?>" <?php echo jobswp_field_value( 'category', esc_attr( $cat->slug ) ); ?>><?php echo $cat->name; ?></option>
				<?php endforeach; ?>
			</select>
		</div>

		<div class="post-job-input">
			<label for="jobtype"><?php _e( 'Job Type' ); ?>*</label>
			<select name="jobtype" class="<?php echo jobswp_required_field_classes( 'jobtype' ); ?>" required>
				<option value="" selected="selected" disabled="disabled"></option>
				<option value="ft" <?php echo jobswp_field_value( 'jobtype', 'ft' ); ?>><?php _e( 'Full Time', 'jobswp' ); ?></option>
				<option value="pt" <?php echo jobswp_field_value( 'jobtype', 'pt' ); ?>><?php _e( 'Part Time', 'jobswp' ); ?></option>
				<option value="ppt" <?php echo jobswp_field_value( 'jobtype', 'ppt' ); ?>><?php _e( 'Project', 'jobswp' ); ?></option>
			</select>
		</div>
	</div>

	<div class="clear"></div>

	<div class="">

		<div class="post-job-input">
			<label for="job_title"><?php _e( 'Job Description' ); ?>*</label>
			<textarea name="job_description" rows="10" class="<?php echo jobswp_required_field_classes( 'job_description' ); ?>"><?php echo jobswp_field_value( 'job_description' ); ?></textarea>
			<p><?php echo sprintf( __( 'Line and paragraph breaks are automatic. <acronym title="Hypertext Markup Language">HTML</acronym> allowed: <code>%s</code>', 'jobswp' ), jobswp_allowed_tags() ); ?></p>
			<p><?php _e( 'All job postings are moderated prior to appearing on the site.' ); ?></p>
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
	<?php } else {
		$button_label = __( 'Submit Job', 'jobswp' );
	} ?>
	<input class="submit-job" type="submit" name="submitjob" value="<?php echo esc_attr( $button_label ); ?>" />
</div>

</div>

</form>
