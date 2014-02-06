<?php

class WordCamp_Coming_Soon_Page {
	protected $override_theme_template;
	const VERSION = '0.1';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init',               array( $this, 'init' ), 11 );                               // after WCCSP_Settings::init()
		add_action( 'wp_enqueue_scripts', array( $this, 'manage_plugin_theme_stylesheets' ), 99 );    // (hopefully) after all plugins/themes have enqueued their styles
		add_action( 'wp_head',            array( $this, 'render_dynamic_styles' ) );
		add_filter( 'template_include',   array( $this, 'override_theme_template' ) );
	}

	/**
	 * Initialize variables
	 */
	public function init() {
		$settings                      = $GLOBALS['WCCSP_Settings']->get_settings();
		$this->override_theme_template = 'on' == $settings['enabled'] && ! is_user_logged_in();
	}

	/**
	 * Ensure the template has a consistent base of CSS rules, regardless of the current theme or Custom CSS
	 * Dequeue irrelevant stylesheets and use TwentyThirteen as the base style
	 */
	public function manage_plugin_theme_stylesheets() {
		// todo maybe need to exempt jetpack styles also - $exempt_stylesheets = array(  );
		if ( $this->override_theme_template ) {
			foreach( $GLOBALS['wp_styles']->queue as $stylesheet ) {
				// todo removing fonts that we want - wp_dequeue_style( $stylesheet );
			}
		}

		$twenty_thirteen_stylesheet = '/twentythirteen/style.css';
		foreach( $GLOBALS['wp_theme_directories'] as $directory ) {
			if ( is_file( $directory . $twenty_thirteen_stylesheet ) ) {
				wp_register_style( 'twentythirteen-style-css', $directory . $twenty_thirteen_stylesheet );
				wp_register_style( 'twentythirteen-fonts-css', '//fonts.googleapis.com/css?family=Source+Sans+Pro%3A300%2C400%2C700%2C300italic%2C400italic%2C700italic%7CBitter%3A400%2C700&#038;subset=latin%2Clatin-ext' );
				
				// todo still isn't consistent between local and remote sandboxes
			}
		}
		
		wp_register_style(
			'wccsp-template',
			plugins_url( '/css/template-coming-soon.css', __DIR__ ),
			array( 'twentythirteen-style-css', 'twentythirteen-fonts-css' ),
			self::VERSION
		);
		
		wp_enqueue_style( 'wccsp-template' );
	}

	/**
	 * Render dynamic CSS styles
	 */
	public function render_dynamic_styles() {
		if ( ! $this->override_theme_template ) {
			return;
		}
		
		$settings = $GLOBALS['WCCSP_Settings']->get_settings();
		?>
		
		<style type="text/css">
			html, body {
				background-color: <?php echo esc_html( $settings['body_background_color'] ); ?>;
				color: <?php echo esc_html( $settings['text_color'] ); ?>;
			}

			#wccsp-container {
				background-color: <?php echo esc_html( $settings['container_background_color'] ); ?>;
			}
		</style>

		<?php
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
	 * Collect all of the variables the Coming Soon template will need
	 * Doing this here keeps the template less cluttered and more of a pure view
	 * 
	 * @return array
	 */
	function get_template_variables() {
		$variables = array(
			'image_url'              => $this->get_image_url(),
			'dates'                  => $this->get_dates(),
			'active_modules'         => Jetpack::$instance->get_active_modules(),
			'contact_form_shortcode' => $this->get_contact_form_shortcode(),
		);

		return $variables;
	}

	/**
	 * Retrieve the URL of the image displayed in the template
	 * 
	 * @return string|false
	 */
	public function get_image_url() {
		$settings = $GLOBALS['WCCSP_Settings']->get_settings();
		$image    = wp_get_attachment_image_src( $settings['image_id'], 'full' );
		
		return $image ? $image[0] : false;
	}

	/**
	 * Retrieve the dates of the WordCamp
	 * 
	 * @return string|false
	 */
	public function get_dates() {
		$dates = false;
		
		// todo - switch to blog, lookup based on url or blog id?
		
		return $dates;
	}

	/**
	 * Generate the contact form shortcode string 
	 * 
	 * @return string
	 */
	public function get_contact_form_shortcode() {
		$shortcode = sprintf(
			"[contact-form to='%s' subject='%s contact request']
				[contact-field label='Name' type='name' required='1' /]
				[contact-field label='Email' type='email' required='1' /]
				[contact-field label='Comment' type='textarea' required='1' /]
			[/contact-form]",
			get_bloginfo( 'name' ),
			get_option( 'admin_email' )
		);
		
		return $shortcode;
	}
} // end WordCamp_Coming_Soon_Page
