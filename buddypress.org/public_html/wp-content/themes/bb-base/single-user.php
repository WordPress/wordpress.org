<?php

/**
 * User Profile
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

	<?php do_action( 'bbp_before_main_content' ); ?>

	<h3 id="bbpress-user"><?php bbp_displayed_user_field( 'display_name' ); ?> (@<?php bbp_displayed_user_field( 'user_nicename' ); ?>)</h3>

	<div id="bbp-user-<?php bbp_current_user_id(); ?>" class="bbp-single-user">
		<div class="entry-content">

			<?php bbp_get_template_part( 'content', 'single-user' ); ?>

		</div><!-- .entry-content -->
	</div><!-- #bbp-user-<?php bbp_current_user_id(); ?> -->

	<?php do_action( 'bbp_after_main_content' ); ?>

<?php get_footer(); ?>
