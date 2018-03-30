<?php

namespace WP15\Theme;

defined( 'WPINC' ) || die();

add_filter( 'template_include',   __NAMESPACE__ . '\get_front_page_template' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );
add_filter( 'get_custom_logo',    __NAMESPACE__ . '\set_custom_logo' );
add_filter( 'body_class',         __NAMESPACE__ . '\add_body_classes' );


/**
 * Bypass TwentySeventeen's front-page template.
 *
 * When a static front page is configured, its corresponding template should be used to render the page, not
 * TwentySeventeen's generic front-page template.
 *
 * @param string $template
 *
 * @return string
 */
function get_front_page_template( $template ) {
	if ( false !== strpos( $template, 'twentyseventeen/front-page.php' ) ) {
		$template = get_page_template();
	}

	return $template;
}

/**
 * Enqueue scripts and styles
 */
function enqueue_scripts() {
	wp_register_style(
		'source-sans-pro',
		'https://fonts.googleapis.com/css?family=Crete+Round|Source+Sans+Pro:400,400i,600,700&subset=cyrillic,cyrillic-ext,greek,greek-ext,latin-ext,vietnamese'
	);

	wp_register_style(
		'twentyseventeen-parent-style',
		get_template_directory_uri() . '/style.css'
	);

	wp_enqueue_style(
		'twentyseventeen-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'twentyseventeen-parent-style', 'source-sans-pro' ),
		filemtime( __DIR__ . '/style.css' )
	);

	// Styles for locale switcher.
	wp_enqueue_style( 'select2' );
}

/**
 * Add the post's slug to the body tag
 *
 * For CSS developers, this is better than relying on the post ID, because that often changes between their local
 * development environment and production, and manually importing/exporting is inconvenient.
 *
 * @param array $body_classes
 *
 * @return array
 */
function add_body_classes( $body_classes ) {
	global $wp_query;
	$post = $wp_query->get_queried_object();

	if ( is_a( $post, 'WP_Post' ) ) {
		$body_classes[] = $post->post_type . '-slug-' . sanitize_html_class( $post->post_name, $post->ID );
	}

	return $body_classes;
}

/**
 * Set the custom logo.
 *
 * @return string
 */
function set_custom_logo() {
	ob_start();

	?>

	<a href="<?php echo esc_url( home_url() ); ?>" class="custom-logo-link" rel="home" itemprop="url">
		<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/wp15-logo.svg" class="custom-logo" alt="WordPress 15th anniversary logo" itemprop="logo" />
	</a>

	<?php

	return ob_get_clean();
};

/**
 * Data for the Swag page download items.
 *
 * @return array
 */
function get_swag_download_items() {
	return array(
		/*
		array(
			'title'             => __( '', 'wp15' ),
			'preview_image_url' => '',
			'files'             => array(
				array(
					'name' => __( '', 'wp15' ),
					'url'  => '',
				),
			),
		),
		*/
		array(
			'title'             => __( 'WP15 Logo', 'wp15' ),
			'preview_image_url' => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_logo_preview.png',
			'files'             => array(
				array(
					'name' => __( 'AI source file (vector)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_logo_source.ai',
				),
				array(
					'name' => __( 'PDF (vector)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_logo.pdf',
				),
				array(
					'name' => __( 'PNG (blue/white/transparent)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_logo_blue-white-transparent.png',
				),
				array(
					'name' => __( 'PNG (blue/transparent)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_logo_blue-transparent.png',
				),
				array(
					'name' => __( 'PNG (white/transparent)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_logo_white-transparent.png',
				),
			),
		),
		array(
			'title'             => 'wp_is( 15 );',
			'preview_image_url' => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_is15_preview.png',
			'files'             => array(
				array(
					'name' => __( 'AI source file (vector)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_is15_source.ai',
				),
				array(
					'name' => __( 'PDF (vector)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_is15.pdf',
				),
				array(
					'name' => __( 'PNG (gray/transparent)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_is15_gray-transparent.png',
				),
			),
		),
		array(
			'title'             => __( 'Sticker sheet', 'wp15' ),
			'preview_image_url' => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_stickers_preview.png',
			'files'             => array(
				array(
					'name' => __( 'AI source file (vector)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_stickers_source.ai',
				),
				array(
					'name' => __( 'PDF (vector)', 'wp15' ),
					'url'  => 'https://wp15.wordpress.net/content/uploads/2018/03/swag_wp15_stickers.pdf',
				),
			),
		),
	);
}
