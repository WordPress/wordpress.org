<?php
/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Theme
 */

namespace WordPressdotorg\Theme;

?>

<aside id="secondary" class="widget-area col-3">
	<?php
	if ( is_active_sidebar( 'sidebar-1' ) ) :
		dynamic_sidebar( 'sidebar-1' );
	else: ?>
		<h4><?php _e( 'Categories', 'wporg' ); ?></h4>
		<ul>
			<?php wp_list_categories( 'title_li=&show_count=1&orderby=count&order=DESC&number=10' ); ?>
		</ul>

		<h4><?php _e( 'Blog Archives', 'wporg' ); ?></h4>
		<ul>
			<?php wp_get_archives( 'type=monthly&limit=12' ); ?>
		</ul>
	<?php endif; ?>
</aside><!-- #secondary -->
