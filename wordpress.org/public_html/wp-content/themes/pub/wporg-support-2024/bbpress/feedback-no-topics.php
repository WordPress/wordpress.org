<?php
/**
 * Notice shown when a forum view has no topics.
 *
 * @package bbPress
 */

?>
<div class="bbp-template-notice">
	<?php if ( is_user_logged_in() ) : ?>
		<p><?php esc_html_e( 'No topics found. Select another view or start a new post.', 'wporg-forums' ); ?></p>
	<?php else : ?>
		<?php /* translators: %s: Login URL. */ ?>
		<p><?php printf( wp_kses_post( __( 'No topics found. Select another view or <a href="%s">log in</a> to start a new post.', 'wporg-forums' ) ), esc_url( wp_login_url() ) ); ?></p>
	<?php endif; ?>
</div>
