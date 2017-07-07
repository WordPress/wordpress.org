<?php

/**
 * User Topics Replied To
 *
 * @package bbPress
 * @subpackage Theme
 */

do_action( 'bbp_template_before_user_topics_replied_to' ); ?>

<div id="bbp-user-topics-replied-to" class="bbp-user-topics-replied-to">
	<h2 class="entry-title"><?php esc_html_e( 'Topics Replied To', 'wporg-forums' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_replies_created() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'replies' ); ?>

			<?php bbp_get_template_part( 'loop',       'reply-topics' ); ?>

			<?php bbp_get_template_part( 'pagination', 'replies' ); ?>

		<?php else : ?>

			<p><?php bbp_is_user_home()
				? esc_html_e( 'You have not replied to any topics.',      'wporg-forums' )
				: esc_html_e( 'This user has not replied to any topics.', 'wporg-forums' );
			?></p>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-topics-replied-to -->

<?php do_action( 'bbp_template_after_user_topics_replied_to' );
