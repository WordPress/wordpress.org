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

		add_image_size( 'wccsp_image_medium_rectangle', 500, 300 );
	}

	/**
	 * Initialize variables
	 */
	public function init() {
		$settings                      = $GLOBALS['WCCSP_Settings']->get_settings();
		$this->override_theme_template = 'on' == $settings['enabled'] && ! current_user_can( 'edit_posts' );
	}

	/**
	 * Ensure the template has a consistent base of CSS rules, regardless of the current theme or Custom CSS
	 * Dequeue irrelevant stylesheets and use TwentyThirteen as the base style
	 */
	public function manage_plugin_theme_stylesheets() {
		if ( ! $this->override_theme_template ) {
			return;
		}
		
		$this->dequeue_all_stylesheets();
		$this->register_twentythirteen_styles();

		wp_enqueue_style(
			'wccsp-template',
			plugins_url( '/css/template-coming-soon.css', __DIR__ ),
			array( 'twentythirteen-fonts', 'genericons', 'twentythirteen-style', 'admin-bar' ),
			self::VERSION
		);
	}

	/**
	 * Dequeue all enqueued stylesheets
	 */
	protected function dequeue_all_stylesheets() {
		foreach( $GLOBALS['wp_styles']->queue as $stylesheet ) { 
			wp_dequeue_style( $stylesheet );
		}
	}

	/**
	 * Register TwentyThirteen's base styles
	 */
	protected function register_twentythirteen_styles() {
		$twentythirteen_uri = get_theme_root_uri( 'twentythirteen' ) . '/twentythirteen'; 
		
		if ( ! wp_style_is( 'twentythirteen-fonts', 'registered' ) ) {
			wp_register_style( 'twentythirteen-fonts', '//fonts.googleapis.com/css?family=Source+Sans+Pro%3A300%2C400%2C700%2C300italic%2C400italic%2C700italic%7CBitter%3A400%2C700&#038;subset=latin%2Clatin-ext', array(), null );
		}

		if ( ! wp_style_is( 'genericons', 'registered' ) ) {
			wp_register_style( 'genericons', $twentythirteen_uri . '/fonts/genericons.css' );
		}

		if ( ! wp_style_is( 'twentythirteen-style', 'registered' ) ) {
			wp_register_style( 'twentythirteen-style', $twentythirteen_uri . '/style.css' );
		}
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
		
		<!-- BEGIN wordcamp-coming-soon-page -->
		<style type="text/css">
			html, body {
				background-color: <?php echo esc_html( $settings['body_background_color'] ); ?>;
				color: <?php echo esc_html( $settings['text_color'] ); ?>;
			}
			
			#wccsp-container,
			.widget  {
				background-color: <?php echo esc_html( $settings['container_background_color'] ); ?>;
			}
		</style>
		<!-- END wordcamp-coming-soon-page -->

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
		$settings   = $GLOBALS['WCCSP_Settings']->get_settings();
		$image_meta = wp_get_attachment_metadata( $settings['image_id'] );
		$size       = isset( $image_meta['sizes']['wccsp_image_medium_rectangle'] ) ? 'wccsp_image_medium_rectangle' : 'full'; 
		$image      = wp_get_attachment_image_src( $settings['image_id'], $size );
		
		return $image ? $image[0] : false;
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
				$dates = date( 'l, F jS Y', $wordcamp_post->meta['Start Date (YYYY-mm-dd)'][0] );

				if ( ! empty( $wordcamp_post->meta['End Date (YYYY-mm-dd)'][0] ) ) {
					$dates .= ' - ' . date( 'l, F jS Y', $wordcamp_post->meta['End Date (YYYY-mm-dd)'][0] );
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
} // end WordCamp_Coming_Soon_Page
