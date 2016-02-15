<?php
/**
 * Issue Submission Form
 */

add_action('pre_option_p2_hide_sidebar', '__return_true');
remove_action( 'wp_head', 'p2_hidden_sidebar_css' );

// user must be logged into w.org to submit an issue
if ( !is_user_logged_in() ) {
    auth_redirect();
    exit();
}

get_header();

?>
<style>
.sleeve_main { margin-left: 0; margin-right: 0; }
#wrapper { background: transparent; }
</style>
<div class="sleeve_main">

	<div id="main">
		<h2><?php the_title(); ?></h2>
	</div> <!-- main -->
	<div id="postbox">

		<div class="issueinputarea">
			<form id="new_issue" name="new_issue" method="post" action="<?php echo site_url(); ?>/">
				<div class="postrow thanks">
					<p><?php printf(__('Howdy %1$s, thanks for taking the time to report an issue and to help improve <strong>%2$s</strong>.', 'wporg'),
						esc_html($current_user->display_name),
						get_issue_location() ); ?></p>
				</div>
				<div class="issuelink">
					<p><?php _e("We think the issue you're reporting is on this page:", 'wporg'); ?></p>
					<input id="issue_link" name="issue_link" type="text" autocomplete="off"
						value="<?php echo esc_url( get_issue_location_url() ); ?>" />
					<p class="right"><?php _e("If this page isn't the right one, please correct it.", 'wporg'); ?></p>
				</div>
				<div class="issuetype">
					<p><?php _e("What issue would you like to report?", 'wporg'); ?></p>
					<?php issue_type_dropdown(); ?>
				</div>
				<p class="recommended"><?php _e("Do you have any recommended actions? (This may be a text change if you've spotted a spelling mistake.)", 'wporg'); ?></p>
				<textarea class="expand70-200" name="issuetext" id="issuetext" rows="4" cols="60"></textarea>
				<div class="postrow issuesubmit">
					<input id="submitissue" type="submit" value="<?php esc_attr_e( 'Report an Issue', 'p2' ); ?>" />
				</div>
				<input type="hidden" name="action" value="post-issue" />
				<?php wp_nonce_field( 'new-post-issue' ); ?>
			</form>

		</div>

		<div class="clear"></div>

	</div> <!-- // postbox -->

</div> <!-- sleeve -->

<?php get_footer(); ?>

