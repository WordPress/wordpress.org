<?php

namespace WP15\Theme;

defined( 'WPINC' ) || die();

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );
add_filter( 'get_custom_logo',    __NAMESPACE__ . '\set_custom_logo' );

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
