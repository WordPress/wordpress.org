<?php

$localised_domain = parse_url( home_url( '/' ), PHP_URL_HOST );

$title = __( 'Say Hello to the New Editor', 'wporg' );

$content = '<!-- wp:subhead -->
<p class="wp-block-subhead">' . esc_html__( 'It&#8217;s a whole new way to use WordPress. We call it Gutenberg.', 'wporg' ) . '</p>
<!-- /wp:subhead -->' . "\n\n";

$content .= '<!-- wp:image {"id":97629,"align":"full"} -->
<figure class="wp-block-image alignfull"><img src="https://wordpress.org/gutenberg/files/2018/07/Screenshot-4-1.png" alt="" class="wp-image-97629"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Gutenberg is the new editor for WordPress. It&#8217;s been completely rethought and rebuilt to make it easier for you to easily create rich, beautiful posts and pages&#8212;whether you write code for a living, or you&#8217;re building a website for the first time.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:gallery {"align":"wide","columns":4,"className":"gutenberg-landing\u002d\u002dfeatures-grid"} -->
<ul class="wp-block-gallery alignwide columns-4 is-cropped gutenberg-landing--features-grid"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Plugin-1-1.gif" alt=""/><figcaption>' . esc_html_x( 'Accomplish more with fewer plugins.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Layout-3.gif" alt=""/><figcaption>' . esc_html_x( 'Easily create modern, multimedia-heavy layouts.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Devices-1-1.gif" alt=""/><figcaption>' . esc_html_x( 'Work across all screen sizes and devices.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Visual-1.gif" alt=""/><figcaption>' . esc_html_x( 'Trust that your editor looks like your actual website.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li></ul>
<!-- /wp:gallery -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:wporg/download-button -->
<div class="wp-block-wporg-download-button wp-block-button aligncenter"><a class="wp-block-button__link has-background has-strong-blue-background-color" href="' . "https://{$localised_domain}/plugins/gutenberg/" . '" style="background-color:rgb(0,115,170)">' . esc_html__( 'Download Gutenberg Today', 'wporg' ) . '</a></div>
<!-- /wp:wporg/download-button -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="has-small-font-size gutenberg-landing--button-disclaimer"><em>' .
	sprintf(
		/* translators: %s: The URL to the Clasic Editor plugin. */
		wp_kses_post( __( 'Gutenberg is available as a plugin today, and will be included in version 5.0 of WordPress. The <a href="%s">classic editor</a> will be available as a plugin if needed.', 'wporg' ) ),
		esc_url( "https://{$localised_domain}/plugins/classic-editor/" )
	) . '</em></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"left"} -->
