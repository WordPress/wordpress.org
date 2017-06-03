<?php
/**
 * Sample implementation of the Custom Header feature
 *
 * You can add an optional custom header image to header.php with <?php the_header_image_tag(); ?>
 *
 * @link https://developer.wordpress.org/themes/functionality/custom-headers/
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

add_action( 'after_setup_theme',     __NAMESPACE__ . '\custom_header_setup' );
add_filter( 'header_video_settings', __NAMESPACE__ . '\video_controls'      );

/**
 * Set up the WordPress core custom header feature.
 *
 * @uses header_style()
 */
function custom_header_setup() {
	add_theme_support(
		'custom-header',
		apply_filters( __NAMESPACE__ . '\custom_header_args', array(
			'default-image'      => '',
			'default-text-color' => '000000',
			'width'              => 1000,
			'height'             => 250,
			'flex-height'        => true,
			'video'              => true,
			'wp-head-callback'   => __NAMESPACE__ . '\header_style',
		) )
	);
}

/**
 * Styles the header image and text displayed on the blog.
 *
 * @see campsite_2017_custom_header_setup().
 */
function header_style() {
	$header_text_color = get_header_textcolor();

	/*
	 * If no custom options for text are set, let's bail.
	 * get_header_textcolor() options: Any hex value, 'blank' to hide text.
	 * Default: add_theme_support( 'custom-header' ).
	 */
	if ( get_theme_support( 'custom-header', 'default-text-color' ) === $header_text_color ) {
		return;
	}

	?>

	<style type="text/css">
		<?php if ( ! display_header_text() ) : ?>
			.site-title,
			.site-description {
				position: absolute;
				clip: rect( 1px, 1px, 1px, 1px );
			}

		<?php else : // If the user has set a custom color for the text, use that. ?>
			.site-title a,
			.site-description {
				color: #<?php echo esc_attr( $header_text_color ); ?>;
			}

		<?php endif; ?>
	</style>

	<?php
}

/**
 * Customize video play/pause button in the custom header.
 *
 * @param array $settings Video settings.
 *
 * @return array
 */
function video_controls( $settings ) {
	$settings['l10n']['play'] = sprintf( '
		<span class="screen-reader-text">' .
		__( 'Play background video', 'wordcamporg' ) . '
		</span> %s',
		get_svg( array( 'icon' => 'play' ) )
	);

	$settings['l10n']['pause'] = sprintf( '
		<span class="screen-reader-text">' . __( 'Pause background video', 'wordcamporg' ) . '</span> %s',
		get_svg( array( 'icon' => 'pause' ) )
	);

	return $settings;
}
