<?php get_header(); ?>

<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

	<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">

		<div class="wrapper">
			<div id="bbp-user-<?php bbp_current_user_id(); ?>" class="bbp-single-user">
				<div class="entry-content">

					<?php bbp_get_template_part( 'content', 'single-user' ); ?>

				</div><!-- .entry-content -->
			</div><!-- #bbp-user-<?php bbp_current_user_id(); ?> -->
		</div>

	</div>

</main>

<?php get_footer(); ?>
