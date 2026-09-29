<?php

namespace WordPressdotorg\Theme\Documentation_2022;

use WP_Block_Supports;

// Block files
require_once __DIR__ . '/src/article-list/index.php';

/**
 * Actions and filters.
 */
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );
add_filter( 'wporg_block_site_breadcrumbs', __NAMESPACE__ . '\set_site_breadcrumbs' );
add_action( 'pre_get_posts', __NAMESPACE__ . '\pre_get_posts' );
add_filter( 'query_loop_block_query_vars', __NAMESPACE__ . '\modify_query_loop_block_query_vars', 10, 2 );
add_filter( 'comment_form_defaults', __NAMESPACE__ . '\comment_form_defaults' );
add_filter( 'comment_form_field_comment', __NAMESPACE__ . '\hide_field_after_submission' );
add_filter( 'comment_form_submit_field', __NAMESPACE__ . '\hide_field_after_submission' );
add_filter( 'comment_post_redirect', __NAMESPACE__ . '\comment_post_redirect', 10, 2 );
add_filter( 'render_block_core/term-description', __NAMESPACE__ . '\inject_term_description', 10, 3 );
add_filter( 'jetpack_open_graph_tags', __NAMESPACE__ . '\custom_open_graph_tags' );
add_filter( 'wporg_block_navigation_menus', __NAMESPACE__ . '\add_site_navigation_menus' );

// Hide edit Site from the admin bar (Site Editor entry point).
add_action( 'admin_bar_menu', __NAMESPACE__ . '\hide_site_editor_node', 999 );

/**
 * Remove the Site Editor admin-bar item on the docs site.
 *
 * Keeps editors in the page editor workflow.
 *
 * @param WP_Admin_Bar $bar Admin Bar instance.
 */
function hide_site_editor_node( $bar ) {
	$bar->remove_node( 'site-editor' );
}
// Enable Jetpack opengraph by default
add_filter( 'jetpack_enable_open_graph', '__return_true' );

// Enforce log in to leave feedback.
add_filter( 'pre_option_comment_registration', '__return_true' );

// Remove table of contents.
add_filter( 'wporg_handbook_toc_should_add_toc', '__return_false' );

/**
 * Enqueue scripts and styles.
 */
function enqueue_assets() {
	// The parent style is registered as `wporg-parent-2021-style`, and will be loaded unless
	// explicitly unregistered. We can load any child-theme overrides by declaring the parent
	// stylesheet as a dependency.
	wp_enqueue_style(
		'wporg-docs-2021-style',
		get_stylesheet_directory_uri() . '/build/style/style-index.css',
		array( 'wporg-parent-2021-style', 'wporg-global-fonts' ),
		filemtime( __DIR__ . '/build/style/style-index.css' )
	);
}

/**
 * Update the breadcrumbs format for this site.
 *
 * @param array $breadcrumbs An array of breadcrumb links.
 * @return array Updated breadcrumbs.
 */
function set_site_breadcrumbs( $breadcrumbs ) {
	if ( is_front_page() ) {
		return array();
	}

	// Set the first item's title. By default this is the site title, but the
	// site title for `w.org/support` is "WordPress Forums", which is not
	// correct for the breadcrumbs.
	$breadcrumbs[0]['title'] = __( 'Home', 'wporg-docs' );

	if ( is_category() ) {
		// Format: home / topic page / category
		$category    = get_queried_object();
		$breadcrumbs = array( $breadcrumbs[0] );
		if ( $category->category_parent ) {
			$parent        = get_term( $category->category_parent );
			$breadcrumbs[] = array(
				'url'   => get_topic_permalink( $parent ),
				'title' => $parent->name,
			);
		}
		$breadcrumbs[] = array(
			'url'   => '',
			'title' => $category->name,
		);
	} elseif ( is_singular( 'helphub_article' ) ) {
		// Format: home / topic page / category / article title
		$breadcrumbs = array( $breadcrumbs[0] );
		$categories  = get_the_category();
		if ( $categories ) {
			$cats_without_parents = wp_list_filter( $categories, array( 'parent' => 0 ) );
			$cats_with_parents = wp_list_filter( $categories, array( 'parent' => 0 ), 'NOT' );
			if ( $cats_without_parents ) {
				$category = reset( $cats_without_parents );
				$breadcrumbs[] = array(
					'url'   => get_topic_permalink( $category ),
					'title' => $category->name,
				);
			}
			if ( $cats_with_parents ) {
				$category = reset( $cats_with_parents );
				$breadcrumbs[] = array(
					'url'   => get_term_link( $category->term_id, $category->taxonomy ),
					'title' => $category->name,
				);
			}
		}
		$breadcrumbs[] = array(
			'url'   => '',
			'title' => get_the_title(),
		);
	}

	return $breadcrumbs;
}

