<?php

/**
 * User Subscriptions
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_user_subscriptions' ); ?>

<?php if ( bbp_is_subscriptions_active() ) : ?>

	<?php if ( bbp_is_user_home() || current_user_can( 'edit_user', bbp_get_displayed_user_id() ) ) : ?>

		<div id="bbp-user-subscriptions" class="bbp-user-subscriptions">

			<?php bbp_get_template_part( 'form', 'topic-search' ); ?>

			<h2 class="entry-title"><?php esc_html_e( 'Subscribed Topics', 'wporg-forums' ); ?></h2>
			<div class="bbp-user-section">

				<?php if ( bbp_get_user_topic_subscriptions() ) : ?>

					<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

					<?php bbp_get_template_part( 'loop',       'topics' ); ?>

					<?php bbp_get_template_part( 'pagination', 'topics' ); ?>

				<?php else :
					if ( bbp_get_user_id() == get_current_user_id() ) {
						echo '<p>' . __( 'You are not currently subscribed to any topics.', 'wporg-forums' ) . '</p>';
					} else {
						echo '<p>' . __( 'This user is not currently subscribed to any topics.', 'wporg-forums' ) . '</p>';
					}
				endif ?>

			</div>
		</div><!-- #bbp-user-subscriptions -->

	<?php endif; ?>

<?php endif; ?>

<?php do_action( 'bbp_template_after_user_subscriptions' );
