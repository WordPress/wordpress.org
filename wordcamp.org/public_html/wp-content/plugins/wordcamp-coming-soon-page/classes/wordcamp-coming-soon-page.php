<?php

class WordCamp_Coming_Soon_Page {
	protected $override_theme_template;
	const VERSION = '0.2';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',               array( $this, 'init' ), 11 );                               // after WCCSP_Settings::init()
		add_action( 'wp_enqueue_scripts', array( $this, 'manage_plugin_theme_stylesheets' ), 99 );    // (hopefully) after all plugins/themes have enqueued their styles
		add_action( 'wp_head',            array( $this, 'render_dynamic_styles' ) );
		add_filter( 'template_include',   array( $this, 'override_theme_template' ) );
		add_action( 'template_redirect',  array( $this, 'disable_jetpacks_open_graph' ) );
		add_action( 'admin_bar_menu',     array( $this, 'admin_bar_menu_item' ), 1000 );
		add_action( 'admin_head',         array( $this, 'admin_bar_styling' ) );
		add_action( 'wp_head',            array( $this, 'admin_bar_styling' ) );
		add_action( 'admin_notices',      array( $this, 'block_new_post_admin_notice' ) );
		add_filter( 'get_post_metadata',  array( $this, 'jetpack_dont_email_post_to_subs' ), 10, 4 );

