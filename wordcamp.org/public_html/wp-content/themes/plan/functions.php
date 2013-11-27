<?php

/**
 * Register sidebars
 */
function plan_widgets_init() {
	register_sidebar( array(
		'name' => __( 'After Header', 'plan' ),
		'id' => 'sidebar-4',
		'description' => __( 'Appears below the header', 'plan' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => '</aside>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );

	register_sidebar( array(
		'name' => __( 'Footer', 'plan' ),
		'id' => 'sidebar-5',
		'description' => __( 'Appears at the top of the footer', 'plan' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget' => '</aside>',
		'before_title' => '<h3 class="widget-title">',
		'after_title' => '</h3>',
	) );
}
add_action( 'widgets_init', 'plan_widgets_init' );