<?php
/**
 * Jobs Theme 2026 functions and definitions.
 *
 * Presentation only; job data and behavior live in the wporg-jobs-blocks plugin.
 *
 * @package wporg-jobs-2026
 */

declare( strict_types = 1 );

/**
 * Theme supports and the editor stylesheet.
 */
function wporg_jobs_2026_setup(): void {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'style.css' );
}
add_action( 'after_setup_theme', 'wporg_jobs_2026_setup' );

/**
 * Root stylesheet. Block themes do not enqueue style.css by themselves.
 */
function wporg_jobs_2026_enqueue_assets(): void {
	wp_enqueue_style(
		'wporg-jobs-2026-style',
		get_stylesheet_uri(),
		array(),
		wp_get_theme()->get( 'Version' )
	);
}
add_action( 'wp_enqueue_scripts', 'wporg_jobs_2026_enqueue_assets' );

/**
 * Block style variations and the one stylesheet per block type that backs them.
 */
function wporg_jobs_2026_register_block_styles(): void {
	$ver = wp_get_theme()->get( 'Version' );

	wp_enqueue_block_style(
		'core/heading',
		array(
			'handle' => 'wporg-jobs-2026-block-core-heading',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-heading.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-heading.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/heading',
		array(
			'name'  => 'highlight',
			'label' => __( 'Highlight', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/group',
		array(
			'handle' => 'wporg-jobs-2026-block-core-group',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-group.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-group.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'card',
			'label' => __( 'Card', 'wporg-jobs-2026' ),
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'sticky-aside',
			'label' => __( 'Sticky below header', 'wporg-jobs-2026' ),
		)
	);
	register_block_style(
		'core/group',
		array(
			'name'  => 'hide-when-empty',
			'label' => __( 'Hide when a field is empty', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/post-terms',
		array(
			'handle' => 'wporg-jobs-2026-block-core-post-terms',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-post-terms.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-post-terms.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/post-terms',
		array(
			'name'  => 'badge',
			'label' => __( 'Badge', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/post-template',
		array(
			'handle' => 'wporg-jobs-2026-block-core-post-template',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-post-template.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-post-template.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/post-template',
		array(
			'name'  => 'zebra',
			'label' => __( 'Striped rows', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/list',
		array(
			'handle' => 'wporg-jobs-2026-block-core-list',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-list.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-list.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/list',
		array(
			'name'  => 'plain',
			'label' => __( 'Plain', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/categories',
		array(
			'handle' => 'wporg-jobs-2026-block-core-categories',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-categories.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-categories.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/categories',
		array(
			'name'  => 'plain',
			'label' => __( 'Plain', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/term-template',
		array(
			'handle' => 'wporg-jobs-2026-block-core-term-template',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-term-template.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-term-template.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/term-template',
		array(
			'name'  => 'pills',
			'label' => __( 'Filter pills', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/post-content',
		array(
			'handle' => 'wporg-jobs-2026-block-core-post-content',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-post-content.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-post-content.css' ),
			'ver'    => $ver,
		)
	);
	register_block_style(
		'core/post-content',
		array(
			'name'  => 'job-description',
			'label' => __( 'Job description', 'wporg-jobs-2026' ),
		)
	);

	wp_enqueue_block_style(
		'core/navigation',
		array(
			'handle' => 'wporg-jobs-2026-block-core-navigation',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-navigation.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-navigation.css' ),
			'ver'    => $ver,
		)
	);

	wp_enqueue_block_style(
		'core/search',
		array(
			'handle' => 'wporg-jobs-2026-block-core-search',
			'src'    => get_theme_file_uri( 'assets/css/blocks/core-search.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/core-search.css' ),
			'ver'    => $ver,
		)
	);

	wp_enqueue_block_style(
		'wporg-jobs/open-to-work',
		array(
			'handle' => 'wporg-jobs-2026-block-wporg-jobs-open-to-work',
			'src'    => get_theme_file_uri( 'assets/css/blocks/wporg-jobs-open-to-work.css' ),
			'path'   => get_theme_file_path( 'assets/css/blocks/wporg-jobs-open-to-work.css' ),
			'ver'    => $ver,
		)
	);
}
add_action( 'init', 'wporg_jobs_2026_register_block_styles' );

/**
 * Falls back to the theme's SVG logo when no custom logo is set.
 *
 * @param string $html Logo markup from get_custom_logo().
 * @return string
 */
function wporg_jobs_2026_default_custom_logo( string $html ): string {
	if ( ! empty( $html ) ) {
		return $html;
	}

	return sprintf(
		'<a href="%1$s" class="custom-logo-link" rel="home"><img src="%2$s" class="custom-logo" alt="%3$s" width="36" height="36" /></a>',
		esc_url( home_url( '/' ) ),
		esc_url( get_theme_file_uri( 'assets/logo.svg' ) ),
		esc_attr_x( 'WordPress Jobs', 'Site logo alt text', 'wporg-jobs-2026' )
	);
}
add_filter( 'get_custom_logo', 'wporg_jobs_2026_default_custom_logo' );

/**
 * Marks the navigation link matching the request path as current; core matches by post id, which differs per environment.
 *
 * @param string $block_content Rendered link.
 * @param array  $block         Parsed block.
 * @return string
 */
function wporg_jobs_2026_mark_current_nav_link( string $block_content, array $block ): string {
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
add_filter( 'render_block_core/navigation-link', 'wporg_jobs_2026_mark_current_nav_link', 10, 2 );
