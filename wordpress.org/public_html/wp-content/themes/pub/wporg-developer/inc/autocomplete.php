<?php
/**
 * Code Reference autocomplete for the search form.
 *
 * @package wporg-developer
 */

/**
 * Class to handle autocomplete for the search form.
 */
class DevHub_Search_Form_Autocomplete {

	public function __construct() {
		$this->init();
	}

	/**
	 * Initialization
	 *
	 * @access public
	 */
	public function init() {

		add_action( 'wp_ajax_autocomplete', array( $this, 'autocomplete_data_update' ) );
		add_action( 'wp_ajax_nopriv_autocomplete', array( $this, 'autocomplete_data_update' ) );

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'scripts_and_styles' ), 11 );
	}


	/**
	 * Enqueues scripts and styles.
	 *
	 * @access public
	 */
	public function scripts_and_styles() {

		wp_enqueue_style(
			'awesomplete-css',
			get_template_directory_uri() . '/stylesheets/awesomplete.css',
			array(),
			filemtime( dirname( __DIR__ ) . '/stylesheets/awesomplete.css' )
		);
		wp_enqueue_style(
			'autocomplete-css',
			get_template_directory_uri() . '/stylesheets/autocomplete.css',
			array(),
			filemtime( dirname( __DIR__ ) . '/stylesheets/autocomplete.css' )
		);

		wp_register_script(
			'awesomplete',
			get_template_directory_uri() . '/js/awesomplete.min.js',
			array(),
			filemtime( dirname( __DIR__ ) . '/js/awesomplete.min.js' ),
			true
		);
		wp_enqueue_script( 'awesomplete' );

		wp_register_script( 'autocomplete', get_stylesheet_directory_uri() . '/js/autocomplete.js', array( 'awesomplete' ), filemtime( dirname( __DIR__ ) . '/js/autocomplete.js' ), true );
		wp_localize_script(
			'autocomplete',
			'autocomplete',
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'autocomplete_nonce' ),
			)
		);

		wp_enqueue_script( 'autocomplete' );
	}


	/**
	 * Handles AJAX updates for the autocomplete list.
	 *
	 * @access public
	 *
	 * @return string JSON data
	 */
	public function autocomplete_data_update() {

		check_ajax_referer( 'autocomplete_nonce', 'nonce' );

		$parser_post_types = DevHub\get_parsed_post_types();
		$defaults          = array(
			's'         => '',
			'post_type' => $parser_post_types,
			'posts'     => array(),
		);

		if ( ! ( isset( $_POST['data'] ) && $_POST['data'] ) ) {
			wp_send_json_error( $defaults );
		}

		// Parse the search form fields.
		wp_parse_str( $_POST['data'], $form_data );
		$form_data = array_merge( $defaults, $form_data );

		// No search query.
		if ( empty( $form_data['s'] ) ) {
			wp_send_json_error( $defaults );
		}

		foreach ( $form_data['post_type'] as $key => $post_type ) {
			if ( ! in_array( $post_type, $parser_post_types ) ) {
				unset( $form_data['post_type'][ $key ] );
			}
		}

		$post_types = ! empty( $form_data['post_type'] ) ? $form_data['post_type'] : $parser_post_types;

		$args = array(
			'posts_per_page'       => -1,
			'post_type'            => $post_types,
			's'                    => $form_data['s'],
			'orderby'              => '',
			'search_orderby_title' => 1,
			'order'                => 'ASC',
			'_autocomplete_search' => true,
		);

		$search = get_posts( $args );

		if ( ! empty( $search ) ) {
			$post_types_function_like = array( 'wp-parser-function', 'wp-parser-method' );

			foreach ( $search as $post ) {
				$permalink = get_permalink( $post->ID );
				$title     = $post->post_title;

				if ( in_array( $post->post_type, $post_types_function_like ) ) {
					$title .= '()';
				}

				if ( $post->post_type == 'wp-parser-class' ) {
					$title = 'class ' . $title . ' {}';
				}

				$form_data['posts'][ $title ] = $permalink;
			}
		}

		wp_send_json_success( $form_data );
	}

}

$autocomplete = new DevHub_Search_Form_Autocomplete();
