<?php
header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>';
?>
<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
>

<channel>
	<title><?php _e( 'Theme Directory', 'wporg-themes' ); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss('url') ?></link>
	<description><?php _e( 'Free WordPress Themes', 'wporg-themes' ); ?></description>
	<lastBuildDate><?php echo gmdate( 'D, d M Y H:i:s +0000' ); ?></lastBuildDate>
	<language><?php bloginfo_rss( 'language' ); ?></language>
	<sy:updatePeriod>daily</sy:updatePeriod>
	<sy:updateFrequency>1</sy:updateFrequency>
	<?php

	$themes = wporg_themes_get_themes_for_query();
	foreach ( $themes['themes'] as $theme ) :
	?>
	<item>
		<title><?php echo esc_html( $theme->name ); ?></title>
		<link><?php echo home_url( $theme->slug . '/' ); ?></link>
		<pubDate><?php echo gmdate( 'D, d M Y H:i:s +0000', strtotime( $theme->last_updated ) ); ?></pubDate>
		<dc:creator><![CDATA[<?php echo esc_html( $theme->author->display_name ); ?>]]></dc:creator>
		<?php foreach ( $theme->tags as $tag ) : ?>
			<category><![CDATA[<?php echo esc_html( $tag ); ?>]]></category>
		<?php endforeach; ?>
		<guid isPermaLink="true"><?php echo home_url( $theme->slug . '/' ); ?></guid>
		<description><![CDATA[<?php echo esc_html( $theme->description ); ?>]]></description>
		<content:encoded><![CDATA[<?php echo esc_html( $theme->description ); ?>]]></content:encoded>
	</item>
	<?php endforeach; ?>
</channel>
</rss>