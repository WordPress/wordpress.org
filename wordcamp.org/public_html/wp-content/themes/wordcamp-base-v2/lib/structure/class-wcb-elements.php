<?php

class WCB_Elements extends WCB_Element {
	var $elements = array();
	var $_queue = array();

	function WCB_Elements( $elements=array() ) {
		$this->add( $elements );
	}

	function add( $elements ) {
		if ( ! is_array( $elements ) )
			$elements = array( $elements );
		$this->elements = array_merge( $this->elements, $elements );
	}

	function reset() {
		$this->_queue = array();
	}

	/**
	 * Renders the elements.
	 * Elements can stop the queue by returning false in their render method.
	 * If the queue has been stopped, render() will resume from the stopping point.
	 *
	 * @param boolean $resume_only If true, will only render if the queue has already been stopped.
	 */
	function render( $resume_only=false ) {
		if ( ! $this->in_progress() ) {

			if ( $resume_only )
				return;

			$this->_queue = $this->elements;
			$this->before();
		}

		$this->content();

		if ( ! $this->in_progress() )   // Finish the collection.
			$this->after();
		else                            // We encountered a break.
			return false;
	}

	/**
	 * Determine whether a render is in progress or stopped.
	 */
	function in_progress() {
		return ! empty( $this->_queue );
	}

	/**
	 * Resumes rendering a stopped queue.
	 */
	function resume() {
		$this->render( true );
	}

	function content() {
		while ( $this->in_progress() ) {
			if ( $this->_queue[0]->render() === false )
				break;
			array_shift( $this->_queue );
		}
	}
}

?>