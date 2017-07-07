<?php

/**
 * User Active Topics
 *
 * @package bbPress
 * @subpackage Theme
 */

do_action( 'bbp_template_before_user_active_topics' ); ?>

<div id="bbp-user-active-topics" class="bbp-user-active-topics">
	<h2 class="entry-title"><?php esc_html_e( 'Active Topics', 'wporg-forums' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_topics_started() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

			<?php bbp_get_template_part( 'loop',       'topics' ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

		<?php else : ?>

			<p><?php bbp_is_user_home()
				? esc_html_e( 'You have not created any topics.',      'wporg-forums' )
				: esc_html_e( 'This user has not created any topics.', 'wporg-forums' );
			?></p>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-active-topics -->

<?php do_action( 'bbp_template_after_user_active_topics' );
