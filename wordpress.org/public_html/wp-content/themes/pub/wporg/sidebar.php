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
	<div class="widget">
		<h4><?php esc_html_e( 'Categories', 'wporg' ); ?></h4>
		<ul>
			<?php wp_list_categories( 'title_li=&show_count=1&orderby=count&order=DESC&number=10' ); ?>
		</ul>
	</div>

	<div class="widget">
		<h4><?php esc_html_e( 'Blog Archives', 'wporg' ); ?></h4>
		<ul>
			<?php wp_get_archives( 'type=monthly&limit=12' ); ?>
		</ul>
	</div>
</aside><!-- #secondary -->
