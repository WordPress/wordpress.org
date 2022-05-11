<?php

class Gth_Temporary_Post {
	public $ID;
	public $comments_open = 'open';
	public function __construct( $post_id ) {
		$this->ID = $post_id;
	}

	public function __toString() {
		return strval( $this->ID );
	}
}

