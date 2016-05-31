<?php

require_once TEMPLATEPATH . "/lib/utils/twentyten-overrides.php";
require_once TEMPLATEPATH . "/lib/utils/twentyten-functions.php";
require_once TEMPLATEPATH . "/lib/utils/class-wcb-loader.php";
require_once TEMPLATEPATH . "/lib/utils/class-wcb-post-meta-manager.php";
if ( is_admin() ) {
        require_once TEMPLATEPATH . "/lib/utils/class-wcb-registry.php";
        require_once TEMPLATEPATH . "/lib/utils/class-wcb-metabox.php";
        require_once TEMPLATEPATH . "/lib/utils/class-wcb-post-metabox.php";
}
require_once TEMPLATEPATH . "/lib/utils/header.php";

function wcb_maybe_define( $constant, $value, $filter='' ) {
	if ( defined( $constant ) )
		return;

	if ( !empty( $filter ) )
		$value = apply_filters( $filter, $value );

	define( $constant, $value );
}

function wcb_dev_url( $url, $force=false ) {
	if ( $force || ( defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ) )
		return preg_replace( '#(\.js|\.css)$#', '.dev${1}', $url );
	else
		return $url;
}

function wcb_admin_enqueue_scripts( $screen_id ) {
	do_action( "wcb_enqueue_scripts_$screen_id" );
}
add_action( 'admin_enqueue_scripts', 'wcb_admin_enqueue_scripts' );


function wcb_menu_icon( $post_type, $icon_url ) {
	global $wcb_menu_icons;
	if ( !isset( $wcb_menu_icons ) )
		$wcb_menu_icons = array();

	$wcb_menu_icons[ $post_type ] = $icon_url;
	return '';
}

function __wcb_render_menu_icons() {
	global $wcb_menu_icons;

	if ( !isset( $wcb_menu_icons ) )
		return;

	?><style type="text/css"><?php
	foreach ( $wcb_menu_icons as $post_type => $icon_url ):
		$class    = sanitize_html_class( $post_type );
		$icon_url = esc_url( $icon_url ); ?>
			#menu-posts-<?php echo $class; ?> .wp-menu-image {
				background: url(<?php echo $icon_url; ?>) no-repeat 0 -32px;
			}
			#menu-posts-<?php echo $class; ?>:hover .wp-menu-image,
			#menu-posts-<?php echo $class; ?>.wp-has-current-submenu .wp-menu-image {
				background: url(<?php echo $icon_url; ?>) no-repeat 0 0;
			}
		<?php
	endforeach;
	?></style><?php
}
add_action( 'admin_head', '__wcb_render_menu_icons' );

/**
 * Splits the query into two columns based upon content length.
 *
 * @param WP_Query $query
 * @param integer $post_cost A character cost attributed to rendering a post. Helps for approximations.
 * @param integer $min_chars The minimum number of characters per post. Helps for approximations.
 * @return Object The starting post ID of the second column.
 */
function wcb_optimal_column_split( $query, $post_cost=0, $min_chars=0 ) {
	$query->rewind_posts();

	$total  = 0;
	$totals = array();

	while ( $query->have_posts() ) {
		$post     = $query->next_post();
		$length   = strlen( $post->post_content );
		$total   += ( $length < $min_chars) ? $min_chars : $length;
		$total   += $post_cost;
		$totals[] = array( $total, $post->ID );
	}

	$optimum = $total / 2;

	foreach ( $totals as $arr ) {
		list( $current, $post_id ) = $arr;

		// When the total starts increasing, we've found the beginning of the new column.
		if ( isset( $last ) && abs( $optimum - $last ) < abs( $optimum - $current ) ) {
			return $post_id;
		}

		$last = $current;
	}
}

/**
 * Prevents ShareDaddy from displaying sharing after the post content.
 */

function wcb_suppress_sharing() {
	remove_filter( 'the_content', 'sharing_display', 19 );
	remove_filter( 'the_excerpt', 'sharing_display', 19 );
}

/**
 * Rewinds the loop and prints ShareDaddy output for the first post.
 */

function wcb_print_sharing() {
	rewind_posts();
	if ( have_posts() ) {
		the_post();
		if ( function_exists( 'sharing_display' ) )
			echo sharing_display();
	}
}

?>