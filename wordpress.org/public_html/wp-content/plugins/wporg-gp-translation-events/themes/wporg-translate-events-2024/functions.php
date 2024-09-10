<?php

namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Urls;

function register_blocks(): void {
	include_once __DIR__ . '/blocks/header/index.php';
	include_once __DIR__ . '/blocks/event-excerpt/index.php';
	include_once __DIR__ . '/blocks/event-date/index.php';
	include_once __DIR__ . '/blocks/event-template/index.php';
	include_once __DIR__ . '/blocks/event-title/index.php';
	include_once __DIR__ . '/blocks/event-list/index.php';
	include_once __DIR__ . '/blocks/footer/index.php';
	include_once __DIR__ . '/blocks/pages/events/my-events/index.php';
	include_once __DIR__ . '/blocks/event-attendance-mode/index.php';
	include_once __DIR__ . '/blocks/event-flag/index.php';
}

add_action(
	'init',
	function (): void {
		do_action( 'wporg_translate_events_theme_init' );
	}
);

add_action(
	'wporg_translate_events_theme_init',
	function (): void {
		register_blocks();
		add_action(
			'wp_head',
			function (): void {
				add_social_tags(
					esc_html__( 'Translation Events', 'wporg-translate-events-2024' ),
					Urls::events_home(),
					esc_html__( 'WordPress Translation Events', 'wporg-translate-events-2024' ),
					Urls::event_default_image()
				);

				wp_enqueue_style(
					'wporg-translate-events-2024-style',
					get_stylesheet_uri(),
					array(),
					filemtime( __DIR__ . '/style.css' )
				);
			}
		);
	}
);

add_filter(
	'wporg_block_navigation_menus',
	function (): array {
		return array(
			'site-header-menu' => array(
				array(
					'label' => esc_html__( 'Events', 'wporg-plugins' ),
					'url'   => 'https://translate.wordpress.org/events/',
				),
				array(
					'label' => esc_html__( 'Team', 'wporg-plugins' ),
					'url'   => 'https://make.wordpress.org/polyglots/teams/',
				),
				array(
					'label' => esc_html__( 'Requests', 'wporg-plugins' ),
					'url'   => 'https://make.wordpress.org/polyglots/?resolved=unresolved',
				),
				array(
					'label' => esc_html__( 'Weekly Chats', 'wporg-plugins' ),
					'url'   => 'https://make.wordpress.org/polyglots/category/weekly-chats/',
				),
				array(
					'label' => esc_html__( 'Translate', 'wporg-plugins' ),
					'url'   => 'https://translate.wordpress.org/',
				),
				array(
					'label' => esc_html__( 'Handbook', 'wporg-plugins' ),
					'url'   => 'https://make.wordpress.org/polyglots/handbook/',
				),
			),
		);
	}
);

// Declare the base breadcrumbs, which apply to all pages.
// Pages can add additional levels of breadcrumbs.
add_filter(
	'wporg_block_site_breadcrumbs',
	function (): array {
		return array(
			array(
				'url'   => home_url(),
				'title' => __( 'Home', 'wporg-translate-events-2024' ),
			),
			array(
				'url'   => Urls::events_home(),
				'title' => __( 'Events', 'wporg-translate-events-2024' ),
			),
		);
	}
);

/**
 * Add social tags to the head of the page.
 *
 * @param string $html_title       The title of the page.
 * @param string $url              The URL of the page.
 * @param string $html_description The description of the page.
 * @param string $image_url        The URL of the image to use.
 *
 * @return void
 */
function add_social_tags( string $html_title, string $url, string $html_description, string $image_url ) {
	$meta_tags = array(
		'name'     => array(
			'twitter:card'        => 'summary',
			'twitter:site'        => '@WordPress',
			'twitter:title'       => esc_attr( $html_title ),
			'twitter:description' => esc_attr( $html_description ),
			'twitter:creator'     => '@WordPress',
			'twitter:image'       => esc_url( $image_url ),
			'twitter:image:alt'   => esc_attr( $html_title ),
		),
		'property' => array(
			'og:url'              => esc_url( $url ),
			'og:title'            => esc_attr( $html_title ),
			'og:description'      => esc_attr( $html_description ),
			'og:site_name'        => esc_attr( get_bloginfo() ),
			'og:image:url'        => esc_url( $image_url ),
			'og:image:secure_url' => esc_url( $image_url ),
			'og:image:type'       => 'image/png',
			'og:image:width'      => '1200',
			'og:image:height'     => '675',
			'og:image:alt'        => esc_attr( $html_title ),
		),
	);

	foreach ( $meta_tags as $name => $content ) {
		foreach ( $content as $key => $value ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '<meta ' . esc_attr( $name ) . '="' . esc_attr( $key ) . '" content="' . esc_attr( $value ) . '" />' . "\n";
		}
	}
}

// The $attributes argument cannot be removed despite not being used in the function, because otherwise it won't be
// in scope for the rendered template.
// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
function render_page( string $template_path, string $title, array $attributes ): void {
	// The page content must be rendered before the header block, so that styles and scripts of the referenced blocks
	// are registered.
	ob_start();
	require $template_path;
	$rendered_template = ob_get_clean();
	$page_title        = esc_html( $title );
	$page_content      = do_blocks(
		<<<BLOCKS
		<!-- wp:group {"tagName":"main","style":{"spacing":{"blockGap":"0px"}},"className":"entry-content","layout":{"type":"constrained"}} -->
		<main class="wp-block-group entry-content">
			<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|20","left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space","bottom":"var:preset|spacing|50"}}},"layout":{"type":"default"}} -->
			<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--50);padding-left:var(--wp--preset--spacing--edge-space)">
				<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
				<div class="wp-block-group page-upcoming-title-past-wrapper">
					<!-- wp:heading --><h2 class="wp-block-heading">$page_title</h2><!-- /wp:heading -->
				</div>
				<!-- /wp:group -->

				<!-- wp:group {"layout":{"type":"inherit","flexWrap":"nowrap","justifyContent":"space-between"}} -->
				<div class="wp-block-group">$rendered_template</div>
				<!-- /wp:group -->
			</div>
			<!-- /wp:group -->
		</main>
		<!-- /wp:group -->
		BLOCKS
	);

	$header_json = wp_json_encode( array( 'title' => $title ) );
	echo do_blocks( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		<<<BLOCKS
		<!-- wp:wporg-translate-events-2024/header $header_json /-->
		$page_content
		<!-- wp:wporg-translate-events-2024/footer /-->
		BLOCKS
	);
}
