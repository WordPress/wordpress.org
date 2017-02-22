<?php

namespace WordCamp\RemoteCSS;
use WP_Post;
use WP_Customize_Manager;
use Exception;

defined( 'WPINC' ) or die();

const POST_TYPE             = 'wc_remote_css';
const SAFE_CSS_POST_SLUG    = 'wcrcss_safe_cached_version';
const OPTION_LAST_UPDATE    = 'wcrcss_last_update';
const AJAX_ACTION           = 'wcrcss_webhook';
const SYNCHRONIZE_ACTION    = 'wcrcss_synchronize';
const WEBHOOK_RATE_LIMIT    = 30; // seconds
const OPTION_REMOTE_CSS_URL = 'wcrcss_remote_css_url';
const CSS_HANDLE            = 'wordcamp_remote_css';
const GITHUB_API_HOSTNAME   = 'api.github.com';

add_action( 'init',               __NAMESPACE__ . '\register_post_types'  );
add_action( 'customize_register', __NAMESPACE__ . '\add_discovery_notice' );

/**
 * Register our custom post types
 */
function register_post_types() {
	$labels = array(
		'name'          => __( 'Remote CSS', 'wordcamporg' ),
		'singular_name' => __( 'Remote CSS', 'wordcamporg' ),
	);

	$capabilities = array(
		'delete_posts'           => 'edit_theme_options',
		'delete_post'            => 'edit_theme_options',
		'delete_published_posts' => 'edit_theme_options',
		'delete_private_posts'   => 'edit_theme_options',
		'delete_others_posts'    => 'edit_theme_options',
		'edit_post'              => 'edit_css',
		'edit_posts'             => 'edit_css',
		'edit_others_posts'      => 'edit_css',
		'edit_published_posts'   => 'edit_css',
		'read_post'              => 'read',
		'read_private_posts'     => 'read',
		'publish_posts'          => 'edit_theme_options',
	);

	$params = array(
		'labels'           => $labels,
		'public'           => false,
		'hierarchical'     => false,
		'rewrite'          => false,
		'query_var'        => false,
		'delete_with_user' => false,
		'can_export'       => true,
		'supports'         => array( 'title', 'revisions' ),
		'capabilities'     => $capabilities,
	);

	register_post_type( POST_TYPE, $params );
}

/**
 * Determines if the site's owner has configured the plugin
 *
 * @return bool
 */
function is_configured() {
	return ! empty( get_option( OPTION_REMOTE_CSS_URL ) );
}

/**
 * Get the WP_Post where the sanitized CSS is stored
 *
 * If it doesn't exist, it will be created.
 *
 * @todo All of the code related to the old Jetpack posts can be removed once all sites have migrated.
 *       That only happens once something triggers `admin_init`, though.
 *
 * @return WP_Post
 */
function get_safe_css_post() {
	$posts = get_posts( array(
		'post_type'      => POST_TYPE,
		'post_status'    => 'private',
		'posts_per_page' => 1,
	) );

	if ( isset( $posts[0] ) ) {
		$post = $posts[0];
	} else {
		$jetpack_post = get_jetpack_post();

		if ( $jetpack_post ) {
			$post = migrate_jetpack_post( $jetpack_post );
		} else {
			$post = create_new_post();
		}
	}

	return $post;
}

/**
 * Find Jetpack's safe CSS post
 *
 * Before WordPress 4.7 and Jetpack 4.2.2, Jetpack stored it's Custom CSS in a `safecss` post type, and Remote CSS
 * created it's own `safecss` post to store our sanitized CSS. Any site that hasn't been migrated to the
 * `wc_remote_css` post type yet will still have their CSS stored in a `safecss` post.
 *
 * @return WP_Post|false
 */
function get_jetpack_post() {
	$post = false;

	$safe_css_post = get_posts( array(
		'posts_per_page' => 1,
		'post_type'      => 'safecss',
		'post_status'    => 'private',
		'post_name'      => SAFE_CSS_POST_SLUG,
	) );

	if ( $safe_css_post ) {
		$post = $safe_css_post[0];
	}

	return $post;
}

