<?php
/**
 * Template Name: Features
 *
 * Page template for displaying the Features page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

$GLOBALS['menu_items'] = [
	'requirements' => __( 'Requirements', 'wporg' ),
	'features'     => __( 'Features', 'wporg' ),
	'security'     => __( 'Security', 'wporg' ),
	'roadmap'      => __( 'Roadmap', 'wporg' ),
	'history'      => __( 'History', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

add_filter( 'jetpack_open_graph_tags', function( $tags ) {
	$tags['og:title']            = _esc_html__( 'WordPress Features', 'wporg' );
	/* translators: WordPress market share: 29%; */
	$tags['og:description']      = sprintf( _esc_html__( 'Discover why WordPress powers more than %s of the web. WordPress is a simple, flexible, user-friendly platform, with key features that include media management, SEO, and endless options for customization. More than 50,000 plugins extend the core functionality of WordPress even more. Build your site today.', 'wporg' ), WP_MARKET_SHARE . '%' );
	$tags['twitter:text:title']  = $tags['og:title'];
	$tags['twitter:description'] = $tags['og:description'];

	return $tags;
} );

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php _esc_html_e( 'Features', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<?php
						/* translators: WordPress market share: 29%; */
						printf( _esc_html__( 'WordPress powers more than %s of the web &mdash; a figure that rises every day. Everything from simple websites, to blogs, to complex portals and enterprise websites, and even applications, are built with WordPress.', 'wporg' ), esc_html( WP_MARKET_SHARE . '%' ) );
						?>
					</p>

					<p><?php _esc_html_e( 'WordPress combines simplicity for users and publishers with under-the-hood complexity for developers. This makes it flexible while still being easy-to-use.', 'wporg' ); ?></p>

					<p><?php _esc_html_e( 'The following is a list of some of the features that come as standard with WordPress; however, there are literally thousands of plugins that extend what WordPress does, so the actual functionality is nearly limitless. You are also free to do whatever you like with the WordPress code, extend it or modify in any way or use it for commercial projects without any licensing fees. That is the beauty of free software, free refers not only to price but also the freedom to have complete control over it.', 'wporg' ); ?></p>

					<p><?php _esc_html_e( 'Here are some of the features that we think that you&#8217;ll love.', 'wporg' ); ?></p>

					<ul>
						<li><strong><?php _esc_html_e( 'Simplicity', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'Simplicity makes it possible for you to get online and get publishing, quickly. Nothing should get in the way of you getting your website up and your content out there. WordPress is built to make that happen.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Flexibility', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'With WordPress, you can create any type of website you want: a personal blog or website, a photoblog, a business website, a professional portfolio, a government website, a magazine or news website, an online community, even a network of websites. You can make your website beautiful with themes, and extend it with plugins. You can even build your very own application.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Publish with Ease', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'If you&#8217;ve ever created a document, you&#8217;re already a whizz at creating content with WordPress. You can create Posts and Pages, format them easily, insert media, and with the click of a button your content is live and on the web.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Publishing Tools', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'WordPress makes it easy for you to manage your content. Create drafts, schedule publication, and look at your post revisions. Make your content public or private, and secure posts and pages with a password.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'User Management', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'Not everyone requires the same access to your website. Administrators manage the site, editors work with content, authors and contributors write that content, and subscribers have a profile that they can manage. This lets you have a variety of contributors to your website, and let others simply be part of your community.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Media Management', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'They say a picture says a thousand words, which is why it&#8217;s important for you to be able to quickly and easily upload images and media to WordPress. Drag and drop your media into the uploader to add it to your website. Add alt text, captions, and titles, and insert images and galleries into your content. We&#8217;ve even added a few image editing tools you can have fun with.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Full Standards Compliance', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'Every piece of WordPress generated code is in full compliance with the standards set by the W3C. This means that your website will work in today&#8217;s browser, while maintaining forward compatibility with the next generation of browser. Your website is a beautiful thing, now and in the future.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Easy Theme System', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'WordPress comes bundled with two default themes, but if they aren&#8217;t for you there&#8217;s a theme directory with thousands of themes for you to create a beautiful website. None of those to your taste? Upload your own theme with the click of a button. It only takes a few seconds for you to give your website a complete makeover.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Extend with Plugins', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'WordPress comes packed full of features for every user, for every other feature there&#8217;s a plugin directory with thousands of plugins. Add complex galleries, social networking, forums, social media widgets, spam protection, calendars, fine-tune controls for search engine optimization, and forms.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Built-in Comments', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'Your blog is your home, and comments provide a space for your friends and followers to engage with your content. WordPress&#8217;s comment tools give you everything you need to be a forum for discussion and to moderate that discussion.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Search Engine Optimized', 'wporg' ); ?></strong>
							<?php
							/* translators: Link to Plugin Directory search for SEO */
							printf( wp_kses_post( ___( 'WordPress is optimized for search engines right out of the box. For more fine-grained SEO control, there are plenty of <a href="%s">SEO plugins</a> to take care of that for you.', 'wporg' ) ), esc_url( 'https://wordpress.org/plugins/search/SEO/' ) );
							?>
						</li>
						<li><strong><?php _esc_html_e( 'Multilingual', 'wporg' ); ?></strong>
							<?php
							/* translators: Link to polyglots teams */
							printf( wp_kses_post( ___( 'WordPress is available in more than 70 languages. If you or the person you&#8217;re building the website for would prefer to use WordPress in a language other than English, <a href="%s">that&#8217;s easy to do</a>.', 'wporg' ) ), esc_url( 'https://make.wordpress.org/polyglots/teams/' ) );
							?>
						</li>
						<li><strong><?php _esc_html_e( 'Easy Installation and Upgrades', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'WordPress has always been easy to install and upgrade. If you&#8217;re happy using an FTP program, you can create a database, upload WordPress using FTP, and run the installer. Not familiar with FTP? Plenty of web hosts offer one-click WordPress installers that let you install WordPress with, well, just one click!', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Importers', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'Using blog or website software that you aren&#8217;t happy with? Running your blog on a hosted service that&#8217;s about to shut down? WordPress comes with importers for blogger, LiveJournal, Movable Type, TypePad, Tumblr, and WordPress. If you&#8217;re ready to make the move, we&#8217;ve made it easy for you.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Own Your Data', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'Hosted services come and go. If you&#8217;ve ever used a service that disappeared, you know how traumatic that can be. If you&#8217;ve ever seen adverts appear on your website, you&#8217;ve probably been pretty annoyed. Using WordPress means no one has access to your content. Own your data, all of it &mdash; your website, your content, your data.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Freedom', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'WordPress is licensed under the GPL which was created to protect your freedoms. You are free to use WordPress in any way you choose: install it, use it, modify it, distribute it. Software freedom is the foundation that WordPress is built on.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Community', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'As the most popular open source CMS on the web, WordPress has a vibrant and supportive community. Ask a question on the support forums and get help from a volunteer, attend a WordCamp or Meetup to learn more about WordPress, read blogs posts and tutorials about WordPress. Community is at the heart of WordPress, making it what it is today.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Contribute', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'You can be WordPress too! Help to build WordPress, answer questions on the support forums, write documentation, translate WordPress into your language, speak at a WordCamp, write about WordPress on your blog. Whatever your skill, we&#8217;d love to have you!', 'wporg' ); ?>
						</li>
					</ul>
					<h3><?php _esc_html_e( 'Developer Features', 'wporg' ); ?></h3>
					<p><?php _esc_html_e( 'For developers, we&#8217;ve got lots of goodies packed under the hood that you can use to extend WordPress in whatever direction takes your fancy.', 'wporg' ); ?></p>

					<ul>
						<li><strong><?php _esc_html_e( 'Plugin System', 'wporg' ); ?></strong>
							<?php
							/* translators: 1: Link to Codex page about APIs; 2: Link to Plugin Directory */
							printf( wp_kses_post( ___( 'The <a href="%1$s">WordPress APIs</a> make it possible for you to create plugins to extend WordPress. WordPress&#8217;s extensibility lies in the thousands of hooks at your disposal. Once you&#8217;ve created your plugin, we&#8217;ve even got a <a href="%2$s">plugin repository</a> for you to host it on.', 'wporg' ) ), esc_url( 'https://codex.wordpress.org/WordPress_APIs' ), esc_url( home_url( '/plugins/' ) ) );
							?>
						</li>
						<li><strong><?php _esc_html_e( 'Theme System', 'wporg' ); ?></strong>
							<?php
							/* translators: 1: Link to Codex page about APIs; 2: Link to Theme Directory */
							printf( wp_kses_post( ___( 'Create WordPress themes for clients, customers, and for WordPress users. The <a href="%1$s">WordPress API</a> provides the extensibility to create themes as simple or as complex as you wish. If you want to give your theme away for free you can give it to users in the <a href="%2$s">Theme Repository</a>', 'wporg' ) ), esc_url( 'https://codex.wordpress.org/WordPress_APIs' ), esc_url( home_url( '/themes/' ) ) );
							?>
						</li>
						<li><strong><?php _esc_html_e( 'Application Framework', 'wporg' ); ?></strong>
							<?php _esc_html_e( 'If you want to build an application, WordPress can help with that too. Under the hood WordPress provides a lot of the features that your app will need, things like translations, user management, HTTP requests, databases, URL routing and much, much more.', 'wporg' ); ?>
						</li>
						<li><strong><?php _esc_html_e( 'Custom Content Types', 'wporg' ); ?></strong>
							<?php
							/* translators: 1: Link to Codex page about Custom Post Types; 2: Link to Codex page about Custom Taxonomies; 3: Link to Codex page about Custom Fields */
							printf( wp_kses_post( ___( 'WordPress comes with default content types, but for more flexibility you can add a few lines of code to create your own <a href="%1$s">custom post types</a>, <a href="%2$s">taxonomies</a>, and <a href="%3$s">metadata</a>. Take WordPress in whatever direction you wish.', 'wporg' ) ), esc_url( 'https://codex.wordpress.org/Post_Types#Custom_Post_Types' ), esc_url( 'https://codex.wordpress.org/Taxonomies#Custom_Taxonomies' ), esc_url( 'https://codex.wordpress.org/Custom_Fields' ) );
							?>
						</li>
						<li><strong><?php _esc_html_e( 'The Latest Libraries', 'wporg' ); ?></strong>
							<?php
							/* translators: 1: Link to Developer Handbook page about default scripts */
							printf( wp_kses_post( ___( 'WordPress comes with the <a href="%s">latest script libraries</a> for you to make use of. These include jQuery, Plupload, Underscore.js and Backbone.js. We&#8217;re always on the lookout for new tools that developers can use to make a better experience for our users.', 'wporg' ) ), esc_url( 'https://developer.wordpress.org/reference/functions/wp_enqueue_script/#default-scripts-included-and-registered-by-wordpress' ) );
							?>
						</li>
					</ul>
				</section>
			</div><!-- .entry-content -->

			<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					esc_html__( 'Edit %s', 'wporg' ),
					the_title( '<span class="screen-reader-text">"', '"</span>', false )
				),
				'<footer class="entry-footer"><span class="edit-link">',
				'</span></footer><!-- .entry-footer -->'
			);
			?>
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
