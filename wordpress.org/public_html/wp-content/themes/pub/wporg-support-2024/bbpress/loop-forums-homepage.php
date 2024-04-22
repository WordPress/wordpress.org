<?php do_action( 'bbp_template_before_forums_loop' ); ?>

<?php echo do_blocks( '<!-- wp:wporg/forums-list -->'); ?>

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
