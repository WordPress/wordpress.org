<?php

$content = do_blocks( <<<BLOCKS
<!-- wp:query {"queryId":9,"query":{"perPage":3,"pages":0,"offset":0,"postType":"page","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false,"parents":[]}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px"}},"borderColor":"light-grey-1","layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)"><!-- wp:group {"style":{"spacing":{"margin":{"bottom":"var:preset|spacing|20"}}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group" style="margin-bottom:var(--wp--preset--spacing--20)"><!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:post-title {"style":{"spacing":{"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"heading-4","fontFamily":"inter"} /-->

<!-- wp:post-date {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"extra-small"} /--></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:paragraph -->
<p>Actions</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group"><!-- wp:heading {"level":4,"fontSize":"normal"} -->
<h4 class="wp-block-heading has-normal-font-size">Checks</h4>
<!-- /wp:heading -->

<!-- wp:wporg/release-checks /-->

<!-- wp:heading {"level":4,"fontSize":"normal"} -->
<h4 class="wp-block-heading has-normal-font-size">Flags</h4>
<!-- /wp:heading -->

<!-- wp:wporg/release-flags /-->

<!-- wp:heading {"level":4,"fontSize":"normal"} -->
<h4 class="wp-block-heading has-normal-font-size">Changelog</h4>
<!-- /wp:heading -->

<!-- wp:wporg/release-changelog /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query -->
BLOCKS
);

printf(
    '<div %1$s>%2$s<div>',
    get_block_wrapper_attributes(),
    $content
);
