<?php

namespace WordCamp\Site_Cloner;
use \WordCamp\RemoteCSS;
use Jetpack_Custom_CSS_Enhancements;

defined( 'WPINC' ) or die();

/**
 * Customizer Setting for source site ID
 *
 * This isn't an actual setting that gets stored in the database, it's just a temporary value to track the ID of
 * the site that that will be cloned. There are actually many data points that we'll import, but they don't fit
 * into the default model that the Customizer expects.
 *
 * @todo - We may no longer need a setting; see https://make.wordpress.org/core/2016/03/10/customizer-improvements-in-4-5/
 */
class Source_Site_ID_Setting extends \WP_Customize_Setting {
	public $type              = 'wcsc-source-site-id';
	public $default           = 0;
	public $sanitize_callback = 'absint';

	protected $preview_source_site_id;

	/**
	 * Preview another site before it's imported
	 */
	public function preview() {
		if ( ! $this->preview_source_site_id = $this->manager->post_value( $this ) ) {
			return;
		}

		add_action( 'wp_head',                 array( $this, 'preview_source_site_css'  ), 99 );   // wp_print_styles is too early; the theme's stylesheet would get enqueued later and take precedence
		add_filter( 'safecss_skip_stylesheet', array( $this, 'preview_skip_stylesheet'  ), 15 );   // After Jetpack_Custom_CSS_Enhancements::preview_skip_stylesheet()

		// Disable the current site's Custom CSS from being output
		remove_action( 'wp_head', 'wp_custom_css_cb', 11 ); // todo compat with WP 4.7. this line can be removed when r39616 is merged to the 4.7 branch
		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
	}

	/**
	 * Print the source site's custom CSS in an inline style block
	 */
	public function preview_source_site_css() {
		switch_to_blog( $this->preview_source_site_id );

		printf(
			'<style id="wcsc-source-site-custom-css">%s %s</style>',
			$this->get_cached_remote_css(),
			wp_get_custom_css()
		);

		restore_current_blog();
	}

	/**
	 * Determine whether or not to skip the primary stylesheet
	 *
	 * When the `Start Fresh` theme mod is set, Jetpack will prevent the primary stylesheet from
	 * being enqueued. We need to trigger that behavior based on the value on the source site, rather than the
	 * current site.
	 *
	 * @param bool $skip
	 *
	 * @return bool
	 */
	public function preview_skip_stylesheet( $skip ) {
		remove_filter( 'safecss_skip_stylesheet', array( $this, 'preview_skip_stylesheet'  ), 15 ); // Avoid infinite recursion. Also, use same priority as originally set in preview()
		switch_to_blog( $this->preview_source_site_id );

		$skip = Jetpack_Custom_CSS_Enhancements::skip_stylesheet();

		restore_current_blog();
		add_filter( 'safecss_skip_stylesheet', array( $this, 'preview_skip_stylesheet'  ), 15 );

		return $skip;
	}

	/**
	 * Get the cached remote CSS for the current blog.
	 *
	 * @return string
	 */
	public function get_cached_remote_css() {
		$remote_css = '';

		if ( ! function_exists( '\WordCamp\RemoteCSS\get_safe_css_post' ) ) {
			return $remote_css;
		}

		$post = RemoteCSS\get_safe_css_post();

		if ( $post instanceof \WP_Post ) {
			// Sanitization copied from \Jetpack_Custom_CSS::get_css()
			// so that there is parity with how Jetpack outputs the CSS
			$remote_css = str_replace(
				array( '\\\00BB \\\0020', '\0BB \020', '0BB 020' ),
				'\00BB \0020',
				$post->post_content
			);
		}

		return $remote_css;
	}

	/**
	 * Clone the source site into the current site
	 *
	 * If the theme needs to be switched, Core will do that for us because we added the `?theme=` parameter
	 * to the URL.
	 *
	 * @param int $source_site_id
	 *
	 * @return null
	 */
	protected function update( $source_site_id ) {
		switch_to_blog( $source_site_id );

		$source_site_theme_mods = get_theme_mod( 'jetpack_custom_css' );
		$source_site_css = '';

		// Add Remote CSS first
		// This maintains the correct cascading order of stylesheet rules
		if ( $remote_css = $this->get_cached_remote_css() ) {
			$source_site_css .= sprintf(
				"/* %s */\n%s\n\n",
				sprintf(
					esc_html__( 'Remote CSS from %s', 'wordcamporg' ),
					esc_url( get_option( 'wcrcss_remote_css_url' ) )
				),
				$remote_css
			);
		}

		/*
		 * Add Core Custom CSS second to maintain correct cascading order.
		 *
		 * Processed CSS and vanilla CSS are stored in `post_content`, but pre-processed CSS is stored in
		 * `post_content_filtered`.
		 */
		if ( $custom_css_post = wp_get_custom_css_post() ) {
			if ( empty( $source_site_theme_mods['preprocessor'] ) ) {
				$custom_css = $custom_css_post->post_content;
			} else {
				$custom_css = $custom_css_post->post_content_filtered;
			}

			$source_site_css .= sprintf(
				"/* %s */\n%s\n\n",
				sprintf(
					esc_html__( 'Custom CSS from %s', 'wordcamporg' ),
					parse_url( home_url(), PHP_URL_HOST )
				),
				$custom_css
			);
		}

		restore_current_blog();

		wp_update_custom_css_post( $source_site_css );
		set_theme_mod( 'jetpack_custom_css', $source_site_theme_mods );
	}
}
