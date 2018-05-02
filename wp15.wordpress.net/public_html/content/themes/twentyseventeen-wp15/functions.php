<?php

namespace WP15\Theme;

defined( 'WPINC' ) || die();

add_filter( 'template_include',      __NAMESPACE__ . '\get_front_page_template'          );
add_action( 'wp_enqueue_scripts',    __NAMESPACE__ . '\enqueue_scripts'                  );
add_filter( 'get_custom_logo',       __NAMESPACE__ . '\set_custom_logo'                  );
add_filter( 'body_class',            __NAMESPACE__ . '\add_body_classes'                 );
add_filter( 'the_title',             __NAMESPACE__ . '\internationalize_titles'          );
add_filter( 'document_title_parts',  __NAMESPACE__ . '\internationalize_document_titles' );
add_filter( 'wp_get_nav_menu_items', __NAMESPACE__ . '\internationalize_menu_items'      );
add_action( 'wp_head',               __NAMESPACE__ . '\render_social_meta_tags'          );


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
 * Register custom fonts.
 */
function get_fonts_url() {
	$fonts_url = '';
	$fonts     = array();
	$subsets   = 'cyrillic,cyrillic-ext,greek,greek-ext,latin,latin-ext,vietnamese';

	/*
	 * Translators: If there are characters in your language that are not supported
	 * by Source Sans Pro, translate this to 'off'. Do not translate into your own language.
	 */
	if ( 'off' !== _x( 'on', 'Source Sans Pro font: on or off', 'wp15' ) ) {
		$fonts[] = 'Source Sans Pro:400,400i,600,700';
	}

	/*
	 * Translators: If there are characters in your language that are not supported
	 * by Crete Round, translate this to 'off'. Do not translate into your own language.
	 */
	if ( 'off' !== _x( 'on', 'Crete Round font: on or off', 'wp15' ) ) {
		$fonts[] = 'Crete Round';
	}

	if ( $fonts ) {
		$fonts_url = add_query_arg( array(
			'family' => rawurlencode( implode( '|', $fonts ) ),
			'subset' => rawurlencode( $subsets ),
		), 'https://fonts.googleapis.com/css' );
	}

	return $fonts_url;
}

/**
 * Enqueue scripts and styles
 */
function enqueue_scripts() {
	wp_register_style(
		'twentyseventeen-wp15-fonts',
		get_fonts_url()
	);

	wp_register_style(
		'twentyseventeen-parent-style',
		get_template_directory_uri() . '/style.css'
	);

	wp_enqueue_style(
		'twentyseventeen-style',
		get_stylesheet_directory_uri() . '/style.css',
		array( 'twentyseventeen-parent-style', 'twentyseventeen-wp15-fonts', 'dashicons' ),
		filemtime( __DIR__ . '/style.css' )
	);

	// Styles for locale switcher.
	wp_enqueue_style( 'select2' );

	wp_enqueue_script(
		'twentyseventeen-wp15-front-end',
		get_theme_file_uri( '/assets/js/front-end.js' ),
		array( 'jquery', 'twentyseventeen-global' ),
		1,
		true
	);
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
		<img src="<?php echo esc_url( get_stylesheet_directory_uri() ); ?>/images/wp15-logo.svg" class="custom-logo" alt="<?php esc_html_e( 'WP15 home', 'wp15' ); ?>" itemprop="logo" />
	</a>

	<?php

	return ob_get_clean();
};

/**
 * Internationalize the menu item titles.
 *
 * @param string $title
 *
 * @return string
 */
function internationalize_titles( $title ) {
	switch ( $title ) {
		case 'WP15':
			// translators: The title of the wp15.wordpress.net website.
			$title = esc_html__( 'WP15', 'wp15' );
			break;

		case 'WordPress turns 15 on May 27, 2018':
			// translators: The tagline for the wp15.wordpress.net website.
			$title = esc_html__( 'WordPress turns 15 on May 27, 2018', 'wp15' );
			break;

		case 'About':
			// translators: The name of the page that describes the WP15 celebrations.
			$title = esc_html__( 'About', 'wp15' );
			break;

		case 'Live':
			// translators: The name of the page that displays the #wp15 social media posts in real time.
			$title = esc_html_x( 'Live', 'adjective', 'wp15' );
			break;

		case 'Swag':
			// translators: "Swag" is a term for promotional items. This is the title of the page.
			$title = esc_html__( 'Swag', 'wp15' );
			break;
	}

	return $title;
}

/**
 * Internationalize the document's `<title>` element.
 *
 * @param array $title_parts
 *
 * @return array
 */
function internationalize_document_titles( $title_parts ) {
	$title_parts['title'] = internationalize_titles( $title_parts['title'] );

	if ( isset( $title_parts['site'] ) ) {
		$title_parts['site'] = internationalize_titles( $title_parts['site'] );
	}

	if ( isset( $title_parts['tagline'] ) ) {
		$title_parts['tagline'] = internationalize_titles( $title_parts['tagline'] );
	}

	return $title_parts;
}

/**
 * Internationalize the menu item titles.
 *
 * @param array $items
 *
 * @return array
 */
function internationalize_menu_items( $items ) {
	foreach ( $items as $item ) {
		$item->post_title = internationalize_titles( $item->post_title );
	}

	return $items;
}

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

/**
 * Output social media-related meta tags for the document header.
 */
function render_social_meta_tags() {
	?>
	<meta property="og:type" content="website" />
	<meta property="og:title" content="<?php echo wp_get_document_title(); ?>" />
	<meta property="og:description" content="<?php echo internationalize_titles( 'WordPress turns 15 on May 27, 2018' ); ?>" />
	<meta property="og:url" content="https://wp15.wordpress.net/" />
	<meta property="og:site_name" content="<?php echo internationalize_titles( 'WP15' ); ?>" />
	<meta property="og:image" content="https://wp15.wordpress.net/content/uploads/2018/03/wp15-logo-square.png" />
	<meta property="og:locale" content="<?php echo get_locale(); ?>" />
	<meta name="twitter:card" content="summary" />
	<meta name="twitter:url" content="https://wp15.wordpress.net/" />
	<meta name="twitter:title" content="<?php echo wp_get_document_title(); ?>" />
	<meta name="twitter:description" content="<?php echo internationalize_titles( 'WordPress turns 15 on May 27, 2018' ); ?>" />
	<meta name="twitter:image" content="https://wp15.wordpress.net/content/uploads/2018/03/wp15-logo-square.png" />
	<?php
}
