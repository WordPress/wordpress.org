<?php

$localised_domain = parse_url( home_url( '/' ), PHP_URL_HOST );

$title = __( 'Say Hello to the New Editor', 'wporg' );

$content = '<!-- wp:paragraph {"customTextColor":"#6c7781","customFontSize":17} -->
<p style="color:#6c7781;font-size:17px" class="has-text-color"><em>' . esc_html__( 'It&#8217;s a whole new way to use WordPress. Try it right here!', 'wporg' ) . '</em></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":97629,"align":"full"} -->
<figure class="wp-block-image alignfull"><img src="https://wordpress.org/gutenberg/files/2018/07/Screenshot-4-1.png" alt="" class="wp-image-97629"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'We call the new editor Gutenberg. The entire editing experience has been rebuilt for media rich pages and posts. Experience the flexibility that blocks will bring, whether you are building your first site, or write code for a living.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:gallery {"align":"wide","columns":4,"className":"gutenberg-landing\u002d\u002dfeatures-grid"} -->
<ul class="wp-block-gallery alignwide columns-4 is-cropped gutenberg-landing--features-grid"><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Plugin-1-1.gif" alt=""/><figcaption>' . esc_html_x( 'Do more with fewer plugins.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Layout-3.gif" alt=""/><figcaption>' . esc_html_x( 'Create modern, multimedia-heavy layouts.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Devices-1-1.gif" alt=""/><figcaption>' . esc_html_x( 'Work across all screen sizes and devices.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li><li class="blocks-gallery-item"><figure><img src="https://wordpress.org/gutenberg/files/2018/07/Visual-1.gif" alt=""/><figcaption>' . esc_html_x( 'Trust that your editor looks like your website.', 'Image Caption', 'wporg' ) . '</figcaption></figure></li></ul>
<!-- /wp:gallery -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:wporg/download-button -->
<div class="wp-block-wporg-download-button wp-block-button aligncenter"><a class="wp-block-button__link has-background has-strong-blue-background-color" href="' . "https://{$localised_domain}/download/" . '" style="background-color:rgb(0,115,170);color:#fff">' . esc_html__( 'Try it Today in WordPress', 'wporg' ) . '</a></div>
<!-- /wp:wporg/download-button -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="has-small-font-size gutenberg-landing--button-disclaimer"><em>' .
	sprintf(
		/* translators: 1: The URL to the Classic Editor plugin. 2: The URL to the Gutenberg plugin.  */
		wp_kses_post( __( 'Gutenberg is available as part of WordPress 5.0 and later. The <a href="%1$s">Classic Editor</a> plugin allows users to switch back to the previous editor if needed. Future development will continue in the <a href="%2$s">Gutenberg</a> plugin.', 'wporg' ) ),
		esc_url( "https://{$localised_domain}/plugins/classic-editor/" ),
		esc_url( "https://{$localised_domain}/plugins/gutenberg/" )
	) . '</em></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:heading {"align":"left"} -->
<h2 style="text-align:left">' . esc_html__( 'Meet your new best friends, Blocks', 'wporg' ) . '</h2>
<!-- /wp:heading -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Blocks are a great new tool for building engaging content. With blocks, you can insert, rearrange, and style multimedia content with very little technical knowledge. Instead of using custom code, you can add a block and focus on your content.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":358} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/07/Insert-Block-2-1.gif" alt="" class="wp-image-358"/></figure>
<!-- /wp:image -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'Without being an expert developer, you can build your own custom posts and pages. Here&#8217;s a selection of the default blocks included with Gutenberg:', 'wporg' ) . '</p>
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
<p style="text-align:left">' . esc_html__( 'A single block is nice&#8212;reliable, clear, distinct. Discover the flexibility to use media and content, side by side, driven by your vision.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:image {"id":98363} -->
<figure class="wp-block-image"><img src="https://wordpress.org/gutenberg/files/2018/08/Builder.gif" alt="" class="wp-image-98363"/></figure>
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
<p style="text-align:left">' . esc_html__( 'Gutenberg was developed on GitHub using the WordPress REST API, JavaScript, and React.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="has-small-font-size"><a href="https://developer.wordpress.org/block-editor/key-concepts/">' . esc_html__( 'Learn more', 'wporg' ) . '</a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->' . "\n\n";

