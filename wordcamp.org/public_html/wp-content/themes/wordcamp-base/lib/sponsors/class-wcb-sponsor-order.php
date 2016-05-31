<?php

class WCB_Sponsor_Order extends WCB_Loader {

	function hooks() {
		add_action('admin_init',     array( &$this, 'admin_init' )        );
		add_action('admin_menu',     array( &$this, 'admin_menu' )        );

		add_filter('wcb_get_option', array( &$this, 'get_option' ), 10, 2 );
	}

	function admin_menu() {
		$page = add_submenu_page(
			'edit.php?post_type=' . WCB_SPONSOR_POST_TYPE,  // Page type
			__('Order Sponsor Levels', 'wordcamporg'),              // Page title
			__('Order Sponsor Levels', 'wordcamporg'),              // Menu title
			'edit_posts',                                   // Capability
			'sponsor_levels',                               // Menu slug
			array( &$this, 'render' )                       // Callback
		);

		add_action("wcb_enqueue_scripts_$page", array( &$this, 'enqueue_scripts' ) );
	}

	function enqueue_scripts() {
		wp_enqueue_script( 'wcb-sponsor-order', wcb_dev_url( WCB_LIB_URL . '/sponsors/js/order.js' ), array('jquery-ui-sortable'), '20110212' );
		wp_enqueue_style( 'wcb-sponsor-order', wcb_dev_url( WCB_LIB_URL . '/sponsors/css/order.css' ), array(), '20110212' );
	}

	function admin_init() {
		register_setting( 'wcb_sponsor_options', $this->get_name(), array( &$this, 'validate' ) );
	}

	function validate( $input ) {
		if ( ! is_array( $input ) ) {
			$input = null;
		} else {
			foreach ( $input as $key => $value ) {
				$input[ $key ] = (int) $input[ $key ];
			}
			$input = array_values( $input );
		}

		return $input;
	}

	function get_name() {
		return 'wcb_sponsor_level_order';
	}

	function render() {
		if ( ! isset( $_REQUEST['updated'] ) )
			$_REQUEST['updated'] = false;

		$levels = $this->get_levels();
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h1><?php _e( 'Order Sponsor Levels', 'wordcamporg' ); ?></h1>

			<?php if ( false !== $_REQUEST['updated'] ) : ?>
				<div class="updated fade"><p><strong><?php _e( 'Options saved', 'wordcamporg' ); ?></strong></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcb_sponsor_options' ); ?>
				<div class="description sponsor-order-instructions">
					<?php _e('Change the order of sponsor levels are displayed in the sponsors page template.', 'wordcamporg'); ?>
				</div>
				<ul class="sponsor-order">
				<?php foreach( $levels as $term ): ?>
					<li class="level">
						<input type="hidden" class="level-id" name="<?php echo esc_attr( $this->get_name() ); ?>[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
						<?php echo esc_html( $term->name ); ?>
					</li>
				<?php endforeach; ?>
				</ul>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Options', 'wordcamporg' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	function get_levels() {
		$option         = get_option( $this->get_name() );
		$term_objects   = get_terms( WCB_SPONSOR_LEVEL_TAXONOMY, array('get' => 'all') );
		$terms          = array();
		$ordered_terms  = array();

		foreach ( $term_objects as $term ) {
			$terms[ $term->term_id ] = $term;
		}

		if ( empty( $option ) )
			$option = array();

		foreach ( $option as $term_id ) {
			if ( isset( $terms[ $term_id ] ) ) {
				$ordered_terms[] = $terms[ $term_id ];
				unset( $terms[ $term_id ] );
			}
		}

		return array_merge( $ordered_terms, array_values( $terms ) );
	}

	function get_option( $option, $name ) {
		if ( 'sponsor_level_order' == $name )
			return $this->get_levels();
		else
			return $option;
	}
}

?>