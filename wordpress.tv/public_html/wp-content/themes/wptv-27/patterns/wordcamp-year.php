<?php
/**
 * Title: WordCamp year directory (automatic prototype)
 * Slug: wptv/wordcamp-year
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"className":"wptv-wordcamps__year","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-wordcamps__year"><!-- wp:heading {"level":3,"fontSize":"heading-5"} -->
<h3 class="wp-block-heading has-heading-5-font-size">WPTV_YEAR</h3>
<!-- /wp:heading -->
<!-- wp:terms-query {"termQuery":{"perPage":0,"taxonomy":"event","order":"asc","orderBy":"name","include":[0],"hideEmpty":true,"showNested":false,"inherit":false}} -->
<div class="wp-block-terms-query"><!-- wp:term-template {"className":"wptv-wordcamps__directory-list","layout":{"type":"grid","columnCount":3}} -->
<!-- wp:term-name {"level":4,"isLink":true,"fontSize":"normal"} /-->
<!-- /wp:term-template --></div>
<!-- /wp:terms-query --></div>
<!-- /wp:group -->
