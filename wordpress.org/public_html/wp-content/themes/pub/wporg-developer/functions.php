<?php

namespace DevHub;

/**
 * Custom template tags for this theme.
 */
require __DIR__ . '/inc/template-tags.php';

/**
 * Custom functions that act independently of the theme templates.
 */
require __DIR__ . '/inc/extras.php';

/**
 * Customizer additions.
 */
require __DIR__ . '/inc/customizer.php';

/**
 * Load Jetpack compatibility file.
 */
require __DIR__ . '/inc/jetpack.php';

if ( ! function_exists( 'loop_pagination' ) ) {
	require __DIR__ . '/inc/loop-pagination.php';
}

if ( ! function_exists( 'breadcrumb_trail' ) ) {
	require __DIR__ . '/inc/breadcrumb-trail.php';
}

/**
 * Set the content width based on the theme's design and stylesheet.
 */
if ( ! isset( $content_width ) ) {
	$content_width = 640; /* pixels */
}


add_action( 'init', __NAMESPACE__ . '\\init' );
add_filter( 'handbook_post_types', __NAMESPACE__ . '\\filter_handbook_post_types' );

function init() {

	register_post_types();
	register_taxonomies();
	add_action( 'widgets_init', __NAMESPACE__ . '\\widgets_init' );
	add_action( 'pre_get_posts', __NAMESPACE__ . '\\pre_get_posts' );
	add_action( 'template_redirect', __NAMESPACE__ . '\\redirect_single_search_match' );
	add_action( 'template_redirect', __NAMESPACE__ . '\\redirect_handbook' );
	add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\theme_scripts_styles' );
	add_filter( 'post_type_link', __NAMESPACE__ . '\\method_permalink', 10, 2 );
	add_filter( 'term_link', __NAMESPACE__ . '\\taxonomy_permalink', 10, 3 );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );

	add_filter( 'the_excerpt', __NAMESPACE__ . '\\lowercase_P_dangit_just_once' );
	add_filter( 'the_content', __NAMESPACE__ . '\\make_doclink_clickable', 10, 5 );
	add_filter( 'comments_open', __NAMESPACE__ . '\\can_user_post_example', 10, 2 );

	// Temporarily disable comments
	//add_filter( 'comments_open', '__return_false' );

	add_filter( 'breadcrumb_trail_items',  __NAMESPACE__ . '\\breadcrumb_trail_items', 10, 2 );
	add_filter( 'breadcrumb_trail_items',  __NAMESPACE__ . '\\breadcrumb_trail_remove_handbook', 10, 2 );

	treat_comments_as_examples();
}

/**
 * Fix breadcrumb for hooks.
 *
 * A hook has a parent (the function containing it), which causes the Breadcrumb
 * Trail plugin to introduce trail items related to the parent that shouldn't
 * be shown.
 *
 * @param  array $items The breadcrumb trail items
 * @param  array $args  Original arg
 * @return array
 */
function breadcrumb_trail_items( $items, $args ) {
	$post_type = 'wp-parser-hook';

	// Bail early when not the single archive for hook
	if ( ! is_singular() || $post_type !== get_post_type() || ! isset( $items[4] ) ) {
		return $items;
	}

	$post_type_object = get_post_type_object( $post_type );

	// Replaces 'Functions' archive link with 'Hooks' archive link
	$items[2] = '<a href="' . get_post_type_archive_link( $post_type ) . '">' . $post_type_object->labels->name . '</a>';
	// Replace what the plugin thinks is the parent with the hook name
	$items[3] = $items[4];
	// Unset the last element since it shifted up in trail hierarchy
	unset( $items[4] );

	return $items;
}

/**
 * Removes the 'Handbook' segment of the breakcrumb, when present.
 *
 * There is no handbook page or listing at present.
 *
 * @param  array $items The breadcrumb trail items
 * @param  array $args  Original arg
 * @return array
 */
function breadcrumb_trail_remove_handbook( $items, $args ) {
	if ( false !== strpos( $items[1], '>Handbook</a>' ) ) {
		array_splice( $items, 1, 1 );
	}

	return $items;
}

/**
* handbook post_type filter function
*/
function filter_handbook_post_types( $types ) {
	return array( 'theme', 'plugin' );
}

/**
 * widgets_init function.
 *
 * @access public
 * @return void
 */
