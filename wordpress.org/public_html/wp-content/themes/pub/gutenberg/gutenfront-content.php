<?php
$title = 'Say Hello to the New Editor';

$content = <<<EOPOST
<!-- wp:subhead -->
<p class="wp-block-subhead">It’s a whole new way to use WordPress. We call it Gutenberg.</p>
<!-- /wp:subhead -->

<!-- wp:image {"id":351,"align":"full"} -->
<figure class="wp-block-image alignfull"><img src="https://wordpress.org/gutenberg/wp-content/uploads/2018/07/Screenshot-4.png" alt="" class="wp-image-351"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":97629,"align":"full"} -->
<figure class="wp-block-image alignfull"><img src="https://wordpress.org/gutenberg/files/2018/07/Screenshot-4-1.png" alt="" class="wp-image-97629"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">Gutenberg is the new editor for WordPress. It's been completely rethought and rebuilt to make it easier for you to easily create rich, beautiful posts and pages — whether you write code for a living, or you're building a website for the first time.</p>
<!-- /wp:paragraph -->

<!-- wp:gallery {"align":"wide","columns":4,"className":"gutenberg-landing\u002d\u002dfeatures-grid"} -->
<ul class="wp-block-gallery alignwide columns-4 is-cropped gutenberg-landing--features-grid"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Visual-1.gif" alt=""/><figcaption>Trust that your editor looks like your actual website.</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Layout-3.gif" alt=""/><figcaption>Easily create modern, multimedia-heavy layouts.</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Plugin-1-1.gif" alt=""/><figcaption>Accomplish more with fewer plugins.</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Devices-1-1.gif" alt=""/><figcaption>Work across all screen sizes and devices.</figcaption></figure></li></ul>
<!-- /wp:gallery -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:button {"align":"center","backgroundColor":"dark-blue"} -->
<div class="wp-block-button aligncenter"><a class="wp-block-button__link has-background has-dark-blue-background-color" href="https://wordpress.org/plugins/gutenberg/">Download Gutenberg Today</a></div>
<!-- /wp:button -->

<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="is-small-text gutenberg-landing--button-disclaimer"><em>Gutenberg is available as a plugin today, and will be included in version 5.0 of WordPress. The <a href="https://wordpress.org/plugins/classic-editor/">classic editor</a> will <em>be available as a plugin if needed.</em></em></p>
<!-- /wp:paragraph -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">Meet your new best friend, Blocks</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">Blocks allow you to insert, rearrange, and style rich content natively, instead of relying on a daunting list of separate features: shortcodes, embeds, widgets, post formats, custom post types, theme options, meta-boxes, and other formatting elements.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":358} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Insert-Block-2-1.gif" alt="" class="wp-image-358"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":358} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/wp-content/uploads/2018/07/Insert-Block-2-1.gif" alt="" class="wp-image-358"/></figure>
<!-- /wp:image -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">Blocks allow for rich customization without deep knowledge of code, and make good on the promise of WordPress: broad functionality with a clear, consistent user experience. Here's just a selection of the default blocks included with Gutenberg:</p>
<!-- /wp:paragraph -->

<!-- wp:gallery {"align":"full","columns":8} -->
<ul class="wp-block-gallery alignfull columns-8 is-cropped"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon.png" alt=""/><figcaption>Paragraph</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Heading.png" alt=""/><figcaption>Heading</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Subheading.png" alt=""/><figcaption>Subheading</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Quote.png" alt=""/><figcaption>Quote</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Image.png" alt=""/><figcaption>Image</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Gallery.png" alt=""/><figcaption>Gallery</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Cover-Image.png" alt=""/><figcaption>Cover Image</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Video.png" alt=""/><figcaption>Video</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Audio.png" alt=""/><figcaption>Audio</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Column.png" alt=""/><figcaption>Columns</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-File.png" alt=""/><figcaption>File</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Code.png" alt=""/><figcaption>Code</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-List.png" alt=""/><figcaption>List</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Button.png" alt=""/><figcaption>Button</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Embeds.png" alt=""/><figcaption>Embeds</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-More.png" alt=""/><figcaption>More</figcaption></figure></li></ul>
<!-- /wp:gallery -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">Be your own builder</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">A single block is nice — solid, clear, discrete. But when you start building with blocks? That’s when the real excitement starts: endless combinations, endless layouts, endless possibility, all driven by your vision.</p>
<!-- /wp:paragraph -->