/**
 * Get the topic landing page permalink for a given parent category.
 */
function get_topic_permalink( $category ) {
	if ( empty( $category->slug ) ) {
		return '';
	}

	if ( 'wordpress-overview' === $category->slug ) {
		return site_url( '/overview/' );
	}

	return site_url( '/' . $category->slug . '/' );
}

/**
 * Filter the default query.
 *
 * Used to render posts on archive pages (query.inherit = true).
 *
 * @param \WP_Query $query The WordPress Query object.
 */
function pre_get_posts( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	// Show many articles on the archive pages.
	if ( ! $query->is_singular() ) {
		$query->set( 'posts_per_page', 50 );
		$query->set(
			'orderby',
			array(
				'menu_order' => 'desc',
				'title' => 'asc',
			)
		);
	}
}

/**
 * Override the Query Loop block on the Commercial page.
 *
 * @param array    $query Array containing parameters for `WP_Query` as parsed by the block context.
 * @param WP_Block $block Block instance.
 *
 * @return array
 */
function modify_query_loop_block_query_vars( $query, $block ) {
	if ( 'helphub_article' === $block->context['query']['postType'] ) {
		$query['orderby'] = array(
			'menu_order' => 'desc',
			'title' => 'asc',
		);
	}

	return $query;
}

/**
 * Modify the redirect after a feedback comment is submitted on an Article.
 *
 * @param string     $location
 * @param WP_Comment $comment
 *
 * @return string
 */
function comment_post_redirect( $location, $comment ) {
	if ( 'helphub_article' === get_post_type( $comment->comment_post_ID ) ) {
		$location  = substr( $location, 0, strpos( $location, '#' ) );
		$location  = add_query_arg( 'feedback_submitted', 1, $location );
		$location .= '#reply-title';
	}

	return $location;
}

/**
 * Check if the current page has the submission parameter.
 *
 * This is set on the redirect in `comment_post_redirect` (above).
 */
function has_submitted_comment_form() {
	return isset( $_GET['feedback_submitted'] ) && $_GET['feedback_submitted'];  // phpcs:ignore
}

/**
 * Update the comment form settings.
 *
 * @param array $fields The default comment form arguments.
 *
 * @return array Returns the modified fields.
 */
function comment_form_defaults( $fields ) {
	$post    = get_post();
	$post_id = $post->ID;

	$forums_url = 'https://wordpress.org/support/forums/';

	$user          = wp_get_current_user();
	$user_identity = $user->exists() ? $user->display_name : '';

	$str_log_in = sprintf(
		/* translators: 1: log in link, 2: support forums link. */
		__( '<a href="%1$s">Log in to submit feedback</a>. If you need support with something that wasn&#39;t covered by this article, please post your question in the <a href="%2$s">support forums</a>.', 'wporg-docs' ),
		esc_url( wp_login_url( apply_filters( 'the_permalink', get_permalink( $post_id ), $post_id ) ) ),
		esc_url( $forums_url )
	);
	$fields['must_log_in'] = '<p class="must-log-in">' . $str_log_in . '</p>';

	$fields['title_reply_before'] = '<h2 id="reply-title" class="comment-reply-title has-heading-3-font-size" style="margin-top:0">';
	$fields['title_reply_after']  = '</h2>';
	$fields['title_reply']        = __( 'Was this article helpful? How could it be improved?', 'wporg-docs' );
	if ( has_submitted_comment_form() ) {
		$fields['title_reply'] = __( 'Thank you for your feedback!', 'wporg-docs' );
	}

	$fields['label_submit'] = __( 'Submit feedback', 'wporg-docs' );

	$fields['logged_in_as']  = '<p>';
	$fields['logged_in_as'] .= __( 'Feedback you send to us will go only to the folks who maintain documentation. They may reach out in case there are questions or would like to followup feedback. But that too will stay behind the scenes.', 'wporg-docs' );
	$fields['logged_in_as'] .= '</p></p>';
	$fields['logged_in_as'] .= sprintf(
		/* translators: %s: support forums link. */
		__( 'This is not for personalized support. Please create a <a href="%s">forum thread</a> instead to receive help from the community.', 'wporg-docs' ),
		esc_url( $forums_url )
	);
	$fields['logged_in_as'] .= '</p>';

	$fields['comment_notes_after']  = '<p class="logged-in-as">';
	$fields['comment_notes_after'] .= sprintf(
		/* translators: 1: User name, 2: Logout URL. */
		__( 'Logged in as %1$s (<a href="%2$s">log out?</a>)', 'wporg-docs' ),
		$user_identity,
		wp_logout_url( apply_filters( 'the_permalink', get_permalink( $post_id ), $post_id ) )
	);
	$fields['comment_notes_after'] .= '</p>';

	if ( has_submitted_comment_form() ) {
		$fields['logged_in_as']        = '<p>' . __( 'We will review it as quickly as possible.', 'wporg-docs' ) . '</p>';
		$fields['comment_notes_after'] = '';
	}

	return $fields;
}

