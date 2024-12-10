<?php
/**
 * Render the release draft card block.
 *
 * @package wporg-plugins
 */

$markup = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px","radius":{"topLeft":"2px","topRight":"2px"}}},"borderColor":"light-grey-1","layout":{"type":"default","justifyContent":"left"}} -->
<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20);border-top-left-radius:2px;border-top-right-radius:2px">
	<!-- wp:heading {"style":{"spacing":{"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"heading-4","fontFamily":"inter"} -->
		<h2 class="wp-block-heading has-inter-font-family has-heading-4-font-size" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0">
		$post_title
		</h2>
	<!-- /wp:heading -->
	$block->inner_html );
</div>
<!-- /wp:group -->
HTML;

printf(
	'<div %1$>%2$s</div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $markup ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);
