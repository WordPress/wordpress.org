<?php
/**
 * The catchall archive template.
 *
 * If no specific archive layout is defined, we'll go with
 * a generic simplistic one, like this, just to actually
 * be able to show some content.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow entry-content">

			<h1><?php single_cat_title(); ?></h1>

			<div>
			<?php echo do_blocks(
				sprintf(
					'<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%%"},"fontSize":"small"} -->
					<div class="wp-block-group is-style-cards-grid has-small-font-size">%s</div>
					<!-- /wp:group -->',
					wporg_support_get_archive_posts(),
				)
			); ?>

			</div>

			<div class="archive-pagination">
				<?php posts_nav_link(); ?>
			</div>

		</div>

	</main>

<?php
get_footer();

