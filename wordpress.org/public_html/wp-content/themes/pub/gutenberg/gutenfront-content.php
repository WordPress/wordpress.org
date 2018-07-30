<?php
$title = __( 'Say Hello to the New Editor', 'gutenbergtheme' );

$content = '<!-- wp:subhead -->
<p class="wp-block-subhead">' . __( 'It’s a whole new way to use WordPress. We call it Gutenberg.', 'gutenbergtheme' ) . '</p>
<!-- /wp:subhead -->' . "\n\n";

$content .= '<!-- wp:image {"id":97629,"align":"full"} -->
<figure class="wp-block-image alignfull"><img src="https://wordpress.org/gutenberg/files/2018/07/Screenshot-4-1.png" alt="" class="wp-image-97629"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( "Gutenberg is the new editor for WordPress. It's been completely rethought and rebuilt to make it easier for you to easily create rich, beautiful posts and pages - whether you write code for a living, or you're building a website for the first time.", 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:gallery {"align":"wide","columns":4,"className":"gutenberg-landing\u002d\u002dfeatures-grid"} -->
<ul class="wp-block-gallery alignwide columns-4 is-cropped gutenberg-landing--features-grid"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Visual-1.gif" alt=""/><figcaption>' . _x( 'Trust that your editor looks like your actual website.', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Layout-3.gif" alt=""/><figcaption>' . _x( 'Easily create modern, multimedia-heavy layouts.', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Plugin-1-1.gif" alt=""/><figcaption>' . _x( 'Accomplish more with fewer plugins.', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Devices-1-1.gif" alt=""/><figcaption>' . _x( 'Work across all screen sizes and devices.', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li></ul>
<!-- /wp:gallery -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:button {"align":"center","backgroundColor":"dark-blue"} -->
<div class="wp-block-button aligncenter"><a class="wp-block-button__link has-background has-dark-blue-background-color" href="/plugins/gutenberg/">' . __( "Download Gutenberg Today", 'gutenbergtheme' ) . '</a></div>
<!-- /wp:button -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="is-small-text gutenberg-landing--button-disclaimer"><em>' .
	sprintf(
		/* translators: %s: The URL to the Clasic Editor plugin. */
		__( "Gutenberg is available as a plugin today, and will be included in version 5.0 of WordPress. The <a href='%s'>classic editor</a> will be available as a plugin if needed.", 'gutenbergtheme' ),
		'/plugins/classic-editor/'
	) . '</em></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">' . __( 'Meet your new best friend, Blocks', 'gutenbergtheme' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( 'Blocks allow you to insert, rearrange, and style rich content natively, instead of relying on a daunting list of separate features: shortcodes, embeds, widgets, post formats, custom post types, theme options, meta-boxes, and other formatting elements.', 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":358} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Insert-Block-2-1.gif" alt="" class="wp-image-358"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( "Blocks allow for rich customization without deep knowledge of code, and make good on the promise of WordPress: broad functionality with a clear, consistent user experience. Here's just a selection of the default blocks included with Gutenberg:", 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:gallery {"align":"full","columns":8} -->
<ul class="wp-block-gallery alignfull columns-8 is-cropped"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon.png" alt=""/><figcaption>' . _x( 'Paragraph', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Heading.png" alt=""/><figcaption>' . _x( 'Heading', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Subheading.png" alt=""/><figcaption>' . _x( 'Subheading', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Quote.png" alt=""/><figcaption>' . _x( 'Quote', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Image.png" alt=""/><figcaption>' . _x( 'Image', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Gallery.png" alt=""/><figcaption>' . _x( 'Gallery', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Cover-Image.png" alt=""/><figcaption>' . _x( 'Cover Image', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Video.png" alt=""/><figcaption>' . _x( 'Video', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Audio.png" alt=""/><figcaption>' . _x( 'Audio', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Column.png" alt=""/><figcaption>' . _x( 'Columns', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-File.png" alt=""/><figcaption>' . _x( 'File', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Code.png" alt=""/><figcaption>' . _x( 'Code', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-List.png" alt=""/><figcaption>' . _x( 'List', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Button.png" alt=""/><figcaption>' . _x( 'Button', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Embeds.png" alt=""/><figcaption>' . _x( 'Embeds', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-More.png" alt=""/><figcaption>' . _x( 'More', 'Image Caption', 'gutenbergtheme' ) . '</figcaption></figure></li></ul>
<!-- /wp:gallery -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">' . __( 'Be your own builder', 'gutenbergtheme' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( 'A single block is nice — solid, clear, discrete. But when you start building with blocks? That’s when the real excitement starts: endless combinations, endless layouts, endless possibility, all driven by your vision.', 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":359} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Builder-2-1.gif" alt="" class="wp-image-359"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">' . __( 'Gutenberg ❤️ Developers', 'gutenbergtheme' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:columns {"className":"gutenberg-landing\u002d\u002ddevelopers-columns"} -->
<div class="wp-block-columns has-2-columns gutenberg-landing--developers-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"align":"left"} -->
<h3 style="text-align:left">' . __( 'Built with modern technology.', 'gutenbergtheme' ) . '</h3>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( 'Gutenberg was developed on GitHub and uses the WordPress REST API, Javascript, and React.', 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="is-small-text"><a href="https://wordpress.org/gutenberg/handbook/language/">' . __( 'Learn more', 'gutenbergtheme' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->' . "\n\n";

$content .= '<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":3,"align":"left"} -->
<h3 style="text-align:left">' . __( 'Designed for compatibility.', 'gutenbergtheme' ) . '</h3>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( 'We recommend migrating features to blocks when possible, but support for existing WordPress functionality will remain, and there will be transition paths for shortcodes, meta-boxes, and Custom Post Types.', 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="is-small-text"><a href="https://wordpress.org/gutenberg/handbook/reference/faq/">' . __( 'Learn more', 'gutenbergtheme' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">' . __( 'The editor is just the beginning', 'gutenbergtheme' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . __( "Gutenberg is more than an editor. It's also the foundation that'll revolutionize customization and site building in WordPress.", 'gutenbergtheme' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . __( '"Once Gutenberg is capable of handling all the pieces that visually compose a site — with themes providing styles for all the blocks — we end up with an editor that looks <em>exactly like the front-end</em>."', 'gutenbergtheme' ) . '</p><cite>— <a href="https://matiasventura.com/post/gutenberg-or-the-ship-of-theseus/">' . __( 'Matias Ventura', 'gutenbergtheme' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . __( '"Suddenly, the chore of setting up a new website becomes effortless."', 'gutenbergtheme' ) . '</p><cite>— <a href="https://loopconf.com/talk/customizing-the-future/">' . __( 'Mel Choyce', 'gutenbergtheme' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . __( '"The web up until this point has been confined to some sort of rectangular screen. But that is not how it’s going to be. Gutenberg has the potential of moving us into the next time."', 'gutenbergtheme' ) . '</p><cite>— <a href="https://wordpress.tv/2017/12/10/morten-rand-hendriksen-gutenberg-and-the-wordpress-of-tomorrow/">' . __( 'Morten Rand-Hendriksen', 'gutenbergtheme' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:button {"align":"center","backgroundColor":"dark-blue"} -->
<div class="wp-block-button aligncenter"><a class="wp-block-button__link has-background has-dark-blue-background-color" href="/plugins/gutenberg/">' . __( 'Download Gutenberg Today', 'gutenbergtheme' ) . '</a></div>
<!-- /wp:button -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="is-small-text gutenberg-landing--button-disclaimer"><em>' .
	sprintf(
		/* translators: %s: The URL to the Clasic Editor plugin. */
		__( "Gutenberg is available as a plugin today, and will be included in version 5.0 of WordPress. The <a href='%s'>classic editor</a> will be available as a plugin if needed.", 'gutenbergtheme' ),
		'/plugins/classic-editor/'
	) . '</em></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"center"} -->
<h2 style="text-align:center">' . __( 'Dig in deeper', 'gutenbergtheme' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:list -->
<ul>
	<li><a href="https://make.wordpress.org/core/2017/01/17/editor-technical-overview">' . __( 'Gutenberg Editor Technical Overview', 'gutenbergtheme' ) . '</a></li>
	<li><a href="http://gutenberg-devdoc.surge.sh/reference/design-principles/">' . __( 'Gutenberg Design Principles', 'gutenbergtheme' ) . '</a></li>
	<li><a href="https://make.wordpress.org/core/tag/gutenberg/">' . __( 'Development updates on make.wordpress.org', 'gutenbergtheme' ) . '</a></li>
	<li><a href="https://wordpress.tv/?s=gutenberg">' . __( 'WordPress.tv Talks about Gutenberg', 'gutenbergtheme' ) . '</a></li>
	<li><a href="https://wordpress.org/gutenberg/handbook/reference/faq/">' . __( 'FAQs', 'gutenbergtheme' ) . '</a></li>
</ul>
<!-- /wp:list -->' . "\n\n";

return compact( 'title', 'content' );
