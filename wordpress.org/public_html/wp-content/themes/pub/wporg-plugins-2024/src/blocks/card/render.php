<?php
/**
 * Render a card block.
 *
 * @package wporg-plugins
 */

$html = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px","radius":{"topLeft":"2px","topRight":"2px"}}},"borderColor":"light-grey-1"} -->
<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20);border-top-left-radius:2px;border-top-right-radius:2px">
	<!-- wp:heading {"style":{"spacing":{"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"heading-4"} -->
	<h3 class="wp-block-heading has-heading-4-font-size" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0">
	{$block->attributes['title']}
	</h3>
	<!-- /wp:heading -->
	$content
</div>
<!-- /wp:group -->
HTML;

$output = sprintf(
	'<div %1$s>%2$s</div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	$html,
);

echo do_blocks( $output ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
