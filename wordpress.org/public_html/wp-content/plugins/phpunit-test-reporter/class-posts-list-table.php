<?php
namespace PTR;

use WP_Posts_List_Table;

class Posts_List_Table extends WP_Posts_List_Table {

	function __construct( $args = array() ) {
		parent::__construct( $args );

		add_filter( 'page_row_actions', [ $this, 'remove_quick_edit_link' ] );
	}

	function inline_edit() {
		// silence is golden, no more OOM.
	}

	function remove_quick_edit_link( $links ) {
		unset( $links['inline hide-if-no-js'] );
		return $links;
	}

}