<?php
/**
 * Template part for displaying the Plugin Sidebar.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

$widget_args = array(
	'before_title' => '<h3 class="widget-title">',
	'after_title'  => '</h3>',
);

the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Meta',    array(), $widget_args );
the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Ratings', array(), $widget_args );
the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Support', array(), $widget_args );
the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Donate',  array(), $widget_args );
