<?php

/**
 * User Reviews Written
 *
 * @package bbPress
 * @subpackage Theme
 */

do_action( 'bbp_template_before_user_reviews_written' ); ?>

<div id="bbp-user-reviews-written" class="bbp-user-reviews-written">
	<h2 class="entry-title"><?php esc_html_e( 'Reviews Written', 'wporg-forums' ); ?></h2>
	<div class="bbp-user-section">

		<?php if ( bbp_get_user_topics_started() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

			<?php bbp_get_template_part( 'loop',       'topics' ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

		<?php else : ?>

			<p><?php bbp_is_user_home()
				? esc_html_e( 'You have not written any reviews.',      'wporg-forums' )
				: esc_html_e( 'This user has not written any reviews.', 'wporg-forums' );
			?></p>

		<?php endif; ?>

	</div>
</div><!-- #bbp-user-reviews-written -->

<?php do_action( 'bbp_template_after_user_reviews_written' );