/**
 * Migrate an old `safecss` post to the new `wc_remote_css` post type
 *
 * @see get_jetpack_post() for some background information
 *
 * We don't want to delete the old post, just in case something goes wrong and we need to restore it. It does need
 * to be disabled, though, because otherwise it could cause problems. For instance, if it's migrated without being
 * disabled, then the user deletes the Remote CSS URL in order to stop using the plugin. In that scenario, the old
 * post would be restored, and there would be no way for the user to stop using the plugin.
 *
 * @param WP_Post $jetpack_post
 *
 * @return WP_Post
 *
 * @throws Exception
 */
function migrate_jetpack_post( $jetpack_post ) {
	$new_post = create_new_post( $jetpack_post->post_content );

	$jetpack_post->post_status = 'draft';
	$jetpack_post->post_name   = SAFE_CSS_POST_SLUG . '_migrated';

	$result = wp_update_post( $jetpack_post, true );

	if ( is_wp_error( $result ) ) {
		throw new Exception( sprintf(
			// translators: %s is an email address
			__( "Could not migrate Jetpack post. Please notify us at %s.", 'wordcamporg' ),
			EMAIL_CENTRAL_SUPPORT
		) );
	}

	return $new_post;
}

/**
 * Create a new Remote CSS post
 *
 * @param string $content
 *
 * @return WP_Post
 *
 * @throws Exception
 */
function create_new_post( $content = '' ) {
	$post = wp_insert_post( array(
		'post_type'    => POST_TYPE,
		'post_name'    => SAFE_CSS_POST_SLUG,
		'post_status'  => 'private',
		'post_content' => $content
	), true );

	if ( ! is_wp_error( $post ) ) {
		$post = get_post( $post );
	}

	if ( ! is_a( $post, 'WP_Post' ) ) {
		throw new Exception( sprintf(
			// translators: %s is an email address
			__( "Could not create CSS post. Please notify us at %s.", 'wordcamporg' ),
			EMAIL_CENTRAL_SUPPORT
		) );
	}

	return $post;
}

/**
 * Get the mode for outputting the custom CSS.
 *
 * This just uses the same mode as Jetpack's CSS post, because it wouldn't make any sense to have them configured
 * with opposite values.
 *
 * The value is pulled directly from the theme mod, rather than using
 * `Jetpack_Custom_CSS_Enhancements::skip_stylesheet()`, because we don't want to require that the Custom CSS
 * module be active in order to use Remote CSS.
 *
 * todo If replace mode is on, but Jetpack is deactivated, then the stylesheet won't be removed because
 *      `style_filter()` won't be activated to remove it. So, maybe we need to require Custom CSS be activated
 *      all the time anyway?
 *
 * @return string
 */
function get_output_mode() {
	$mode = 'add-on';
	$jetpack_settings = (array) get_theme_mod( 'jetpack_custom_css' );

	if ( isset( $jetpack_settings['replace'] ) && $jetpack_settings['replace'] ) {
		$mode = 'replace';
	}

	return $mode;
}

/**
 * Add a notice to Core's Custom CSS section to offer using Remote CSS
 *
 * The Core/Jetpack CSS editor is not designed to meet the needs of the typical WordCamp organizing team, but teams
 * who aren't satisfied with it may not be aware of Remote CSS. This helps them discover it, so that they can
 * choose which one they want to use.
 *
 * @param WP_Customize_Manager $wp_customize
 */
function add_discovery_notice( $wp_customize ) {
	$plugin_url = plugins_url( '', __DIR__ );

	$notice_text = sprintf(
		__(
			'You can also build your CSS locally using your favorite tools, and collaborate on GitHub with <a href="%s">Remote CSS</a>.',
			'wordcamporg'
		),
		admin_url( 'themes.php?page=remote-css' )
	);

	ob_start();
	require_once( dirname( __DIR__ ) . '/views/template-discovery-notice.php' );
	$description = ob_get_clean();

	$wp_customize->add_control(
		'wcrss_discovery',
		array(
			'priority'    => 1,
			'section'     => 'custom_css',
			'settings'    => array(),
			'description' => $description,
			'input_attrs' => array(
				'style' => 'display: none;',
			),
		)
	);
}
