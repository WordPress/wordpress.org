<?php
/**
 * The sidebars containing the widget areas for the "day of" template
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

if ( ! is_active_sidebar( 'sidebar-day-of-1' ) && ! is_active_sidebar( 'sidebar-day-of-2' ) ) {
	return;
}

?>

<aside id="secondary" class="widget-area" role="complementary">
	<?php if ( is_active_sidebar( 'sidebar-day-of-1' ) ) : ?>
		<div id="primary-sidebar">
			<?php dynamic_sidebar( 'sidebar-day-of-1' ); ?>
		</div>
	<?php endif; ?>

	<?php if ( is_active_sidebar( 'sidebar-day-of-2' ) ) : ?>
		<div id="secondary-sidebar">
			<?php dynamic_sidebar( 'sidebar-day-of-2' ); ?>
		</div>
	<?php endif; ?>
</aside>
