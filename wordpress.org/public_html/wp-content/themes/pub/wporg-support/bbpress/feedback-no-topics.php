<div class="bbp-template-notice">
	<p>
		<?php
		if ( is_user_logged_in() ) {
			_e( 'No topics found. Select another view or start a new post.', 'wporg-forums' );
		} else {
			printf(
			/* translators: %s: Login URL. */
				__( 'No topics found. Select another view or start a new post. Please <a href="%s">log in</a> first.', 'wporg-forums' ),
				esc_url( wp_login_url( bbp_get_forum_permalink() ) )
			);
		}
		?>
	</p>
</div>
