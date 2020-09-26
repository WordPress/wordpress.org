<?php

/*
 * Plugin Name: Reblog Feed
 * Description: Creates an RSS feed of re-blogged content from external sites. This lives on w.org/news/ and is subscribed to by planet.w.org.
 */


/*
 * TODO:
 * - Remove the archive page, but keep its feed public.
 * - Create dashboard widget with input field. Paste in URL, and CPT is automatically created & published.
 * - Public form to submit URLs. Moderators can accept/reject.
 */


namespace WordPressdotorg\Reblog_Feed;
use WP_Post;


add_action( 'init',                        __NAMESPACE__ . '\register_post_types' );
add_action( 'init',                        __NAMESPACE__ . '\register_post_metas' );
add_filter( 'post_type_link',              __NAMESPACE__ . '\permalink_source_url', 10, 2 );
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_scripts' );


/**
 * Register post types.
 */
function register_post_types() {
	$reblogged_post_args = array(
		'labels' => array(
			'name'          => 'Reblogged Posts',
			'singular_name' => 'Reblogged Post',
		),

		'description'        => 'Each post represents an external post that has been reblogged.',
		'hierarchical'       => false,
		'supports'           => array( 'title', 'editor', 'custom-fields' ),
		'public'             => false,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_rest'       => true,
		'has_archive'        => true,

		'rewrite' => array(
			'slug'  => 'reblog',
			'feeds' => true,
		),
	);

	register_post_type( 'reblogged-post', $reblogged_post_args );
}

/**
 * Register post meta fields.
 */
function register_post_metas() {
	register_post_meta(
		'reblogged-post',
		'rbf_source_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => 'esc_url_raw',
			'show_in_rest'      => true,

			'auth_callback' => function( $post ) {
				return current_user_can( 'edit_post', $post );
			}
		)
	);
}

/**
 * Change the post's permalink to use its source URL instead.
 *
 * @param string $permalink
 * @param WP_Post $post
 *
 * @return string
 */
function permalink_source_url( $permalink, $post ) {
	if ( 'reblogged-post' !== $post->post_type ) {
		return $permalink;
	}

	return $post->rbf_source_url ?? $permalink;
}

/**
 * Enqueue scripts.
 */
function enqueue_scripts() {
	if ( 'reblogged-post' !== get_current_screen()->id ) {
		return;
	}

	wp_enqueue_script(
		'reblog-feed',
		trailingslashit( plugin_dir_url( __FILE__ ) ) . 'reblog-feed.js',
		array( 'wp-element', 'wp-data', 'wp-components', 'wp-plugins', 'wp-edit-post' ),
		filemtime( __DIR__ . '/reblog-feed.js' ),
		true
	);
}
