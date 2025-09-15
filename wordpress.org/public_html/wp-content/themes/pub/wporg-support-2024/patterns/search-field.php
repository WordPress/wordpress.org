<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/search-field',
		array(
			'title'   => __( 'Search Field', 'wporg-forums' ),
			'content' => sprintf(
				'<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
				<div id="wporg-search" class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--20)">
					%s
				</div>
				<!-- /wp:group -->',
				get_search_form( false )
			),
		)
	);
}

?>
