<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package wpmobileapps
 */

if ( ! is_active_sidebar( 'sidebar-1' ) || ! is_active_sidebar( 'sidebar-2' ) ) {
	return;
}
?>

<div id="secondary" class="widget-area" role="complementary">
	<div class="footer-widgets">
		<div class="footer-widget first">
			<?php dynamic_sidebar( 'sidebar-1' ); ?>
		</div>
		<div class="footer-widget second">
			<?php dynamic_sidebar( 'sidebar-2' ); ?>
		</div>
	</div>
</div><!-- #secondary -->