function widgets_init() {
	register_sidebar( array(
		'name'          => __( 'Sidebar', 'wporg' ),
		'id'            => 'sidebar-1',
		'before_widget' => '<aside id="%1$s" class="box gray widget %2$s">',
		'after_widget'  => '</div></aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1><div class="widget-content">',
	) );
}

/**
 * @param \WP_Query $query
 */
function pre_get_posts( $query ) {

	if ( $query->is_main_query() && ( $query->is_post_type_archive() || $query->is_search() ) ) {
		$query->set( 'orderby', 'title' );
		$query->set( 'order', 'ASC' );
	}

	if ( $query->is_main_query() && $query->is_tax() && $query->get( 'wp-parser-source-file' ) ) {
		$query->set( 'wp-parser-source-file', str_replace( array( '.php', '/' ), array( '-php', '_' ), $query->query['wp-parser-source-file'] ) );
	}
}

/**
 * Register the function and class post types
 */
function register_post_types() {
	$supports = array(
		'comments',
		'custom-fields',
		'editor',
		'excerpt',
		'revisions',
		'title',
	);

	// Functions
	register_post_type( 'wp-parser-function', array(
		'has_archive' => 'reference/functions',
		'label'       => __( 'Functions', 'wporg' ),
		'labels'      => array(
			'name'               => __( 'Functions', 'wporg' ),
			'singular_name'      => __( 'Function', 'wporg' ),
			'all_items'          => __( 'Functions', 'wporg' ),
			'new_item'           => __( 'New Function', 'wporg' ),
			'add_new'            => __( 'Add New', 'wporg' ),
			'add_new_item'       => __( 'Add New Function', 'wporg' ),
			'edit_item'          => __( 'Edit Function', 'wporg' ),
			'view_item'          => __( 'View Function', 'wporg' ),
			'search_items'       => __( 'Search Functions', 'wporg' ),
			'not_found'          => __( 'No Functions found', 'wporg' ),
			'not_found_in_trash' => __( 'No Functions found in trash', 'wporg' ),
			'parent_item_colon'  => __( 'Parent Function', 'wporg' ),
			'menu_name'          => __( 'Functions', 'wporg' ),
		),
		'public'      => true,
		'rewrite'     => array(
			'feeds'      => false,
			'slug'       => 'reference/functions',
			'with_front' => false,
		),
		'supports'    => $supports,
	) );

	// Methods
	add_rewrite_rule( 'reference/classes/page/([0-9]{1,})/?$', 'index.php?post_type=wp-parser-class&paged=$matches[1]', 'top' );
	add_rewrite_rule( 'reference/classes/([^/]+)/([^/]+)/?$', 'index.php?post_type=wp-parser-method&name=$matches[1]-$matches[2]', 'top' );

	// Classes
	register_post_type( 'wp-parser-class', array(
		'has_archive' => 'reference/classes',
		'label'       => __( 'Classes', 'wporg' ),
		'labels'      => array(
			'name'               => __( 'Classes', 'wporg' ),
			'singular_name'      => __( 'Class', 'wporg' ),
			'all_items'          => __( 'Classes', 'wporg' ),
			'new_item'           => __( 'New Class', 'wporg' ),
			'add_new'            => __( 'Add New', 'wporg' ),
			'add_new_item'       => __( 'Add New Class', 'wporg' ),
			'edit_item'          => __( 'Edit Class', 'wporg' ),
			'view_item'          => __( 'View Class', 'wporg' ),
			'search_items'       => __( 'Search Classes', 'wporg' ),
			'not_found'          => __( 'No Classes found', 'wporg' ),
			'not_found_in_trash' => __( 'No Classes found in trash', 'wporg' ),
			'parent_item_colon'  => __( 'Parent Class', 'wporg' ),
			'menu_name'          => __( 'Classes', 'wporg' ),
		),
		'public'      => true,
		'rewrite'     => array(
			'feeds'      => false,
			'slug'       => 'reference/classes',
			'with_front' => false,
		),
		'supports'    => $supports,
	) );

	// Hooks
	register_post_type( 'wp-parser-hook', array(
		'has_archive' => 'reference/hooks',
		'label'       => __( 'Hooks', 'wporg' ),
		'labels'      => array(
			'name'               => __( 'Hooks', 'wporg' ),
			'singular_name'      => __( 'Hook', 'wporg' ),
			'all_items'          => __( 'Hooks', 'wporg' ),
			'new_item'           => __( 'New Hook', 'wporg' ),
			'add_new'            => __( 'Add New', 'wporg' ),
			'add_new_item'       => __( 'Add New Hook', 'wporg' ),
			'edit_item'          => __( 'Edit Hook', 'wporg' ),
			'view_item'          => __( 'View Hook', 'wporg' ),
			'search_items'       => __( 'Search Hooks', 'wporg' ),
			'not_found'          => __( 'No Hooks found', 'wporg' ),
			'not_found_in_trash' => __( 'No Hooks found in trash', 'wporg' ),
			'parent_item_colon'  => __( 'Parent Hook', 'wporg' ),
			'menu_name'          => __( 'Hooks', 'wporg' ),
		),
		'public'      => true,
		'rewrite'     => array(
			'feeds'      => false,
			'slug'       => 'reference/hooks',
			'with_front' => false,
		),
		'supports'    => $supports,
	) );

	// Methods
	register_post_type( 'wp-parser-method', array(
		'has_archive' => 'reference/methods',
		'label'       => __( 'Methods', 'wporg' ),
		'labels'      => array(
			'name'               => __( 'Methods', 'wporg' ),
			'singular_name'      => __( 'Method', 'wporg' ),
			'all_items'          => __( 'Methods', 'wporg' ),
			'new_item'           => __( 'New Method', 'wporg' ),
			'add_new'            => __( 'Add New', 'wporg' ),
			'add_new_item'       => __( 'Add New Method', 'wporg' ),
			'edit_item'          => __( 'Edit Method', 'wporg' ),
			'view_item'          => __( 'View Method', 'wporg' ),
			'search_items'       => __( 'Search Methods', 'wporg' ),
			'not_found'          => __( 'No Methods found', 'wporg' ),
			'not_found_in_trash' => __( 'No Methods found in trash', 'wporg' ),
			'parent_item_colon'  => __( 'Parent Method', 'wporg' ),
			'menu_name'          => __( 'Methods', 'wporg' ),
		),
		'public'      => true,
		'rewrite'     => array(
			'feeds'      => false,
			'slug'       => 'classes',
			'with_front' => false,
		),
		'supports'    => $supports,
	) );
}

