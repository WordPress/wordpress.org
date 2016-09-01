<?php

/**
 * Single View Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<div id="bbpress-forums">

	<?php do_action( 'wporg_compat_before_single_view' ); ?>

	<?php bbp_breadcrumb(); ?>

	<?php bbp_set_query_name( bbp_get_view_rewrite_id() ); ?>

	<?php if ( bbp_view_query() ) : ?>

		<?php if ( 1 != bbp_get_paged() ) bbp_get_template_part( 'pagination', 'topics' ); ?>

		<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

		<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

	<?php endif; ?>

	<?php do_action( 'wporg_compat_after_single_view' ); ?>

	<?php bbp_reset_query_name(); ?>

</div>
