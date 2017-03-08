<?php
/**
 * Template part for displaying the Plugin Admin sidebar..
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

$widget_args = array(
	'before_title' => '<h4 class="widget-title">',
	'after_title'  => '</h4>',
);

the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Meta',            array(), $widget_args );
the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Committers',      array(), $widget_args );
the_widget( 'WordPressdotorg\Plugin_Directory\Widgets\Contributors',    array(), array(
	'before_title' => '<h4 class="widget-title">',
	'after_title'  => '</h4>',
	'before_widget' => '<div id="plugin-contributors" class="widget plugin-contributors read-more" aria-expanded="false">',
	'after_widget' => sprintf( '</div><button type="button" class="button-link section-toggle" aria-controls="plugin-contributors">%s</button>', __( 'View more', 'wporg-plugins' ) ),
) );
