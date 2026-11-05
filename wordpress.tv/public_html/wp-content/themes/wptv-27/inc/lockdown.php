<?php
/**
 * Design lockdown: the theme files are the only source of truth for the design.
 *
 * See docs/decisions.md ("The design lives in code"). Nobody, administrators
 * included, can change templates, template parts or global styles from the
 * admin. Post and page content stays editable.
 *
 * @package WordPressTV
 */

namespace WordPressTV\Lockdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that store template changes made in the Site Editor.
 *
 * Global styles are not hidden: core creates a new user global styles post
 * whenever its lookup finds none, so hiding them added one post per editor
 * load. empty_user_theme_json() already ignores their content.
 */
const DESIGN_POST_TYPES = array( 'wp_template', 'wp_template_part' );

/**
 * Hides database copies of templates and template parts.
 *
 * Template resolution and the template-part block look these post types up
 * through WP_Query, so returning no posts makes WordPress fall back to the
 * theme files. Existing copies stay in the database untouched, but have no
 * effect.
 *
 * @param \WP_Post[]|int[]|null $posts Short-circuit value.
 * @param \WP_Query             $query The query.
 * @return \WP_Post[]|int[]|null
 */
function hide_design_posts( $posts, $query ) {
	$post_types = (array) $query->get( 'post_type' );
	if ( $post_types && ! array_diff( $post_types, DESIGN_POST_TYPES ) ) {
		$query->found_posts   = 0;
		$query->max_num_pages = 0;
		return array();
	}
	return $posts;
}
add_filter( 'posts_pre_query', __NAMESPACE__ . '\hide_design_posts', 10, 2 );

/**
 * Ignores user global styles, including their custom CSS.
 *
 * @return \WP_Theme_JSON_Data
 */
function empty_user_theme_json() {
	return new \WP_Theme_JSON_Data( array( 'version' => \WP_Theme_JSON::LATEST_SCHEMA ), 'custom' );
}
add_filter( 'wp_theme_json_data_user', __NAMESPACE__ . '\empty_user_theme_json' );

// Additional CSS from the Customizer.
add_filter( 'wp_get_custom_css', '__return_empty_string' );

/**
 * Rejects REST writes to templates, template parts and global styles.
 *
 * @param mixed            $result  Response to replace the requested one.
 * @param \WP_REST_Server  $server  Server instance.
 * @param \WP_REST_Request $request Request.
 * @return mixed
 */
function block_design_writes( $result, $server, $request ) {
	if ( 'GET' === $request->get_method() ) {
		return $result;
	}
	if ( preg_match( '#^/wp/v2/(templates|template-parts|global-styles)(/|$)#i', $request->get_route() ) ) {
		return new \WP_Error(
			'wptv_design_locked',
			__( 'The design is managed in the theme code and cannot be changed here.', 'wptv' ),
			array( 'status' => 403 )
		);
	}
	return $result;
}
add_filter( 'rest_pre_dispatch', __NAMESPACE__ . '\block_design_writes', 10, 3 );

/**
 * Blocks the Site Editor screen.
 */
function block_site_editor() {
	wp_die(
		esc_html__( 'The design is managed in the theme code and cannot be changed here.', 'wptv' ),
		'',
		array(
			'response'  => 403,
			'back_link' => true,
		)
	);
}
add_action( 'load-site-editor.php', __NAMESPACE__ . '\block_site_editor' );

/**
 * Removes the Site Editor links from the admin menu and the toolbar.
 */
function remove_site_editor_menu() {
	remove_submenu_page( 'themes.php', 'site-editor.php' );
}
add_action( 'admin_menu', __NAMESPACE__ . '\remove_site_editor_menu', 999 );

/**
 * Removes the "Edit site" toolbar link.
 *
 * @param \WP_Admin_Bar $admin_bar Toolbar.
 */
function remove_toolbar_link( $admin_bar ) {
	$admin_bar->remove_node( 'site-editor' );
}
add_action( 'admin_bar_menu', __NAMESPACE__ . '\remove_toolbar_link', 999 );

/**
 * Restricts the post editor: no template editing, no block locking, no code editor.
 *
 * @param array $settings Block editor settings.
 * @return array
 */
function restrict_editor( $settings ) {
	$settings['supportsTemplateMode'] = false;
	$settings['canLockBlocks']        = false;
	$settings['codeEditingEnabled']   = false;
	return $settings;
}
add_filter( 'block_editor_settings_all', __NAMESPACE__ . '\restrict_editor' );
