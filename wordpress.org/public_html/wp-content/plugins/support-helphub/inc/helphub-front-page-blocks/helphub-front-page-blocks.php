<?php
/**
 * Plugin Name: Helphub Front Page Blocks
 * Plugin URI: https://www.wordpress.org
 * Description: Create linkable blocks on the front page of support pages.
 *
 * @package HelpHub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once( dirname( __FILE__ ) . '/includes/class-support-helphub-front-page-blocks-widget.php' );

function helphub_register_front_page_blocks_widget() {
	register_widget( 'Support_HelpHub_Front_Page_Blocks_Widget' );
}
add_action( 'widgets_init', 'helphub_register_front_page_blocks_widget' );
