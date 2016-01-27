<?php
/*
Template Name: Submit
*/

if (function_exists('set_recaptcha_globals') ) {
	set_recaptcha_globals();
} else {
	$use_recaptcha = false;
}

get_header();

$latest_release = WP_CORE_LATEST_RELEASE;

if ( !empty( $_POST ) ) {
	showcase_handle_submission();
}
?>

<div id="pagebody">
        <div class="wrapper">
			<?php get_template_part( 'sidebar', 'left' ); ?>
                <div class="col-7">

<?php if ( $_POST && ! $error ) : ?>
	<div id="return">
	<h3><?php _e( 'Submitted!', 'wporg-showcase' ); ?></h3>
	<p><?php printf(
		/* translators: %s: URL of the site submission form */
		__( 'Thanks! You have successfully submitted a site for consideration to be added to the WordPress Showcase. If the site you submitted is added, you will be contacted via email within one week. We appreciate your interest in the WordPress Showcase! If you\'d like to submit another site, head back to the <a href="%s">submission form</a>.', 'wporg-showcase' ),
		'https://wordpress.org/showcase/submit-a-wordpress-site/'
	); ?></p>
	</div>
<?php endif; // $_POST && ! $error ?>

<?php
if ( empty( $_POST ) || $error ) {
	if ( have_posts() ) :
		while ( have_posts() ) :
			the_post();
			breadcrumb();
?>

<div class="storycontent">
	<?php the_content(); ?>
</div>

<?php
		endwhile; // have_posts
	endif; // have_posts
?>

	<?php if ( $error ) : ?>
		<h3 id="return"><?php _e( 'Whoops!', 'wporg-showcase' ); ?></h3>

		<?php if ( strstr( $url, 'blogspot.com' ) || strstr( $url, 'blogger.com' ) ) : ?>
			<p><?php _e( 'Please submit a WordPress blog URL. Blogspot/Blogger blogs are not accepted.', 'wporg-showcase' ); ?></p>

		<?php elseif ( $site_detected == "NO" ) : ?>
			<p><?php _e( 'We didn\'t detect WordPress at the given URL. Please submit the URL of a site running WordPress.', 'wporg-showcase' ); ?></p>

		<?php elseif ( $site_detected == "YES" && version_compare($site_version, $latest_release, '<' ) ) : ?>
			<p><?php _e( 'We were unable to detect the latest version of WordPress at the given URL. We\'d prefer submissions to the showcase to be running up-to-date versions of WordPress.', 'wporg-showcase' ); ?></p>
			<p><?php _e( 'If you\'re sure the site is running the latest version of WordPress, then please check the URL to make sure it\'s accurate, and that the URL you submit points directly to the location where WordPress is running.', 'wporg-showcase' ); ?></p>

		<?php else : ?>
			<p><?php _e( 'There seems to have been a problem with the information you entered. Please make sure all fields have data and resubmit.', 'wporg-showcase' ); ?></p>

		<?php endif; ?>

	<?php endif; // $error ?>

<form action="/showcase/submit-a-wordpress-site/#return" method="post" id="submitform">
	<input type="hidden" name="comment_post_ID" value="<?php echo $post->ID; ?>" />

	<p><label for="submitname"><?php _e( 'Your Name', 'wporg-showcase' ); ?></label><br />
	<input type="text" name="submitname" id="submitname" class="text" value="<?php echo esc_attr( $submitname ); ?>" size="28" tabindex="1" /></p>

	<p><label for="email"><?php _e( 'Your E-mail', 'wporg-showcase'); ?></label><br />
	<input type="text" name="email" id="email" value="<?php echo esc_attr( $email ); ?>" size="28" tabindex="2" class="text" /></p>

	<p><label for="url"><?php _e( 'Site URL', 'wporg-showcase' ); ?></label><br />
	<input type="text" name="url" id="url" value="<?php echo esc_url( $url ); ?>" size="28" tabindex="3" class="text" /></p>

	<p><label for="owner"><?php _e( "Do you own this site? (It's okay if you don't - we just want to know for contact purposes.)", 'wporg-showcase' ); ?></label><br />
	<select name="owner" id="owner">
		<option value="yes" <?php selected( $owner, 'yes' ); ?> ><?php _e( 'Yes', 'wporg-showcase' ); ?></option>
		<option value="no" <?php selected( $owner, 'no' ); ?> ><?php _e( 'No', 'wporg-showcase' ); ?></option>
	</select>

	<p><label for="description"><?php _e( 'Please describe the site and, if applicable, the person or organization it represents.', 'wporg-showcase' ); ?></label><br />
	<textarea name="description" id="description" cols="60" rows="4" tabindex="4" class="text"><?php echo esc_textarea( $description ); ?></textarea></p>

	<p><label for="why"><?php _e( 'What justifies this site being added to the WordPress Showcase? What makes it unique or interesting?', 'wporg-showcase' ); ?></label><br />
	<textarea name="why" id="why" cols="60" rows="4" tabindex="5" class="text"><?php echo esc_textarea( $why ); ?></textarea></p>

	<p class="required"><?php _e( '* All fields are required.', 'wporg-showcase' ); ?></p>

	<?php
	if ($use_recaptcha) {
		$recaptcha_url = 'http://www.google.com/recaptcha/api/challenge?k=' . $recaptcha_pubkey;
		if ( !empty( $recaptcha_error ) )
			$recaptcha_url .= '&error=' . $recaptcha_error;
	?>
	<script type="text/javascript" src="<?php echo $recaptcha_url; ?>"></script>
	<noscript>
	<iframe src="http://www.google.com/recaptcha/api/noscript?k=your_public_key" height="300" width="500" frameborder="0"></iframe><br>
	<textarea name="recaptcha_challenge_field" rows="3" cols="40"></textarea>
	<input type="hidden" name="recaptcha_response_field" value="manual_challenge">
	</noscript>
	<?php
	} // $use_recaptcha
	?>

	<p><input id="submit" type="submit" tabindex="6" value="<?php esc_attr_e( 'Submit Site', 'wporg-showcase' ); ?>" class="button" /></p>
	<?php do_action( 'comment_form', $post->ID ); ?>
</form>
<?php } // empty( $_POST ) || $error ?>

		</div>
		<?php get_sidebar( 'right' ); ?>
	</div>
</div>
<?php get_footer(); ?>
