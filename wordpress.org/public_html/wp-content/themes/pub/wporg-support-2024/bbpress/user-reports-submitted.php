<?php

/**
 * User Reports Submitted
 *
 * @package bbPress
 * @subpackage Theme
 */

do_action( 'bbp_template_before_user_reports_submitted' ); ?>

<?php if ( bbp_is_user_home() || current_user_can( 'edit_user', bbp_get_displayed_user_id() ) ) : ?>

	<div id="bbp-user-reports-submitted" class="bbp-user-reports-submitted">
		<h2 class="entry-title"><?php esc_html_e( 'Reports Submitted', 'wporg-forums' ); ?></h2>
		<div class="bbp-user-section">

			<?php if ( bbp_get_user_replies_created() ) : ?>

				<?php bbp_get_template_part( 'pagination', 'replies' ); ?>

				<?php bbp_get_template_part( 'loop',       'replies' ); ?>

				<?php bbp_get_template_part( 'pagination', 'replies' ); ?>

			<?php else : ?>

				<p><?php bbp_is_user_home()
					? esc_html_e( 'You have not reported any topics.',      'wporg-forums' )
					: esc_html_e( 'This user has not reported any topics.', 'wporg-forums' );
				?></p>

			<?php endif; ?>

		</div>
	</div><!-- #bbp-user-reports-submitted -->

<?php endif; ?>

<?php do_action( 'bbp_template_after_user_reports_submitted' );
