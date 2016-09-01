<?php

namespace WordPressdotorg\Forums;

class Moderators {

	public function __construct() {
		add_action( 'parse_query', array( $this, 'register_views' ), 1 );
	}

	public function register_views() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		bbp_register_view(
			'spam',
			__( 'Spam', 'wporg-forums' ),
			array(
				'meta_key'      => null,
				'post_type'     => array(
					'topic',
					'reply',
				),
				'post_status'   => 'spam',
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);

		bbp_register_view(
			'pending',
			__( 'Pending', 'wporg-forums' ),
			array(
				'post_type'     => array(
					'topic',
					'reply',
				),
				'post_status'   => 'pending',
				'show_stickies' => false,
				'orderby'       => 'ID',
			)
		);
	}
}
