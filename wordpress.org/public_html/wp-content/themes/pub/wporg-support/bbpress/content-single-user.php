<?php

/**
 * Single User Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<div id="bbpress-forums" class="bbpress-wrapper">

	<?php do_action( 'bbp_template_notices' ); ?>

	<?php do_action( 'bbp_template_before_user_wrapper' ); ?>

	<div id="bbp-user-wrapper">

		<?php bbp_get_template_part( 'user', 'details' ); ?>

		<div id="bbp-user-body">
			<?php if ( bbp_is_favorites()                     ) bbp_get_template_part( 'user', 'favorites'       ); ?>
			<?php if ( bbp_is_subscriptions()                 ) bbp_get_template_part( 'user', 'subscriptions'   ); ?>

			<?php
				if ( function_exists( 'bbp_is_single_user_engagements' ) && bbp_is_single_user_engagements() ) {
					bbp_get_template_part( 'user', 'engagements' );
				}
			?>

			<?php if ( bbp_is_single_user_topics()            ) bbp_get_template_part( 'user', 'topics-created'  ); ?>
			<?php if ( bbp_is_single_user_replies()           ) bbp_get_template_part( 'user', 'replies-created' ); ?>
			<?php if ( wporg_support_is_single_user_reviews() ) bbp_get_template_part( 'user', 'reviews-created' ); ?>
			<?php if ( bbp_is_single_user_edit()              ) bbp_get_template_part( 'form', 'user-edit'       ); ?>
			<?php if ( bbp_is_single_user_profile()           ) bbp_get_template_part( 'user', 'profile'         ); ?>
		</div>
	</div>

	<?php do_action( 'bbp_template_after_user_wrapper' ); ?>

</div>