/**
 * Remove the field if the form has been submitted.
 *
 * @param string $field The HTML-formatted output of the comment form field.
 *
 * @return string Empty string or initial field.
 */
function hide_field_after_submission( $field ) {
	if ( has_submitted_comment_form() ) {
		return '';
	}
	return $field;
}

/**
 * Enable use of the "Term Description" block on the topic landing pages.
 *
 * This finds the corresponding category for the given topic page and shows that description.
 *
 * @param string   $block_content The block content.
 * @param array    $block         The full block, including name and attributes.
 * @param WP_Block $instance      The block instance.
 *
 * @return string Updated block content.
 */
function inject_term_description( $block_content, $block, $instance ) {
	global $post;
	$topic_pages = array(
		'overview',
		'technical-guides',
		'support-guides',
		'customization',
	);

	if ( is_page( $topic_pages ) ) {
		$term_slug        = ( 'overview' === $post->post_name ) ? 'wordpress-overview' : $post->post_name;
		$term             = get_term_by( 'slug', $term_slug, 'category' );
		$term_description = term_description( $term->term_id );
		$extra_attributes = ( isset( $attributes['textAlign'] ) )
			? array( 'class' => 'has-text-align-' . $attributes['textAlign'] )
			: array();

		// Required to prevent `block_to_render` from being null in `get_block_wrapper_attributes`.
		$parent = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = $block;
		$wrapper_attributes = get_block_wrapper_attributes( $extra_attributes );
		WP_Block_Supports::$block_to_render = $parent;

		return '<div ' . $wrapper_attributes . '>' . $term_description . '</div>';
	}

	return $block_content;
}

/**
 * Customize the open graph tags.
 *
 * This provides better tags on the front page and ensures the tags are set on content.
 *
 * @param array $tags Optional. Open Graph tags.
 * @return array Filtered Open Graph tags.
 */
function custom_open_graph_tags( $tags = array() ) {
	$site_title = get_bloginfo( 'name' );

	// Use `name=""` for description.
	// See Jetpacks Twitter Card for where it happens for the twitter:* fields.
	add_filter(
		'jetpack_open_graph_output',
		function ( $html ) {
			return str_replace( '<meta property="description"', '<meta name="description"', $html );
		}
	);

	// Override the Front-page tags.
	if ( is_front_page() ) {
		$desc = __( 'We\'ve got a variety of resources to help you get the most out of WordPress.', 'wporg-docs' );
		return array(
			'og:type'         => 'website',
			'og:title'        => $site_title,
			'og:description'  => $desc,
			'description'     => $desc,
			'og:url'          => home_url( '/' ),
			'og:site_name'    => $site_title,
			'og:image'        => 'https://wordpress.org/files/2022/08/embed-image.png',
			'og:locale'       => get_locale(),
			'twitter:card'    => 'summary_large_image',
			'twitter:creator' => '@WordPress',
		);
	} else if ( is_category() ) {
		$tags['og:image'] = 'https://wordpress.org/files/2022/08/embed-image.png';
		return $tags;
	}

	$post = get_post();
	if ( ! $post ) {
		return $tags;
	}

	$title = get_the_title();
	$desc  = get_the_excerpt();

	if ( is_page() ) {
		$term_slug = ( 'overview' === $post->post_name ) ? 'wordpress-overview' : $post->post_name;
		$term = get_term_by( 'slug', $term_slug, 'category' );
		if ( $term ) {
			$desc = trim( strip_tags( term_description( $term->term_id ) ) );
		}
	}

	$tags['og:title']            = $title;
	$tags['twitter:text:title']  = $title;
	$tags['og:description']      = $desc;
	$tags['twitter:description'] = $desc;
	$tags['description']         = $desc;

	return $tags;
}

/**
 * Provide a list of local navigation menus.
 */
function add_site_navigation_menus( $menus ) {
	return array(
		'documentation' => array(
			array(
				'label' => __( 'WordPress Overview', 'wporg-docs' ),
				'url' => '/overview/',
			),
			array(
				'label' => __( 'Technical Guides', 'wporg-docs' ),
				'url' => '/technical-guides/',
			),
			array(
				'label' => __( 'Support Guides', 'wporg-docs' ),
				'url' => '/support-guides/',
			),
			array(
				'label' => __( 'Customization', 'wporg-docs' ),
				'url' => '/customization/',
			),
			array(
				'label' => __( 'Get Involved', 'wporg-docs' ),
				'url' => 'https://make.wordpress.org/docs/',
			),
		),
	);
}