<h2 style="text-align:left">' . esc_html__( 'Meet your new best friend, Blocks', 'wporg' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Blocks allow you to insert, rearrange, and style rich content natively, instead of relying on a daunting list of separate features: shortcodes, embeds, widgets, post formats, custom post types, theme options, meta-boxes, and other formatting elements.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":358} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Insert-Block-2-1.gif" alt="" class="wp-image-358"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Blocks allow for rich customization without deep knowledge of code, and make good on the promise of WordPress: broad functionality with a clear, consistent user experience. Here&#8217;s just a selection of the default blocks included with Gutenberg:', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:gallery {"align":"full","columns":8} -->
<ul class="wp-block-gallery alignfull columns-8 is-cropped"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon.png" alt=""/><figcaption>' . esc_html_x( 'Paragraph', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Heading.png" alt=""/><figcaption>' . esc_html_x( 'Heading', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Subheading.png" alt=""/><figcaption>' . esc_html_x( 'Subheading', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Quote.png" alt=""/><figcaption>' . esc_html_x( 'Quote', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Image.png" alt=""/><figcaption>' . esc_html_x( 'Image', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Gallery.png" alt=""/><figcaption>' . esc_html_x( 'Gallery', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Cover-Image.png" alt=""/><figcaption>' . esc_html_x( 'Cover Image', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Video.png" alt=""/><figcaption>' . esc_html_x( 'Video', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Audio.png" alt=""/><figcaption>' . esc_html_x( 'Audio', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Column.png" alt=""/><figcaption>' . esc_html_x( 'Columns', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-File.png" alt=""/><figcaption>' . esc_html_x( 'File', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Code.png" alt=""/><figcaption>' . esc_html_x( 'Code', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-List.png" alt=""/><figcaption>' . esc_html_x( 'List', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Button.png" alt=""/><figcaption>' . esc_html_x( 'Button', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-Embeds.png" alt=""/><figcaption>' . esc_html_x( 'Embeds', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Block-Icon-More.png" alt=""/><figcaption>' . esc_html_x( 'More', 'Image Caption', 'wporg' ) . '</figcaption></figure></li></ul>
<!-- /wp:gallery -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"left"} -->
<h2 style="text-align:left">' . esc_html__( 'Be your own builder', 'wporg' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'A single block is nice&#8212;solid, clear, discrete. But when you start building with blocks? That&#8217;s when the real excitement starts: endless combinations, endless layouts, endless possibility, all driven by your vision.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":359} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Builder-2-1.gif" alt="" class="wp-image-359"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"left"} -->
<h2 style="text-align:left">' . esc_html__( 'Gutenberg ❤️ Developers', 'wporg' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:columns {"className":"gutenberg-landing\u002d\u002ddevelopers-columns"} -->
<div class="wp-block-columns has-2-columns gutenberg-landing--developers-columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left"><strong>' . esc_html__( 'Built with modern technology.', 'wporg' ) . '</strong></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Gutenberg was developed on GitHub and uses the WordPress REST API, Javascript, and React.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="has-small-font-size"><a href="https://wordpress.org/gutenberg/handbook/language/">' . esc_html__( 'Learn more', 'wporg' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->' . "\n\n";

$content .= '<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left"><strong>' . esc_html__( 'Designed for compatibility.', 'wporg' ) . '</strong></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'We recommend migrating features to blocks when possible, but support for existing WordPress functionality will remain, and there will be transition paths for shortcodes, meta-boxes, and Custom Post Types.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="has-small-font-size"><a href="https://wordpress.org/gutenberg/handbook/reference/faq/">' . esc_html__( 'Learn more', 'wporg' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"left"} -->
<h2 style="text-align:left">' . esc_html__( 'The editor is just the beginning', 'wporg' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Gutenberg is more than an editor. It&#8217;s also the foundation that&#8217;ll revolutionize customization and site building in WordPress.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . wp_kses_post( __( '"Once Gutenberg is capable of handling all the pieces that visually compose a site&#8212;with themes providing styles for all the blocks&#8212;we end up with an editor that looks <em>exactly like the front-end</em>."', 'wporg' ) ) . '</p><cite>&#8212; <a href="https://matiasventura.com/post/gutenberg-or-the-ship-of-theseus/">' . esc_html__( 'Matias Ventura', 'wporg' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . esc_html__( '"Suddenly, the chore of setting up a new website becomes effortless."', 'wporg' ) . '</p><cite>&#8212; <a href="https://loopconf.com/talk/customizing-the-future/">' . esc_html__( 'Mel Choyce', 'wporg' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . esc_html__( '"The web up until this point has been confined to some sort of rectangular screen. But that is not how it&#8217;s going to be. Gutenberg has the potential of moving us into the next time."', 'wporg' ) . '</p><cite>&#8212; <a href="https://wordpress.tv/2017/12/10/morten-rand-hendriksen-gutenberg-and-the-wordpress-of-tomorrow/">' . esc_html__( 'Morten Rand-Hendriksen', 'wporg' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:wporg/download-button -->
<div class="wp-block-wporg-download-button wp-block-button aligncenter"><a class="wp-block-button__link has-background has-strong-blue-background-color" href="' . "https://{$localised_domain}/plugins/gutenberg/" . '" style="background-color:rgb(0,115,170)">' . esc_html__( 'Download Gutenberg Today', 'wporg' ) . '</a></div>
<!-- /wp:wporg/download-button -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="has-small-font-size gutenberg-landing--button-disclaimer"><em>' .
	sprintf(
		/* translators: %s: The URL to the Clasic Editor plugin. */
		wp_kses_post( __( 'Gutenberg is available as a plugin today, and will be included in version 5.0 of WordPress. The <a href="%s"">classic editor</a> will be available as a plugin if needed.', 'wporg' ) ),
		esc_url( "https://{$localised_domain}/plugins/classic-editor/" )
	) . '</em></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"left"} -->
<h2 style="text-align:left">' . esc_html__( 'Dig in deeper', 'wporg' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:list -->
<ul>
	<li><a href="https://make.wordpress.org/core/2017/01/17/editor-technical-overview">' . esc_html__( 'Gutenberg Editor Technical Overview', 'wporg' ) . '</a></li>
	<li><a href="http://gutenberg-devdoc.surge.sh/reference/design-principles/">' . esc_html__( 'Gutenberg Design Principles', 'wporg' ) . '</a></li>
	<li><a href="https://make.wordpress.org/core/tag/gutenberg/">' . esc_html__( 'Development updates on make.wordpress.org', 'wporg' ) . '</a></li>
	<li><a href="https://wordpress.tv/?s=gutenberg">' . esc_html__( 'WordPress.tv Talks about Gutenberg', 'wporg' ) . '</a></li>
	<li><a href="https://wordpress.org/gutenberg/handbook/reference/faq/">' . esc_html__( 'FAQs', 'wporg' ) . '</a></li>
</ul>
<!-- /wp:list -->' . "\n\n";

return compact( 'title', 'content' );
