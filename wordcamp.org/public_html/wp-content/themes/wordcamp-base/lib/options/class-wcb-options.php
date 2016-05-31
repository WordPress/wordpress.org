<?php

class WCB_Options extends WCB_Loader {
	var $options;

	function includes() {
		$option_slugs = array('option', 'array-option', 'radio-option', 'grid-option', 'button-option', 'typekit-option');
		foreach ( $option_slugs as $slug ) {
			require_once "class-wcb-$slug.php";
		}
	}

	function hooks() {
		add_action('admin_init', array( &$this, 'admin_init' ) );
		add_action('admin_menu', array( &$this, 'admin_menu' ) );
	}

	function loaded() {
		$this->options['grid'] = new WCB_Radio_Option( array(
			'key'       => 'grid',
			'default'   => 'grid960',
			'label'     => __('Grid Width', 'wordcamporg'),
			'values'   => array(
				'grid960'   => __( '960px wide', 'wordcamporg' ),
				'grid720'   => __( '720px wide', 'wordcamporg' ),
			)
		) );

		$this->options['after_header'] = new WCB_Grid_Option( array(
			'key'       => 'after_header',
			'default'   => array(
				'visible'       => true,
				'layout'        => array( 12 ),
				'front_only'    => true,
			),
			'label'     => __('After Header', 'wordcamporg'),
		) );

		$this->options['before_content'] = new WCB_Grid_Option( array(
			'key'       => 'before_content',
			'default'   => array(
				'visible'       => false,
				'layout'        => array( 4,4,4 ),
				'front_only'    => true,
			),
			'label'     => __('Before Content', 'wordcamporg'),
		) );

		$this->options['content'] = new WCB_Grid_Option( array(
			'key'       => 'content',
			'default'   => array(
				'visible'       => true,
				'layout'        => array(
					array( 9, 'content' ),
					array( 3, 'sidebar' ),
				),
				'front_only'    => false,
			),
			'label'     => __('Content', 'wordcamporg'),
			'type'      => 'content',
		) );

		$this->options['after_content'] = new WCB_Grid_Option( array(
			'key'       => 'after_content',
			'default'   => array(
				'visible'       => false,
				'layout'        => array( 4,4,4 ),
				'front_only'    => false,
			),
			'label'     => __('After Content', 'wordcamporg'),
		) );

		$this->options['before_footer'] = new WCB_Grid_Option( array(
			'key'       => 'before_footer',
			'default'   => array(
				'visible'       => true,
				'layout'        => array( 3,3,3,3 ),
				'front_only'    => false,
			),
			'label'     => __('Before Footer', 'wordcamporg'),
		) );

		$this->options['featured_button'] = new WCB_Button_Option( array(
			'key'       => 'featured_button',
			'default'   => array(
				'visible'       => false,
				'text'          => __('Register now!', 'wordcamporg'),
				'url'           => '',
			),
		) );

		$this->options['typekit'] = new WCB_Typekit_Option( array(
			'key'       => 'typekit',
			'default'   => 'jnd4dds',
			'label'     => __('Typekit', 'wordcamporg'),
			'values'    => array(
				'default'   => __( 'Use the default Typekit fonts.', 'wordcamporg' ),
				'custom'    => __( 'Use a custom Typekit key:', 'wordcamporg' ),
				'off'       => __( 'Do not use any Typekit fonts.', 'wordcamporg' ),
			),
		) );
	}

	function get( $name ) {
		if ( ! isset( $this->options[ $name ] ) )
			return;
		$values = get_option( 'wcb_theme_options' );
		$option = $this->options[ $name ];

		return isset( $values[ $option->key ] ) ? $option->maybe_unserialize( $values[ $option->key ] ) : $option->default;
	}

	function admin_menu() {
		$page = add_theme_page( __('Theme Options', 'wordcamporg'), __('Theme Options', 'wordcamporg'), 'edit_theme_options', 'wcb-theme-options', array( &$this, 'render' ) );

		add_action("wcb_enqueue_scripts_$page", array( &$this, 'enqueue_scripts' ) );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'wcb-options', wcb_dev_url( WCB_LIB_URL . '/options/js/options.js' ), array('jquery'), '20110212' );
		wp_enqueue_style( 'wcb-options-grid', wcb_dev_url( WCB_LIB_URL . '/options/css/options-grid.css' ), array(), '20110212' );
		wp_enqueue_style( 'wcb-options', wcb_dev_url( WCB_LIB_URL . '/options/css/options.css' ), array('wcb-options-grid'), '20110212' );
	}

	function admin_init() {
		register_setting( 'wcb_theme_options', 'wcb_theme_options', array( &$this, 'validate' ) );
	}

	function validate( $input ) {
		foreach ( $this->options as $option ) {
			$input = $option->maybe_validate( $input );
		}

		return $input;
	}

	function render() {
		if ( ! isset( $_REQUEST['updated'] ) )
			$_REQUEST['updated'] = false;

		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h1><?php echo get_current_theme() . ' ' . __( 'Theme Options', 'wordcamporg' ); ?></h1>

			<?php if ( false !== $_REQUEST['updated'] ) : ?>
				<div class="updated fade"><p><strong><?php _e( 'Options saved', 'wordcamporg' ); ?></strong></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcb_theme_options' ); ?>
				<h3><?php _e('General Options', 'wordcamporg'); ?></h3>
				<table class="form-table">
					<?php
					$this->options['grid']->render();
					$this->options['featured_button']->render();
					$this->options['typekit']->render();
					?>
				</table>

				<h3><?php _e('Theme Layout', 'wordcamporg'); ?></h3>
				<table class="form-table">
					<?php
					$rows = array( 'after_header', 'before_content', 'content', 'after_content', 'before_footer' ); ?>

					<tr id="visibility-row">
						<th><?php esc_html_e( 'Show Rows', 'wordcamporg' ); ?></th>
						<td>
							<?php foreach ( $rows as $row ) {
								$this->options[ $row ]->render_visibility();
							} ?>
						</td>
					</tr>

					<tr>
						<th><?php esc_html_e( 'Row Layout', 'wordcamporg' ); ?></th>
						<td>
							<?php foreach ( $rows as $row ) {
								$this->options[ $row ]->render_layout();
							} ?>
						</td>
					</tr>

				</table>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Options', 'wordcamporg' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}
}

function wcb_get_option( $name ) {
	$options = wcb_get('options');
	$option = $options->get( $name );
	$option = apply_filters('wcb_get_option', $option, $name );
	return $option;
}

?>