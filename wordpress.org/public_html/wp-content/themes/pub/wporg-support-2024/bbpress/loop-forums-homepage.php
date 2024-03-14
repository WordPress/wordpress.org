<?php do_action( 'bbp_template_before_forums_loop' ); ?>


<section class="forums-homepage-list">
	<h2 class="has-heading-5-font-size"><?php _e( 'Forums', 'wporg-forums' ); ?></h2>

	<?php echo do_blocks(
		sprintf(
			'<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"bbp-forums is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%%"},"fontSize":"small"} -->
			<div id="forums-list-%s" class="bbp-forums wp-block-group is-style-cards-grid has-small-font-size">%s</div>
			<!-- /wp:group -->',
			esc_attr( bbp_get_forum_id() ),
			wporg_support_get_forums_list(),
		)
	); ?>

</section>

<section class="forums-homepage-topics">
	<h2 class="has-heading-5-font-size"><?php _e( 'Topics', 'wporg-forums' ); ?></h2>

	<?php echo do_blocks(
		sprintf(
			'<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%%"},"fontSize":"small"} -->
			<div class="wp-block-group is-style-cards-grid has-small-font-size">%s</div>
			<!-- /wp:group -->',
			wporg_support_get_views(),
		)
	); ?>
</section>

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
