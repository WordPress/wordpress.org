<?php

namespace WordCamp\Site_Cloner;
defined( 'WPINC' ) or die();

use \WordCamp\RemoteCSS;

/**
 * Customizer Setting for source site ID
 *
 * This isn't an actual setting that gets stored in the database, it's just a temporary value to track the ID of
 * the site that that will be cloned. There are actually many data points that we'll import, but they don't fit
 * into the default model that the Customizer expects.
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
		add_filter( 'get_post_metadata',       array( $this, 'preview_jetpack_postmeta' ), 10, 4 );
		add_filter( 'safecss_skip_stylesheet', array( $this, 'preview_skip_stylesheet'  ) );

		// Disable the current site's Custom CSS from being output
		remove_action( 'wp_head', array( 'Jetpack_Custom_CSS', 'link_tag' ), 101 );
	}

	/**
	 * Print the source site's custom CSS in an inline style block
	 *
	 * It can't be easily enqueued as an external stylesheet because Jetpack_Custom_CSS::link_tag() returns early
	 * in the Customizer if the theme being previewed is different from the live theme.
	 */
	public function preview_source_site_css() {
		if ( method_exists( '\Jetpack', 'get_module_path' ) ) {
			require_once( \Jetpack::get_module_path( 'custom-css' ) );
		} else {
			return;
		}

		switch_to_blog( $this->preview_source_site_id );

		printf(
			'<style id="wcsc-source-site-custom-css">%s %s</style>',
			$this->get_cached_remote_css(),
			\Jetpack_Custom_CSS::get_css( true )
		);

		restore_current_blog();
	}

	/**
	 * Overwrite the previewing site's Custom CSS settings with values from the source site
	 *
	 * @param mixed  $pre_filter_value
	 * @param int    $post_id
	 * @param string $meta_key
	 * @param bool   $return_single_value
	 *
	 * @return mixed
	 */
	public function preview_jetpack_postmeta( $pre_filter_value, $post_id, $meta_key, $return_single_value ) {
		if ( ! in_array( $meta_key, array( 'content_width' ) ) ) {
			return $pre_filter_value;
		}

		remove_filter( 'get_post_metadata', array( $this, 'preview_jetpack_postmeta' ), 10, 4 );    // avoid infinite recursion
		switch_to_blog( $this->preview_source_site_id );

		if ( $source_cite_css_post = \Jetpack_Custom_CSS::get_current_revision() ) {
			$pre_filter_value = get_post_meta( $source_cite_css_post['ID'], $meta_key, $return_single_value );
		}

		restore_current_blog();
		add_filter( 'get_post_metadata', array( $this, 'preview_jetpack_postmeta' ), 10, 4 );

		return $pre_filter_value;
	}

	/**
	 * Determine whether or not to skip the primary stylesheet
	 *
	 * When the Custom CSS value for `Mode` is `Replacement`, Jetpack will prevent the primary stylesheet from
	 * being enqueued. We need to trigger that behavior based on the value on the source site, rather than the
	 * current site.
	 *
	 * This could be actually handled along with other Jetpack options in preview_jetpack_postmeta(), if it
	 * weren't for the fact that Jetpack_Custom_CSS::skip_stylesheet always returns false in the Previewer.
	 *
	 * @param bool $skip
	 *
	 * @return bool
	 */
	public function preview_skip_stylesheet( $skip ) {
		switch_to_blog( $this->preview_source_site_id );

		if ( $source_cite_css_post = \Jetpack_Custom_CSS::get_current_revision() ) {
			$skip = 'no' === get_post_meta( $source_cite_css_post['ID'], 'custom_css_add', true );
		}

		restore_current_blog();

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
				$post->post_content_filtered
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

		if ( ! $source_cite_css_post = \Jetpack_Custom_CSS::get_current_revision() ) {
			restore_current_blog();
			return;
		}

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

		// Add Jetpack Custom CSS second
		if ( $custom_css = \Jetpack_Custom_CSS::get_css( false ) ) {
			$source_site_css .= sprintf(
				"/* %s */\n%s\n\n",
				sprintf(
					esc_html__( 'Custom CSS from %s', 'wordcamporg' ),
					get_bloginfo( 'name' )
				),
				$custom_css
			);
		}

		$source_site_preprocessor  = get_post_meta( $source_cite_css_post['ID'], 'custom_css_preprocessor', true );
		$source_site_mode          = get_post_meta( $source_cite_css_post['ID'], 'custom_css_add', true );
		$source_site_content_width = get_post_meta( $source_cite_css_post['ID'], 'content_width', true );

		restore_current_blog();

		\Jetpack_Custom_CSS::save( array(
			'css'             => $source_site_css,
			'is_preview'      => false,
			'preprocessor'    => $source_site_preprocessor,
			'add_to_existing' => 'yes' === $source_site_mode ? true : false,
			'content_width'   => $source_site_content_width,
		) );
	}
}
