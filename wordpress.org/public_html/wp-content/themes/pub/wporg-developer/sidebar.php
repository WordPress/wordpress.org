<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package wporg-developer
 */
?>
<?php if ( is_active_sidebar( get_post_type() ) ) : ?>
	<div id="sidebar" class="widget-area sidebar section" role="complementary">
		<?php do_action( 'before_sidebar' ); ?>
		<ul>
			<?php if ( ! dynamic_sidebar( get_post_type() ) ) : ?>
			<?php endif; // end sidebar widget area ?>
		</ul>
	</div><!-- #secondary -->
<?php endif; ?>
