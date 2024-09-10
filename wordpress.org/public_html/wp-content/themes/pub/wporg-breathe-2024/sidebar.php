<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package p2-breathe
 */

if( ! is_active_sidebar( 'sidebar-1' ) )
	return;
?>
	<div id="primary-modal"></div>
	<div id="secondary" class="widget-area" role="complementary">
		<a href="#" id="secondary-toggle"><button aria-label="Close menu" class="wp-block-navigation__responsive-container-close"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M13 11.8l6.1-6.3-1-1-6.1 6.2-6.1-6.2-1 1 6.1 6.3-6.5 6.7 1 1 6.5-6.6 6.5 6.6 1-1z"></path></svg></button></a>
		<div id="secondary-content">
			<?php do_action( 'before_sidebar' ); ?>
			<?php dynamic_sidebar( 'sidebar-1' ); ?>
		</div>
	</div><!-- #secondary -->
