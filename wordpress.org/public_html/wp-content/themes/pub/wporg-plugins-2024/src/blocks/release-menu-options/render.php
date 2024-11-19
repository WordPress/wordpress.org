<?php

use WordPressdotorg\Plugin_Directory\Template;

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
	return;
}

?>
 <div data-wp-interactive="wporg-release-menu-options">
	<details  class="wporg-release-menu-options" data-wp-on--focusout="actions.handleFocusOut">
		<summary>
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">
				<path d="M13 19h-2v-2h2v2zm0-6h-2v-2h2v2zm0-6h-2V5h2v2z"></path>
			</svg>
			<span class="screen-reader-text">Toggle menu</span>
		</summary>
		<div class="wporg-release-menu-options-content">
			<a href="<?php echo esc_url( Template::download_link( $post->post_parent, get_post_meta( $post->ID, 'release_version', true ) ) ); ?>">
				<?php echo esc_html_e( 'Download', 'wporg-plugins' ); ?>
			</a>
			<a href="playground link">
				<?php echo esc_html_e( 'Load in Playground', 'wporg-plugins' ); ?>
			</a>
		</div>
	</details>
</div>
