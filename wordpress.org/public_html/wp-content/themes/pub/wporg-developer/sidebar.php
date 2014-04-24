<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package wporg-developer
 */
?>
	<div id="secondary" class="widget-area sidebar section" role="complementary">
		<?php do_action( 'before_sidebar' ); ?>
		<?php if ( ! dynamic_sidebar( 'sidebar-1' ) ) : ?>

			<aside id="search" class="box gray widget widget_search">
				<?php get_search_form(); ?>
			</aside>

			<aside id="archives" class="box gray widget">
				<h1 class="widget-title"><?php _e( 'Archives', 'wporg' ); ?></h1>
				<ul>
					<?php wp_get_archives( array( 'type' => 'monthly' ) ); ?>
				</ul>
			</aside>

			<aside id="meta" class="box gray widget">
				<h1 class="widget-title"><?php _e( 'Meta', 'wporg' ); ?></h1>
				<ul>
					<?php wp_register(); ?>
					<li><?php wp_loginout(); ?></li>
					<?php wp_meta(); ?>
				</ul>
			</aside>

		<?php endif; // end sidebar widget area ?>
	</div><!-- #secondary -->