		add_image_size( 'wccsp_image_medium_rectangle', 500, 300 );
	}

	/**
	 * Initialize variables
	 */
	public function init() {
		$settings                      = $GLOBALS['WCCSP_Settings']->get_settings();
		$show_page                     = 'on' == $settings['enabled'] && ! current_user_can( 'edit_posts' );
		$this->override_theme_template = $show_page || $this->is_coming_soon_preview();
	}

	/**
	 * Check if the current page is our section of the Previewer
	 *
	 * @return bool
	 */
	public function is_coming_soon_preview() {
		global $wp_customize;

		return isset( $_GET['wccsp-preview'] ) && $wp_customize->is_preview();
	}

	/**
	 * Ensure the template has a consistent base of CSS rules, regardless of the current theme or Custom CSS
	 */
	public function manage_plugin_theme_stylesheets() {
		if ( ! $this->override_theme_template ) {
			return;
		}

		$this->dequeue_all_stylesheets();

		if ( is_user_logged_in() ) {
			wp_enqueue_style( 'admin-bar' );
		}

		wp_register_style(
			'open-sans',
			'https://fonts.googleapis.com/css?family=Open+Sans:300italic,400italic,600italic,300,400,600',
			array(),
			null
		);

		wp_enqueue_style(
			'wccsp-template',
			plugins_url( '/css/template-coming-soon.css', __DIR__ ),
			array( 'open-sans' ),
			2
		);
	}

	/**
	 * Dequeue all enqueued stylesheets and Custom CSS
	 */
	protected function dequeue_all_stylesheets() {
		foreach( $GLOBALS['wp_styles']->queue as $stylesheet ) {
			wp_dequeue_style( $stylesheet );
		}

		remove_action( 'wp_head', 'wp_custom_css_cb', 101 );
	}

	/**
	 * Render dynamic CSS styles
	 */
	public function render_dynamic_styles() {
		if ( ! $this->override_theme_template ) {
			return;
		}

		extract( $GLOBALS['WordCamp_Coming_Soon_Page']->get_template_variables() );

		require_once( dirname( __DIR__ ) . '/css/template-coming-soon-dynamic.php' );
	}

	/**
	 * Load the Coming Soon template instead of a theme template
	 *
	 * @param string $template
	 * @return string
	 */
	public function override_theme_template( $template ) {
		if ( $this->override_theme_template ) {
			$template = dirname( __DIR__ ) . '/views/template-coming-soon.php';
		}

		return $template;
	}

	/**
	 * Disable Jetpack's Open Graph meta tags when the Coming Soon page is active
	 */
	public function disable_jetpacks_open_graph() {
		if ( $this->override_theme_template ) {
			add_filter( 'jetpack_enable_open_graph', '__return_false' );
		}
	}

	/**
	 * Collect all of the variables the Coming Soon template will need
	 * Doing this here keeps the template less cluttered and more of a pure view
	 *
	 * @return array
	 */
	function get_template_variables() {
		$variables = array(
			'image_url'              => $this->get_image_url(),
			'background_url'         => $this->get_bg_image_url(),
			'dates'                  => $this->get_dates(),
			'active_modules'         => Jetpack::$instance->get_active_modules(),
			'contact_form_shortcode' => $this->get_contact_form_shortcode(),
			'colors'                 => $this->get_colors(),
			'introduction'           => $this->get_introduction(),
		);

		return $variables;
	}

	/**
	 * Retrieve the colors for the template
	 *
	 * @return array
	 */
	public function get_colors() {
		$settings = $GLOBALS['WCCSP_Settings']->get_settings();

		if ( ! class_exists( 'Jetpack_Color' ) && function_exists( 'jetpack_require_lib' ) ) {
			jetpack_require_lib( 'class.color' );
		}

		// If they never changed from the old default background color, then use the new default
		$background = $settings['body_background_color'];
		if ( '#666666' === $background ) {
			$background = '#0073aa';
		}

		// Just in case we can't find Jetpack_Color
		if ( class_exists( 'Jetpack_Color' ) ) {
			$color     = new Jetpack_Color( $background, 'hex' );
			$color_hsl = $color->toHsl();

			$lighter_color = new Jetpack_Color( array(
				$color_hsl['h'],
				$color_hsl['s'],
				( $color_hsl['l'] >= 85 ) ? 100 : $color_hsl['l'] + 15
			), 'hsl' );

			$darker_color = new Jetpack_Color( array(
				$color_hsl['h'],
				$color_hsl['s'],
				( $color_hsl['l'] < 10 ) ? 0 : $color_hsl['l'] - 10
			), 'hsl' );

			$background_lighter = '#' . $lighter_color->toHex();
			$background_darker  = '#' . $darker_color->toHex();
		} else {
			$background_lighter = $background;
			$background_darker  = $background;
		}

		$colors['main']    = $background;
		$colors['lighter'] = $background_lighter;
		$colors['darker']  = $background_darker;

		// Not currently customizable
		$colors['text']       = '#32373c';
		$colors['light-text'] = '#b4b9be';
		$colors['border']     = '#00669b';

		return $colors;
	}

	/**
	 * Retrieve the URL of the logo image displayed in the template
	 *
	 * @return string|false
	 */
	public function get_image_url() {
		$settings   = $GLOBALS['WCCSP_Settings']->get_settings();
		$image_meta = wp_get_attachment_metadata( $settings['image_id'] );
		$size       = isset( $image_meta['sizes']['wccsp_image_medium_rectangle'] ) ? 'wccsp_image_medium_rectangle' : 'full';
		$image      = wp_get_attachment_image_src( $settings['image_id'], $size );

		return $image ? $image[0] : false;
	}

	/**
	 * Retrieve the URL of the background image displayed in the template
	 *
	 * @return string|false
	 */
	public function get_bg_image_url() {
		$settings   = $GLOBALS['WCCSP_Settings']->get_settings();
		$image_meta = wp_get_attachment_metadata(  $settings['background_id']         );
		$image      = wp_get_attachment_image_src( $settings['background_id'], 'full' );

		return empty( $image[0] ) ? false : $image[0];
	}

	/**
	 * Retrieve the dates of the WordCamp
	 *
	 * @return string|false
	 */
	public function get_dates() {
		$dates = false;
		$wordcamp_post = get_wordcamp_post();

		if ( isset( $wordcamp_post->ID ) ) {
			if ( ! empty( $wordcamp_post->meta['Start Date (YYYY-mm-dd)'][0] ) ) {
				// translators: date format, see https://php.net/date
				$dates = date_i18n( __( 'F jS Y' , 'wordcamporg' ), $wordcamp_post->meta['Start Date (YYYY-mm-dd)'][0] );

				if ( ! empty( $wordcamp_post->meta['End Date (YYYY-mm-dd)'][0] ) ) {
					if ( $wordcamp_post->meta['Start Date (YYYY-mm-dd)'][0] !== $wordcamp_post->meta['End Date (YYYY-mm-dd)'][0] ) {
						// translators: date format, see https://php.net/date
						$dates .= ' - ' . date_i18n( __( 'F jS Y' , 'wordcamporg' ), $wordcamp_post->meta['End Date (YYYY-mm-dd)'][0] );
					}
				}
			}
		}

		return $dates;
	}

	/**
	 * Retrieve the contact form shortcode string
	 *
	 * We can't just create an arbitrary shortcode because of https://github.com/Automattic/jetpack/issues/102. Instead we have to use a form that's tied to a page.
	 * This is somewhat fragile, though. It should work in most cases because the first $page that contains [contact-form] will be the one we automatically create
	 * when the site is created, but if the organizers delete that and then add multiple forms, the wrong form could be displayed. The alternative approaches also
	 * have problems, though, and #102 should be fixed relatively soon, so hopefully this will be good enough until it can be refactored.
	 * todo Refactor this once #102-jetpack is fixed.
	 *
	 * @return string|false
	 */
	public function get_contact_form_shortcode() {
		$contact_form_shortcode = false;
		$shortcode_regex        = get_shortcode_regex();

		$all_pages = get_posts( array(
			'post_type'      => 'page',
			'posts_per_page' => -1,
		) );

		foreach ( $all_pages as $page ) {
			preg_match_all( '/' . $shortcode_regex . '/s', $page->post_content, $matches, PREG_SET_ORDER );

			foreach ( $matches as $shortcode ) {
				if ( 'contact-form' === $shortcode[2] ) {
					global $post;
					$post = $page;
					setup_postdata( $post );

					ob_start();
					echo do_shortcode( $shortcode[0] );
					$contact_form_shortcode = ob_get_clean();

					wp_reset_postdata();
					break;
				}
			}
		}

		return $contact_form_shortcode;
	}

	/**
	 * Retrieve the optional introduction overwriting the default string.
	 *
	 * @return string
	 */
	public function get_introduction() {
		$settings     = $GLOBALS['WCCSP_Settings']->get_settings();

		return $settings['introduction'];
	}

	/**
	 * Display notice in admin bar when Coming Soon mode is on.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference
	 */
	function admin_bar_menu_item( $wp_admin_bar ) {
		$settings = $GLOBALS['WCCSP_Settings']->get_settings();
		if ( $settings['enabled'] !== 'on' ) {
			return;
		}

		$menu_slug   = add_query_arg(
			array(
				'autofocus[section]' => 'wccsp_live_preview',
				'url'                => rawurlencode( add_query_arg( 'wccsp-preview', '', site_url() ) ),
			),
			'/customize.php'
		);
		$setting_url = admin_url( $menu_slug );

		if ( ! current_user_can( 'manage_options' ) ) {
			$setting_url = '';
		}

		$wp_admin_bar->add_node( array(
			'id'     => 'wordcamp-coming-soon-info',
			'href'   => $setting_url,
			'parent' => 'root-default',
			'title'  => __( 'Coming Soon Mode ON', 'wordcamporg' ),
			'meta'   => array( 'class' => 'wc-coming-soon-info' ),
		) );
	}

	/**
	 * Styles for the Coming Soon flag in the Admin bar.
	 */
	public function admin_bar_styling() {
		$settings = $GLOBALS['WCCSP_Settings']->get_settings();
		if ( $settings['enabled'] !== 'on' ) {
			return;
		}

		echo '<style type="text/css">#wpadminbar .wc-coming-soon-info, #wpadminbar .wc-coming-soon-info a { background: #FFE399; color: #23282d }</style>';
	}

	/*
	 * Show a notice if Coming Soon is enabled.
	 *
	 * Explain to users why publishing is disabled when Coming Soon is enabled.
	 */
	public function block_new_post_admin_notice() {
		$settings = $GLOBALS['WCCSP_Settings']->get_settings();
		if ( $settings['enabled'] !== 'on' ) {
			return;
		}

		$menu_slug   = add_query_arg(
			array(
				'autofocus[section]' => 'wccsp_live_preview',
				'url'                => rawurlencode( add_query_arg( 'wccsp-preview', '', site_url() ) ),
			),
			'/customize.php'
		);
		$setting_url = admin_url( $menu_slug );

		$screen = get_current_screen();
		if ( trim( $screen->id ) == 'post' ) {
			$class = 'notice notice-warning';
			$message = sprintf(
				__( '<a href="%s">Coming Soon mode</a> is enabled. Site subscribers will not receive email notifications about published posts.', 'wordcamporg' ),
				esc_url( $setting_url )
			);

			if ( ! current_user_can( 'manage_options' ) ) {
				$message = wp_strip_all_tags( $message );
			}

			printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
		}
	}

	/**
	 * Disable sending of Jetpack emails when Coming Soon mode is on.
	 *
	 * @param null|array|string $value     The value get_metadata() should return - a single metadata value,
	 *                                     or an array of values.
	 * @param int               $object_id Object ID.
	 * @param string            $meta_key  Meta key.
	 * @param bool              $single    Whether to return only the first value of the specified $meta_key.
	 */
	function jetpack_dont_email_post_to_subs( $value, $object_id, $meta_key, $single ) {
		if ( '_jetpack_dont_email_post_to_subs' === $meta_key ) {
			$settings = $GLOBALS['WCCSP_Settings']->get_settings();

			if ( $settings['enabled'] === 'on' ) {
				return true;
			}
		}

		return $value;
	}

} // end WordCamp_Coming_Soon_Page