<!-- wp:image {"id":359} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/wp-content/uploads/2018/07/Builder-2-1.gif" alt="" class="wp-image-359"/></figure>
<!-- /wp:image -->

<!-- wp:image {"id":359} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Builder-2-1.gif" alt="" class="wp-image-359"/></figure>
<!-- /wp:image -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">Gutenberg ❤️ Developers</h2>
<!-- /wp:heading -->

<!-- wp:columns {"className":"gutenberg-landing\u002d\u002ddevelopers-columns"} -->
<div class="wp-block-columns has-2-columns gutenberg-landing--developers-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"align":"left"} -->
<h3 style="text-align:left">Built with modern technology.</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">Gutenberg was developed on GitHub and uses the WordPress REST API, Javascript, and React.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="is-small-text"><a href="https://wordpress.org/gutenberg/handbook/language/">Learn more</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->

<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"align":"left"} -->
<h3 style="text-align:left">Designed for compatibility.</h3>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">We recommend migrating features to blocks when possible, but support for existing WordPress functionality will remain, and there will be transition paths for shortcodes, meta-boxes, and Custom Post Types.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="is-small-text"><a href="https://wordpress.org/gutenberg/handbook/reference/faq/">Learn more</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">The editor is just the beginning</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">Gutenberg is more than an editor. It's also the foundation that'll revolutionize customization and site building in WordPress.</p>
<!-- /wp:paragraph -->

<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>"Once Gutenberg is capable of handling all the pieces that visually compose a site — with themes providing styles for all the blocks — we end up with an editor that looks <em>exactly like the front-end</em>."</p><cite>— <a href="https://matiasventura.com/post/gutenberg-or-the-ship-of-theseus/">Matias Ventura</a></cite></blockquote>
<!-- /wp:quote -->

<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>"Suddenly, the chore of setting up a new website becomes effortless."</p><cite>— <a href="https://loopconf.com/talk/customizing-the-future/">Mel Choyce</a></cite></blockquote>
<!-- /wp:quote -->

<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>"The web up until this point has been confined to some sort of rectangular screen. But that is not how it's going to be. Gutenberg has the potential of moving us into the next time."</p><cite>— <a href="https://wordpress.tv/2017/12/10/morten-rand-hendriksen-gutenberg-and-the-wordpress-of-tomorrow/">Morten Rand-Hendriksen</a> </cite></blockquote>
<!-- /wp:quote -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:button {"align":"center","backgroundColor":"dark-blue"} -->
<div class="wp-block-button aligncenter"><a class="wp-block-button__link has-background has-dark-blue-background-color" href="https://wordpress.org/plugins/gutenberg/">Download Gutenberg Today</a></div>
<!-- /wp:button -->

<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="is-small-text gutenberg-landing--button-disclaimer"><em>Gutenberg is available as a plugin today, and will be included in version 5.0 of WordPress. <em>The <a href="https://wordpress.org/plugins/classic-editor/">classic editor</a> will be available as a plugin if needed. </em></em></p>
<!-- /wp:paragraph -->

<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer"></div>
<!-- /wp:spacer -->

<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">Dig in deeper</h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul>
	<li><a href="https://make.wordpress.org/core/2017/01/17/editor-technical-overview">Gutenberg Editor Technical Overview</a></li>
	<li><a href="http://gutenberg-devdoc.surge.sh/reference/design-principles/">Gutenberg Design Principles</a></li>
	<li><a href="https://make.wordpress.org/core/tag/gutenberg/">Development updates on make.wordpress.org</a></li>
	<li><a href="https://wordpress.tv/?s=gutenberg">WordPress.tv Talks about Gutenberg</a></li>
	<li><a href="https://wordpress.org/gutenberg/handbook/reference/faq/">FAQs</a></li>
</ul>
<!-- /wp:list -->
EOPOST;

return compact( 'title', 'content' );
