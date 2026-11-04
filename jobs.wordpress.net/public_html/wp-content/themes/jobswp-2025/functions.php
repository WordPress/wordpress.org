<?php
/**
 * Jobs Theme 2026 functions and definitions.
 *
 * Presentation only. Job data, bindings, the candidates block, the form
 * partials and the site-level hooks live in the jobswp-blocks plugin.
 *
 * @package jobswp-2025
 */

/**
 * Theme supports and the editor stylesheet.
 */
function jobswp_2025_setup() {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'jobswp_2025_setup' );

/**
 * Root stylesheet. Block themes do not enqueue style.css by themselves.
 */
function jobswp_2025_enqueue_assets() {
	wp_enqueue_style(
		'jobswp-2025-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'jobswp_2025_enqueue_assets' );

/**
 * Block style variations and the one stylesheet per block type that backs them.
 */
function jobswp_2025_register_block_styles() {
	$ver = wp_get_theme()->get( 'Version' );

	wp_enqueue_block_style(
		'core/heading',
		array(
			'handle' => 'jobswp-2025-block-core-heading',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-heading.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-heading.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/heading',
		array(
			'name'  => 'highlight',
			'label' => __( 'Highlight', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/group',
		array(
			'handle' => 'jobswp-2025-block-core-group',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-group.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-group.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'card',
			'label' => __( 'Card', 'jobswp-2025' ),
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'sticky-aside',
			'label' => __( 'Sticky below header', 'jobswp-2025' ),
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'hide-when-empty',
			'label' => __( 'Hide when a field is empty', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/post-terms',
		array(
			'handle' => 'jobswp-2025-block-core-post-terms',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-post-terms.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-post-terms.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/post-terms',
		array(
			'name'  => 'badge',
			'label' => __( 'Badge', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/post-template',
		array(
			'handle' => 'jobswp-2025-block-core-post-template',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-post-template.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-post-template.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/post-template',
		array(
			'name'  => 'zebra',
			'label' => __( 'Striped rows', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/list',
		array(
			'handle' => 'jobswp-2025-block-core-list',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-list.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-list.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/list',
		array(
			'name'  => 'plain',
			'label' => __( 'Plain', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/categories',
		array(
			'handle' => 'jobswp-2025-block-core-categories',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-categories.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-categories.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/categories',
		array(
			'name'  => 'plain',
			'label' => __( 'Plain', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/term-template',
		array(
			'handle' => 'jobswp-2025-block-core-term-template',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-term-template.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-term-template.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/term-template',
		array(
			'name'  => 'pills',
			'label' => __( 'Filter pills', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/post-content',
		array(
			'handle' => 'jobswp-2025-block-core-post-content',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-post-content.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-post-content.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/post-content',
		array(
			'name'  => 'job-description',
			'label' => __( 'Job description', 'jobswp-2025' ),
		)
	);

	wp_enqueue_block_style(
		'core/navigation',
		array(
			'handle' => 'jobswp-2025-block-core-navigation',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-navigation.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-navigation.css' ),
			'ver'    => $ver,
		)
	);

	wp_enqueue_block_style(
		'core/search',
		array(
			'handle' => 'jobswp-2025-block-core-search',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-search.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-search.css' ),
			'ver'    => $ver,
		)
	);

	wp_enqueue_block_style(
		'jobswp/open-to-work',
		array(
			'handle' => 'jobswp-2025-block-jobswp-open-to-work',
			'src'    => get_theme_file_uri( 'assets/css/blocks/jobswp-open-to-work.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/jobswp-open-to-work.css' ),
			'ver'    => $ver,
		)
	);
}
add_action( 'init', 'jobswp_2025_register_block_styles' );

/**
 * Falls back to the theme's SVG logo when no custom logo is set, so the
 * header part holds no environment-specific media id.
 *
 * @param string $html Logo markup from get_custom_logo().
 * @return string
 */
function jobswp_2025_default_custom_logo( $html ) {
	if ( ! empty( $html ) ) {
		return $html;
	}

	return sprintf(
		'<a href="%1$s" class="custom-logo-link" rel="home"><img src="%2$s" class="custom-logo" alt="%3$s" width="36" height="36" /></a>',
		esc_url( home_url( '/' ) ),
		esc_url( get_theme_file_uri( 'assets/logo.svg' ) ),
		esc_attr_x( 'WordPress Jobs', 'Site logo alt text', 'jobswp-2025' )
	);
}
add_filter( 'get_custom_logo', 'jobswp_2025_default_custom_logo' );

/**
 * Marks the navigation link whose path matches the request as core marks
 * a current item: `current-menu-item` on the item (the `-current` state in
 * theme.json styles it) and aria-current="page" on the link. Core matches by
 * post id, which differs per environment. In-page links (`/#candidates`),
 * links to other hosts and search results (whose address is `/?s=`) are never
 * current.
 *
 * @param string $block_content Rendered link.
 * @param array  $block         Parsed block.
 * @return string
 */
function jobswp_2025_mark_current_nav_link( $block_content, $block ) {
	if ( is_search() || empty( $block['attrs']['url'] ) || ! is_string( $block['attrs']['url'] ) ) {
		return $block_content;
	}

	$link = wp_parse_url( $block['attrs']['url'] );

	if ( ! is_array( $link ) || ! empty( $link['fragment'] ) ) {
		return $block_content;
	}

	$link_host = $link['host'] ?? '';

	if ( '' !== $link_host && wp_parse_url( home_url(), PHP_URL_HOST ) !== $link_host ) {
		return $block_content;
	}

	$link_path    = trailingslashit( (string) ( $link['path'] ?? '/' ) );
	$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
	$request_path = trailingslashit( (string) wp_parse_url( $request_uri, PHP_URL_PATH ) );
	$home_path    = trailingslashit( (string) wp_parse_url( home_url(), PHP_URL_PATH ) );

	if ( '/' !== $home_path && 0 === strpos( $request_path, $home_path ) ) {
		$request_path = trailingslashit( '/' . ltrim( substr( $request_path, strlen( $home_path ) ), '/' ) );
	}

	if ( $link_path !== $request_path ) {
		return $block_content;
	}

	$tags = new WP_HTML_Tag_Processor( $block_content );
	if ( $tags->next_tag( 'li' ) ) {
		$tags->add_class( 'current-menu-item' );
	}
	if ( $tags->next_tag( 'a' ) ) {
		$tags->set_attribute( 'aria-current', 'page' );
	}

	return $tags->get_updated_html();
}
add_filter( 'render_block_core/navigation-link', 'jobswp_2025_mark_current_nav_link', 10, 2 );
