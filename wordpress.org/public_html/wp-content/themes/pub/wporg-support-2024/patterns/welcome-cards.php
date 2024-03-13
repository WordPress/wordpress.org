<?php
if ( function_exists( 'register_block_pattern' ) ) {
	register_block_pattern(
		'wporg-support/welcome-cards',
		array(
			'title'   => __( 'Welcome Cards', 'wporg-forums' ),
			'content' => '<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%"}} -->
			<div class="wp-block-group is-style-cards-grid">

				<!-- wp:group {"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">

					<!-- wp:heading -->
					<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Welcome to Support', 'wporg-forums' ) . '</h2>
					<!-- /wp:heading -->

					<!-- wp:paragraph -->
					<p>' . __( 'Our community-based Support Forums are a great place to learn, share, and troubleshoot.', 'wporg-forums' ) . '</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph -->
					<p>' . __( '<a href="https://wordpress.org/support/welcome/">Get started</a>', 'wporg-forums' ) . '</p>
					<!-- /wp:paragraph -->

				</div>
				<!-- /wp:group -->

				<!-- wp:group {"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">

					<!-- wp:heading -->
					<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Documentation', 'wporg-forums' ) . '</h2>
					<!-- /wp:heading -->

					<!-- wp:paragraph -->
					<p>' . __( 'Your first stop where you\'ll find information on everything from installing to creating plugins.', 'wporg-forums' ) . '</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph -->
					<p>' . __( '<a href="https://wordpress.org/documentation/">Explore documentation</a>', 'wporg-forums' ) . '</p>
					<!-- /wp:paragraph -->

				</div>
				<!-- /wp:group -->

				<!-- wp:group {"layout":{"type":"constrained"}} -->
				<div class="wp-block-group">

					<!-- wp:heading -->
					<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Get Involved', 'wporg-forums' ) . '</h2>
					<!-- /wp:heading -->

					<!-- wp:paragraph -->
					<p>' . __( 'The Support Handbook is great for tips, tricks, and advice regarding giving the best support possible.', 'wporg-forums' ) . '</p>
					<!-- /wp:paragraph -->

					<!-- wp:paragraph -->
					<p>' . __( '<a href="https://make.wordpress.org/support/handbook/">Explore the Handbook</a>', 'wporg-forums' ) . '</p>
					<!-- /wp:paragraph -->

				</div>
				<!-- /wp:group -->

			</div>
			<!-- /wp:group -->',
		)
	);
}

?>
