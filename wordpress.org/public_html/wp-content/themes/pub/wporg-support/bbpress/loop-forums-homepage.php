<?php do_action( 'bbp_template_before_forums_loop' ); ?>


<section>
	<h2 class="has-heading-5-font-size">Forums</h2>

	<div id="forums-list-<?php bbp_forum_id(); ?>" class="bbp-forums wp-block-group is-style-cards-grid has-small-font-size is-layout-grid wp-block-group-is-layout-grid">

		<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

			<?php bbp_get_template_part( 'loop', 'single-forum-homepage' ); ?>

		<?php endwhile; ?>

	</div>
</div>

<section>
	<h2 class="has-heading-5-font-size"><?php _e( 'Topics', 'wporg-forums' ); ?></h2>

	<div class="wp-block-group is-style-cards-grid has-small-font-size is-layout-grid wp-block-group-is-layout-grid">
		<?php wporg_support_get_views(); ?>
	</div>
</section>

<section>
	<p><?php
		/* translators: 1: Theme Directory URL, 2: Plugin Directory URL */
		printf( __( 'Looking for help with a specific <a href="%1$s">Theme</a> or <a href="%2$s">Plugin</a>?', 'wporg-forums' ),
			esc_url( __( 'https://wordpress.org/themes/', 'wporg-forums' ) ),
			esc_url( __( 'https://wordpress.org/plugins/', 'wporg-forums' ) ),
		);
	?></p>

	<p><?php _e( 'Every theme and plugin has their own. Head to their individual pages and click "View support forum".', 'wporg-forums' ); ?></p>
</section>

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
