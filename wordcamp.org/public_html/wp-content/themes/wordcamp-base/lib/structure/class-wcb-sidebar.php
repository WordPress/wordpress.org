<?php

class WCB_Sidebar extends WCB_Element {
	var $id;
	var $args;
	
	function WCB_Sidebar( $args ) {
		$defaults = array(
			'class'             => '',
			// id, name, description left to their register_sidebar defaults.
			'before_widget'     => '<li id="%1$s" class="widget-container %2$s">',
			'after_widget'      => '</li>',
			'before_title'      => '<h3 class="widget-title">',
			'after_title'       => '</h3>',
		);
		
		$this->args = wp_parse_args( $args, $defaults );
		
		add_action( 'widgets_init', array( &$this, 'register' ) );
	}
	
	function register() {
		$this->id = register_sidebar( $this->args );
	}
	
	function render() {
		if ( ! isset( $this->id ) || ! is_active_sidebar( $this->id ) )
		 	return;
		
		parent::render();
	}
	
	function before() {
		$id = esc_attr( $this->id );
		$class = esc_attr( $this->args['class'] );
		
		echo "<div id='$id' class='widget-area $class' role='complementary'>";
		echo '<ul class="xoxo">';
	}
	
	function content() {
		dynamic_sidebar( $this->id );
	}
	
	function after() {
		echo '</ul></div>';
	}
}

?>