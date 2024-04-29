<?php

/**
 * Template Name: bbPress - Archive
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow entry-content">

			<section>
				<p><?php printf(
					/* Translators: forums URL */
					__( 'Our community-based support forums are a great place to learn, share, and help each other. <a href="%s">Find out how to get started</a>.', 'wporg-forums' ),
					esc_url( site_url( '/welcome/' ) )
				) ?></p>
			</section>

			<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>

		</div>

	</main>

	<?php echo do_blocks(
		sprintf(
			'<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space","top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"}},"border":{"bottom":{"color":"var:preset|color|white-opacity-15","style":"solid","width":"1px"}},"elements":{"link":{"color":{"text":"var:preset|color|white"}}}},"backgroundColor":"charcoal-2","textColor":"white","className":"forums-homepage-footer","layout":{"type":"constrained"}} -->
			<div class="wp-block-group alignfull forums-homepage-footer has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="border-bottom-color:var(--wp--preset--color--white-opacity-15);border-bottom-style:solid;border-bottom-width:1px;padding-top:var(--wp--preset--spacing--40);padding-right:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--40);padding-left:var(--wp--preset--spacing--edge-space)">

				<!-- wp:columns {"align":"wide","style":{"spacing":{"blockGap":{"top":"var:preset|spacing|20"}}},"className":"is-style-default"} -->
				<div class="wp-block-columns alignwide is-style-default">

					<!-- wp:column {"verticalAlignment":"top","width":"50%%","className":"is-left-column","layout":{"inherit":false}} -->
					<div class="wp-block-column is-vertically-aligned-top is-left-column" style="flex-basis:50%%">

						<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"600","lineHeight":"1.3"}},"fontSize":"heading-5"} -->
						<h2 class="wp-block-heading has-heading-5-font-size" style="font-style:normal;font-weight:600;line-height:1.3">%1$s</h2>
						<!-- /wp:heading -->

						<!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"var:preset|spacing|50"}}}} -->
						<div class="wp-block-columns">

							<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}}} -->
							<div class="wp-block-column">

								<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":1.6},"elements":{"link":{"color":{"text":"var:preset|color|blueberry-2"}}}},"textColor":"blueberry-2","className":"is-style-short-text","fontSize":"normal","fontFamily":"inter"} -->
								<h3 class="wp-block-heading is-style-short-text has-blueberry-2-color has-text-color has-link-color has-inter-font-family has-normal-font-size" style="font-style:normal;font-weight:700;line-height:1.6"><a href="https://wordpress.org/documentation/">%2$s</a></h3>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>%3$s</p>
								<!-- /wp:paragraph -->

							</div>
							<!-- /wp:column -->

							<!-- wp:column {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}}} -->
							<div class="wp-block-column">

								<!-- wp:heading {"level":3,"style":{"typography":{"fontStyle":"normal","fontWeight":"700","lineHeight":1.6},"elements":{"link":{"color":{"text":"var:preset|color|blueberry-2"}}}},"textColor":"blueberry-2","className":"is-style-short-text","fontSize":"normal","fontFamily":"inter"} -->
								<h3 class="wp-block-heading is-style-short-text has-blueberry-2-color has-text-color has-link-color has-inter-font-family has-normal-font-size" style="font-style:normal;font-weight:700;line-height:1.6"><a href="https://make.wordpress.org/support/handbook/">%4$s</a></h3>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>%5$s</p>
								<!-- /wp:paragraph -->

							</div>
							<!-- /wp:column -->

						</div>
						<!-- /wp:columns -->

					</div>
					<!-- /wp:column -->

				<!-- wp:column {"width":"50%%","layout":{"inherit":false}} -->
				<div class="wp-block-column" style="flex-basis:50%%">

				</div>
				<!-- /wp:column -->

				</div>
				<!-- /wp:columns -->

			</div>
			<!-- /wp:group -->',
			esc_html__( 'More resources', 'wporg-forums' ),
			esc_html__( 'Documentation', 'wporg-forums' ),
			esc_html__( 'Find the information you need to get the most out of WordPress.', 'wporg-forums' ),
			esc_html__( 'Support Handbook', 'wporg-forums' ),
			esc_html__( 'Learn how to get involved and provide support in the forums.', 'wporg-forums' ),
		)
	); ?>

<?php
get_footer();
