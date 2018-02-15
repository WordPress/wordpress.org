<?php
/**
 * The sidebar containing the main widget area
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

?>

<aside id="secondary" class="widget-area col-3">
	<?php
	if ( ! dynamic_sidebar( 'sidebar-1' ) ) :
		$widget_args = [
			'before_title' => '<h4>',
			'after_title'  => '</h4>',
		];

		the_widget( 'WP_Widget_Categories', [
			'title' => esc_html__( 'Categories', 'wporg' ),
			'count' => true,
		], $widget_args );

		the_widget( 'WP_Widget_Archives', [
			'title' => esc_html__( 'Blog Archives', 'wporg' ),
		], $widget_args );

		if ( class_exists( 'Jetpack_Subscriptions_Widget' ) ) :
			the_widget( 'Jetpack_Subscriptions_Widget', array_merge( \Jetpack_Subscriptions_Widget::defaults(), [
				'title'          => esc_html__( 'Subscribe to this blog', 'wporg' ),
				'subscribe_text' => '',
			] ), $widget_args );
		endif;
	endif;
	?>
</aside><!-- #secondary -->