/**
 * Register the file and @since taxonomies
 */
function register_taxonomies() {
	// Files
	register_taxonomy( 'wp-parser-source-file', array( 'wp-parser-class', 'wp-parser-function', 'wp-parser-hook', 'wp-parser-method' ), array(
		'label'                 => __( 'Files', 'wporg' ),
		'labels'                => array(
			'name'                       => __( 'Files', 'wporg' ),
			'singular_name'              => _x( 'File', 'taxonomy general name', 'wporg' ),
			'search_items'               => __( 'Search Files', 'wporg' ),
			'popular_items'              => null,
			'all_items'                  => __( 'All Files', 'wporg' ),
			'parent_item'                => __( 'Parent File', 'wporg' ),
			'parent_item_colon'          => __( 'Parent File:', 'wporg' ),
			'edit_item'                  => __( 'Edit File', 'wporg' ),
			'update_item'                => __( 'Update File', 'wporg' ),
			'add_new_item'               => __( 'New File', 'wporg' ),
			'new_item_name'              => __( 'New File', 'wporg' ),
			'separate_items_with_commas' => __( 'Files separated by comma', 'wporg' ),
			'add_or_remove_items'        => __( 'Add or remove Files', 'wporg' ),
			'choose_from_most_used'      => __( 'Choose from the most used Files', 'wporg' ),
			'menu_name'                  => __( 'Files', 'wporg' ),
		),
		'public'                => true,
		// Hierarchical x 2 to enable (.+) rather than ([^/]+) for rewrites.
		'hierarchical'          => true,
		'rewrite'               => array( 'slug' => 'reference/files', 'hierarchical' => true ),
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );

	// Package
	register_taxonomy( 'wp-parser-package', array( 'wp-parser-class', 'wp-parser-function', 'wp-parser-hook', 'wp-parser-method' ), array(
		'hierarchical'          => true,
		'label'                 => '@package',
		'public'                => true,
		'rewrite'               => array( 'slug' => 'reference/package' ),
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );

	// @since
	register_taxonomy( 'wp-parser-since', array( 'wp-parser-class', 'wp-parser-function', 'wp-parser-hook', 'wp-parser-method' ), array(
		'hierarchical'          => true,
		'label'                 => __( '@since', 'wporg' ),
		'public'                => true,
		'rewrite'               => array( 'slug' => 'reference/since' ),
		'sort'                  => false,
		'update_count_callback' => '_update_post_term_count',
	) );
}

function method_permalink( $link, $post ) {
	if ( $post->post_type !== 'wp-parser-method' )
		return $link;

	list( $class, $method ) = explode( '-', $post->post_name );
	$link = home_url( user_trailingslashit( "reference/classes/$class/$method" ) );
	return $link;
}

function taxonomy_permalink( $link, $term, $taxonomy ) {
	if ( $taxonomy === 'wp-parser-source-file' ) {
		$slug = $term->slug;
		if ( substr( $slug, -4 ) === '-php' ) {
			$slug = substr( $slug, 0, -4 ) . '.php';
			$slug = str_replace( '_', '/', $slug );
		}
		$link = home_url( user_trailingslashit( "reference/files/$slug" ) );
	} elseif ( $taxonomy === 'wp-parser-since' ) {
		$link = str_replace( $term->slug, str_replace( '-', '.', $term->slug ), $link );
	}
	return $link;
}

function theme_scripts_styles() {
	wp_enqueue_style( 'dashicons' );
	wp_enqueue_style( 'open-sans', '//fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,400,300,600' );
	wp_enqueue_style( 'wporg-developer-style', get_stylesheet_uri(), array(), '2' );
	wp_enqueue_style( 'wp-dev-sass-compiled', get_template_directory_uri() . '/stylesheets/main.css', array( 'wporg-developer-style' ), '20140709-1' );
	wp_enqueue_script( 'wporg-developer-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '20120206', true );
	wp_enqueue_script( 'wporg-developer-skip-link-focus-fix', get_template_directory_uri() . '/js/skip-link-focus-fix.js', array(), '20130115', true );

	if ( is_singular() && ( '0' != get_comments_number() || post_type_has_source_code() ) ) {
		wp_enqueue_script( 'wporg-developer-function-reference', get_template_directory_uri() . '/js/function-reference.js', array( 'jquery', 'syntaxhighlighter-core', 'syntaxhighlighter-brush-php' ), '20140515', true );
		wp_enqueue_style( 'syntaxhighlighter-core' );
		wp_enqueue_style( 'syntaxhighlighter-theme-default' );

		wp_enqueue_script( 'wporg-developer-code-examples', get_template_directory_uri() . '/js/code-example.js', array(), '20140423', true );
		if ( get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}
	}
}

/**
 * Handles adding/removing hooks to enable comments as examples.
 *
 * Mostly gives users greater permissions in terms of comment content.
 *
 * In order to submit code examples, users must be able to post with less restrictions.
 */
function treat_comments_as_examples() {
	// Restricts commenting to logged in users.
	add_filter( 'comments_open', __NAMESPACE__ . '\\prevent_invalid_comment_submissions', 10, 2 );

	if ( ! current_user_can( 'unfiltered_html' ) ) {
		remove_filter( 'pre_comment_content', 'wp_filter_kses'      );
		add_filter(    'pre_comment_content', 'wp_filter_post_kses' );
	}

	// Force comment registration to be true
	add_filter( 'pre_option_comment_registration', '__return_true' );

	// Force comment moderation to be true
	add_filter( 'pre_option_comment_moderation',   '__return_true' );

	// Remove reply to link
	add_filter( 'comment_reply_link',              '__return_empty_string' );

/*	foreach ( array( 'comment_save_pre', 'pre_comment_content' ) as $filter ) {
		add_filter( $filter, 'balanceTags', 50 );
	}*/

	remove_filter( 'comment_text',        'capital_P_dangit',   31 );

	remove_filter( 'comment_text',        'wptexturize'            );
	remove_filter( 'comment_text',        'convert_chars'          );
	remove_filter( 'comment_text',        'make_clickable',      9 );
	remove_filter( 'comment_text',        'force_balance_tags', 25 );
	remove_filter( 'comment_text',        'convert_smilies',    20 );
	remove_filter( 'comment_text',        'wpautop',            30 );

	remove_filter( 'pre_comment_content', 'wp_rel_nofollow',    15 );

	// Be more permissive with content of examples.
	// Note: the content gets fully escaped via 'get_comment_text'.
	if ( post_type_supports_examples() ) {
		if ( current_user_can( 'unfiltered_html' ) ) {
			remove_filter( 'pre_comment_content', 'wp_filter_post_kses' );
		} else {
			remove_filter( 'pre_comment_content', 'wp_filter_kses' );
		}
	}

	add_filter( 'get_comment_text',  __NAMESPACE__ . '\\escape_example_content' );
}

/**
 * Escapes the entirety of the content for examples.
 *
 * @param  string $text The comment/example content.
 * @return string
 */
function escape_example_content( $text ) {
	// Only proceed if the post type is one that has examples.
	if ( ! post_type_supports_examples() ) {
		return $text;
	}

	return htmlentities( $text );
}

/**
 * Disables commenting to invalid or non-users.
 *
 * @param bool  $status Default commenting status for post.
 * @return bool False if commenter isn't a user, otherwise the passed in status.
 */
function prevent_invalid_comment_submissions( $status, $post_id ) {
	if ( $_POST && ( ! is_user_logged_in() || ! is_user_member_of_blog() ) ) {
		return false;
	}

	return $status;
}

/**
 * Allows for "Wordpress" just for the excerpt value of the capital_P_dangit function.
 *
 * WP.org has a global output buffer that runs capital_P_dangit() over displayed
 * content. For this one field of this one post, circumvent that function to
 * to show the lowercase P.
 *
 * @param  string $excerpt The post excerpt.
 * @return string
 */
function lowercase_P_dangit_just_once( $excerpt ) {
	if ( 'wp-parser-function' == get_post_type() && 'capital_P_dangit' == get_the_title() ) {
		$excerpt = str_replace( 'Wordpress', 'Word&#112;ress', $excerpt );
	}

	return $excerpt;
}

/**
 * Redirects a search query with only one result directly to that result.
 */
function redirect_single_search_match() {
	if ( is_search() && 1 == $GLOBALS['wp_query']->found_posts ) {
		wp_redirect( get_permalink( get_post() ) );
		exit();
	}
}

/**
 * Redirects a naked handbook request to home.
 */
function redirect_handbook() {
	if ( 'handbook' == get_query_var( 'name' ) && ! get_query_var( 'post_type ' ) ) {
		wp_redirect( home_url() );
		exit();
	}
}

/**
 * Makes phpDoc @link references clickable.
 *
 * Handles these five different types of links:
 *
 * - {@link http://en.wikipedia.org/wiki/ISO_8601}
 * - {@link WP_Rewrite::$index}
 * - {@link WP_Query::query()}
 * - {@link esc_attr()}
 * - {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}
 *
 * @param  string $content The content.
 * @return string
 */
function make_doclink_clickable( $content ) {

	if ( false === strpos( $content, '{@link ' ) ) {
		return $content;
	}

	return preg_replace_callback(
		'/\{@link ([^\}]+)\}/',
		function ( $matches ) {

			$link = $matches[1];

			// Fix URLs made clickable during initial parsing
			if ( 0 === strpos( $link, '<a ' ) ) {

				if ( preg_match( '/^<a .*href=[\'\"]([^\'\"]+)[\'\"]>(.*)<\/a>$/', $link, $parts ) ) {
					$link = '<a href="' . $parts[1] . '">' . esc_html( trim( $parts[2] ) ) . '</a>';
				}

			}

			// Link to an external resource.
			elseif ( 0 === strpos( $link, 'http' ) ) {

				$parts = explode( ' ', $link, 2 );

				// Link without linked text: {@link http://en.wikipedia.org/wiki/ISO_8601}
				if ( 1 === count( $parts ) ) {
					$link = '<a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a>';
				}

				// Link with linked text: {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}
				else {
					$link = '<a href="' . esc_url( $parts[0] ) . '">' . esc_html( $parts[1] ) . '</a>';
				}

			}

			// Link to an internal resource.
			else {

				// Link to class variable: {@link WP_Rewrite::$index}
				if ( false !== strpos( $link, '::$' ) ) {
					// Nothing to link to currently.
				}

				// Link to class method: {@link WP_Query::query()}
				elseif ( false !== strpos( $link, '::' ) ) {
					$link = '<a href="' .
						get_post_type_archive_link( 'wp-parser-class' ) .
						str_replace( array( '::', '()' ), array( '/', '' ), $link ) .
						'">' . esc_html( $link ) . '</a>';
				}

				// Link to function: {@link esc_attr()}
				else {
					$link = '<a href="' .
						get_post_type_archive_link( 'wp-parser-function' ) .
						str_replace( '()', '', $link ) .
						'">' . esc_html( $link ) . '</a>';
				}

			}

			return $link;
		},
		$content
	);
}