$content .= '<!-- wp:column -->
<div class="wp-block-column"><!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left"><strong>' . esc_html__( 'Designed for compatibility.', 'wporg' ) . '</strong></p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left"} -->
<p style="text-align:left">' . esc_html__( 'We recommend migrating features to blocks, but support for existing WordPress functionality remains. There will be transition paths for shortcodes, meta-boxes, and Custom Post Types.', 'wporg' ) . '</p>
<!-- /wp:paragraph -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"left","fontSize":"small"} -->
<p style="text-align:left" class="has-small-font-size"><a href="https://developer.wordpress.org/block-editor/contributors/faq/">' . esc_html__( 'Learn more', 'wporg' ) . '</a></p>
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
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . wp_kses_post( __( '"This will make running your own blog a viable alternative again."', 'wporg' ) ) . '</p><cite>&#8212; <a href="https://twitter.com/azumbrunnen_/status/1019347243084800005">' . esc_html__( 'Adrian Zumbrunnen', 'wporg' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . esc_html__( '"The web up until this point has been confined to some sort of rectangular screen. But that is not how it&#8217;s going to be. Gutenberg has the potential of moving us into the next time."', 'wporg' ) . '</p><cite>&#8212; <a href="https://wordpress.tv/2017/12/10/morten-rand-hendriksen-gutenberg-and-the-wordpress-of-tomorrow/">' . esc_html__( 'Morten Rand-Hendriksen', 'wporg' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:quote {"align":"left","className":" is-style-large"} -->
<blockquote style="text-align:left" class="wp-block-quote  is-style-large"><p>' . esc_html__( '"The Gutenberg editor has some great assets that could genuinely help people to write better texts."', 'wporg' ) . '</p><cite>&#8212; <a href="https://yoast.com/writing-with-gutenberg/">' . esc_html__( 'Marieke van de Rakt', 'wporg' ) . '</a></cite></blockquote>
<!-- /wp:quote -->' . "\n\n";

$content .= '<!-- wp:spacer -->
<div style="height:100px" class="wp-block-spacer" aria-hidden="true"></div>
<!-- /wp:spacer -->' . "\n\n";

$content .= '<!-- wp:wporg/download-button -->
<div class="wp-block-wporg-download-button wp-block-button aligncenter"><a class="wp-block-button__link has-background has-strong-blue-background-color" href="' . "https://{$localised_domain}/download/" . '" style="background-color:rgb(0,115,170);color:#fff">' . esc_html__( 'Try it Today in WordPress', 'wporg' ) . '</a></div>
<!-- /wp:wporg/download-button -->' . "\n\n";

$content .= '<!-- wp:paragraph {"align":"center","fontSize":"small","className":"gutenberg-landing\u002d\u002dbutton-disclaimer"} -->
<p style="text-align:center" class="has-small-font-size gutenberg-landing--button-disclaimer"><em>' .
	sprintf(
		/* translators: 1: The URL to the Classic Editor plugin. 2: The URL to the Gutenberg plugin.  */
		wp_kses_post( __( 'Gutenberg is available as part of WordPress 5.0 and later. The <a href="%1$s">Classic Editor</a> plugin allows users to switch back to the previous editor if needed. Future development will continue in the <a href="%2$s">Gutenberg</a> plugin.', 'wporg' ) ),
		esc_url( "https://{$localised_domain}/plugins/classic-editor/" ),
		esc_url( "https://{$localised_domain}/plugins/gutenberg/" )
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
	<li><a href="https://developer.wordpress.org/block-editor/contributors/design/">' . esc_html__( 'Gutenberg Design Principles', 'wporg' ) . '</a></li>
	<li><a href="https://make.wordpress.org/core/tag/gutenberg/">' . esc_html__( 'Development updates on make.wordpress.org', 'wporg' ) . '</a></li>
	<li><a href="https://wordpress.tv/?s=gutenberg">' . esc_html__( 'WordPress.tv Talks about Gutenberg', 'wporg' ) . '</a></li>
	<li><a href="https://developer.wordpress.org/block-editor/contributors/faq/">' . esc_html__( 'FAQs', 'wporg' ) . '</a></li>
</ul>
<!-- /wp:list -->' . "\n\n";

return compact( 'title', 'content' );
