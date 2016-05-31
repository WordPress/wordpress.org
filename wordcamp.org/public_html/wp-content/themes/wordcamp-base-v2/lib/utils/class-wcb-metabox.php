<?php

class WCB_Metabox {
	var $screens = array();
	/**
	 * The current instance
	 * @access private
	 */
	var $current_instance;
	var $id_base;
	static $registry;

	function __construct( $id_base='' ) {
		$this->id_base = ( empty( $id_base ) ) ? get_class( $this ) : $id_base;

		add_action('admin_init',            array( &$this, '_admin_init' ) );
		add_action('admin_enqueue_scripts', array( &$this, '_admin_enqueue_scripts' ) ); // @todo styles. also, consider footer.
	}
	
	// PHP4 Compatible constructor
	function WCB_Metabox( $id_base='' ) {
		$this->__construct( $id_base );
	}

	static function registry() {
		if ( ! isset( WCB_Metabox::$registry ) )
			WCB_Metabox::$registry = new WCB_Registry();
		return WCB_Metabox::$registry;
	}

	function add_instances( $screens, $args=array() ) {
		array_walk( $screens, array( &$this, 'add_instance' ), $args );
	}

	function add_instance( $screen, $args=array() ) {
		if ( ! isset( $this->screens[ $screen ] ) )
			$this->screens[ $screen ] = array();

		$index = count( $this->screens[ $screen ] );
		return $this->_set_instance( $screen, $index, $args );
	}

	function update_instance( $screen, $index, $args=array() ) {
		if ( ! isset( $this->screens[ $screen ] )
		||   ! isset( $this->screens[ $screen ][ $index ] )
		||   ! is_integer( $index ) ) {
			return;
		}
		return $this->_set_instance( $screen, $index, $args );
	}

	function get_instance( $screen='', $index=0 ) {
		if ( empty( $screen ) )
			return $this->current_instance;

		if ( isset( $this->screens[ $screen ] )
		&&   isset( $this->screens[ $screen ][ $index ] ) ) {
			return $this->screens[ $screen ][ $index ];
		}
	}

	/**
	 * Internal function used for both add/update instance.
	 * @access private
	 */
	function _set_instance( $screen, $index, $args=array() ) {
		$defaults = array(
			// 'id'            => '',
			'title'         => __( 'Untitled' , 'wordcamporg'),
			'context'       => 'side',
			'priority'      => 'default',
		);
		$overrides = array(
			'screen'        => $screen,
			'metabox_id'    => $screen . '_' . $index,
		);
		$instance_defaults = $this->instance_default_args();

		$args = array_merge( $defaults, $instance_defaults, $args, $overrides );

		$this->screens[ $screen ][ $index ] = $args;

		return $index;
	}

	function remove_instance( $screen, $index ) {
		if ( ! isset( $this->screens[ $screen ] )
		||   ! isset( $this->screens[ $screen ][ $index ] )
		||   ! is_integer( $index ) ) {
			return;
		}

		unset( $this->screens[ $screen ][ $index ] );
	}

	function instance_default_args() {
		return array();
	}

	function get_id( $type='' ) {
		$base = $this->id_base;
		$id   = $base;

		// General IDs
		switch ( $type ) {
			case 'screens':
				$id = "$base-screens";
				break;
		}

		// Instance IDs
		$instance = $this->get_instance();

		if ( ! empty( $instance ) ) {
			$screen      = $instance['screen'];
			$instance_id = $instance['metabox_id'];

			switch ( $type ) {
				case 'name':
				case 'nonce_action':
					$id = "{$base}_{$instance_id}";
					break;
				case 'nonce_name':
					$id = "_wpnonce_{$base}_{$instance_id}";
					break;
			}
		}

		return esc_attr( $id );
	}

	function get_name( $key='' ) {
		$name = $this->get_id('name');
		if ( ! empty( $key ) )
			$name .= "[$key]";
		return esc_attr( $name );
	}

	function name() {
		$args = func_get_args();
		$name = call_user_func_array( array( &$this, 'get_name' ), $args );
		echo " name='$name' ";
	}

	// Only enqueues metabox scripts when necessary.
	// Should be overridden by subclasses.
	function enqueue_scripts( $id ) {}

	/**
	 * @final
	 */
	function _admin_enqueue_scripts() {
		// @todo: Move to get_current_screen when targeting 3.1+
		global $current_screen;

		if ( isset( $current_screen ) && isset( $this->screens[ $current_screen->id ] ) )
			$this->enqueue_scripts( $current_screen->id );
	}

	function admin_init() {}

	/**
	 * @final
	 */
	function _admin_init() {
		foreach ( $this->screens as $screen => $instances ) {
			foreach ( $instances as $index => $instance ) {
				// Add the meta box
				add_meta_box( "$this->id_base-$index-meta-box", $instance['title'],
					array( &$this, '_render' ), $screen, $instance['context'],
					$instance['priority'], $instance );
			}
		}

		$this->admin_init();
	}

	/**
	 * Render the metabox contents.
	 */
	function render( $object, $instance ) {}

	/**
	 * @final
	 */
	function _render( $object, $box ) {
		// Ignore the box array; our instance is stored in args.
		$instance = $box['args'];
		// Set current instance
		$this->current_instance = $instance;

		// Add a nonce
		wp_nonce_field( $this->get_id('nonce_action'), $this->get_id('nonce_name'), false );

		// Keep track of the rendered screen
		$screen_name  = $this->get_id('screens') . '[]';
		$screen_value = esc_attr( $instance['screen'] );
		echo "<input type='hidden' name='$screen_name' value='$screen_value' />";

		$this->render( $object, $instance );
		// Unset current instance
		$this->current_instance = null;
	}


	/**
	 * Save the metabox contents.
	 *
	 * Do not call directly!
	 *
	 * @todo Add access when php5+.
	 * @access protected
	 */
	function save( $post_id, $post ) {}


	/**
	 * Determine whether to save the metabox contents.
	 *
	 * Saves metabox if returns true.
	 *
	 * @todo Add access when php5+.
	 * @access protected
	 * @return boolean Whether to save the metabox.
	 */
	function maybe_save( $post_id, $post ) {}

	/**
	 * Public access, for hooks, but final so it's remains fixed.
	 * @access public
	 * @final
	 */
	function save_instances() {
		// Screens are stored in a hidden input to keep track of any page changes.
		$screens_key = $this->get_id('screens');

		if ( ! isset( $_POST[ $screens_key ] ) || ! is_array( $_POST[ $screens_key ] ) )
			return;

		foreach ( $_POST[ $screens_key ] as $screen ) {
			if ( ! isset( $this->screens[ $screen ] ) )
				continue;

			foreach ( $this->screens[ $screen ] as $instance ) {
				$this->current_instance = $instance;

				// Nonce check (sorry, you don't have a choice about this one).
				check_admin_referer( $this->get_id('nonce_action'), $this->get_id('nonce_name') );

				$args = func_get_args();
				// Only save if maybe_save returns true.
				if ( call_user_func_array( array( &$this, 'maybe_save' ), $args ) )
					call_user_func_array( array( &$this, 'save' ), $args );

				$this->current_instance = null;
			}
		}
	}


	function add_save_action( $name, $priority=10 ) {
		add_action( $name, array( &$this, 'save_instances' ), $priority, 99 ); // Effectively infinite args.
	}
	function remove_save_action( $name, $priority=10 ) {
		remove_action( $name, array( &$this, 'save_instances' ), $priority, 99 ); // Effectively infinite args.
	}
}

function wcb_get_metabox( $classname ) {
	$registry = WCB_Metabox::registry();
	return $registry->get( $classname );
}



?>