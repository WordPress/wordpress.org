<?php

class WCB_Container extends WCB_Elements {
	var $attrs;
	var $tag;

	function WCB_Container( $args=array(), $elements=array() ) {
		parent::WCB_Elements( $elements );

		$defaults = array(
			'id' => '',
			'class' => '',
			'tag' => 'div'
		);
		$args = wp_parse_args( $args, $defaults );

		$this->tag = $args['tag'];
		unset( $args['tag'] );

		$this->attrs = $args;
	}

	function esc_attrs( $attrs ) {
		$html = '';
		foreach ( $attrs as $k => $v ) {
			if ( ! empty( $k ) && ! empty( $v ) )
				$html .= ' ' . esc_html( $k ) . '="' . esc_attr( $v ) . '"';
		}
		return $html;
	}

	function before() {
		echo '<' . esc_html( $this->tag ) . $this->esc_attrs( $this->attrs ) . '>';
	}

	function after() {
		echo '</' . esc_html( $this->tag ) . '>';
	}

	function get_id() {
		return $this->attrs['id'];
	}
}

?>