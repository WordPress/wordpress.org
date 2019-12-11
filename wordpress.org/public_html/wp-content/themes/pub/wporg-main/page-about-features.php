<?php
/**
 * Template Name: About -> Features
 *
 * Page template for displaying the Features page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/requirements' => _x( 'Requirements', 'Page title', 'wporg' ),
	'about/features'     => _x( 'Features', 'Page title', 'wporg' ),
	'about/security'     => _x( 'Security', 'Page title', 'wporg' ),
	'about/roadmap'      => _x( 'Roadmap', 'Page title', 'wporg' ),
	'about/history'      => _x( 'History', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header row">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
						<?php
						printf(
							/* translators: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
							esc_html__( 'WordPress powers more than %s%% of the web &mdash; a figure that rises every day. Everything from simple websites, to blogs, to complex portals and enterprise websites, and even applications, are built with WordPress.', 'wporg' ),
							esc_html( number_format_i18n( WP_MARKET_SHARE ) )
						);
						?>
					</p>
					<p><?php esc_html_e( 'WordPress combines simplicity for users and publishers with under-the-hood complexity for developers. This makes it flexible while still being easy-to-use. The following is a list of some of the features that come as standard with WordPress; however, there are literally thousands of plugins that extend what WordPress does, so the actual functionality is nearly limitless. You are also free to do whatever you like with the WordPress code, extend it or modify in any way or use it for commercial projects without any licensing fees. That is the beauty of free software, free refers not only to price but also the freedom to have complete control over it.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'Here are some of the features that we think that you&#8217;ll love.', 'wporg' ); ?></p>

					<ul>
						<li><strong><?php echo esc_html_x( 'Simplicity', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'Simplicity makes it possible for you to get online and get publishing, quickly. Nothing should get in the way of you getting your website up and your content out there. WordPress is built to make that happen.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Flexibility', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'With WordPress, you can create any type of website you want: a personal blog or website, a photoblog, a business website, a professional portfolio, a government website, a magazine or news website, an online community, even a network of websites. You can make your website beautiful with themes, and extend it with plugins. You can even build your very own application.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Publish with Ease', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'If you&#8217;ve ever created a document, you&#8217;re already a whizz at creating content with WordPress. You can create Posts and Pages, format them easily, insert media, and with the click of a button your content is live and on the web.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Publishing Tools', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'WordPress makes it easy for you to manage your content. Create drafts, schedule publication, and look at your post revisions. Make your content public or private, and secure posts and pages with a password.', 'wporg' ); ?>
						</li>
						<li><strong>
							<?php
							printf(
								'<a href="%s">%s</a>',
								esc_url( __( 'https://wordpress.org/support/article/roles-and-capabilities/', 'wporg' ) ),
								esc_html_x( 'User Management', 'Features page: Section Header', 'wporg' )
							);
							?>
							</strong><br>
							<?php esc_html_e( 'Not everyone requires the same access to your website. Administrators manage the site, editors work with content, authors and contributors write that content, and subscribers have a profile that they can manage. This lets you have a variety of contributors to your website, and let others simply be part of your community.', 'wporg' ); ?>
						</li>
						<li><strong>
							<?php
							printf(
								'<a href="%s">%s</a>',
								esc_url( __( 'https://wordpress.org/support/article/media-library-screen/', 'wporg' ) ),
								esc_html_x( 'Media Management', 'Features page: Section Header', 'wporg' )
							);
							?>
							</strong><br>
							<?php esc_html_e( 'They say a picture says a thousand words, which is why it&#8217;s important for you to be able to quickly and easily upload images and media to WordPress. Drag and drop your media into the uploader to add it to your website. Add alt text and captions, and insert images and galleries into your content. We&#8217;ve even added a few image editing tools you can have fun with.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Full Standards Compliance', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'Every piece of WordPress generated code is in full compliance with the standards set by the W3C. This means that your website will work in today&#8217;s browser, while maintaining forward compatibility with the next generation of browser. Your website is a beautiful thing, now and in the future.', 'wporg' ); ?>
						</li>
						<li><strong>
							<?php
							printf(
								'<a href="%s">%s</a>',
								esc_url( home_url( '/themes/' ) ),
								esc_html_x( 'Easy Theme System', 'Features page: Section Header', 'wporg' )
							);
							?>
							</strong><br>
							<?php esc_html_e( 'WordPress comes bundled with three default themes, but if they aren&#8217;t for you there&#8217;s a theme directory with thousands of themes for you to create a beautiful website. None of those to your taste? Upload your own theme with the click of a button. It only takes a few seconds for you to give your website a complete makeover.', 'wporg' ); ?>
						</li>
						<li><strong>
							<?php
							printf(
								'<a href="%s">%s</a>',
								esc_url( home_url( '/plugins/' ) ),
								esc_html_x( 'Extend with Plugins', 'Features page: Section Header', 'wporg' )
							);
							?>
							</strong><br>
							<?php
							printf(
								/* translators: %s: Link to Plugin Directory */
								wp_kses_post( __( 'WordPress comes packed with a lot of features for every user. For every feature that&#8217;s not in WordPress core, there&#8217;s a <a href="%s">plugin directory</a> with thousands of plugins. Add complex galleries, social networking, forums, social media widgets, spam protection, calendars, fine-tune controls for search engine optimization, and forms.', 'wporg' ) ),
								esc_url( home_url( '/plugins/' ) )
							);
							?>
						</li>
						<li><strong>
							<?php
							printf(
								'<a href="%s">%s</a>',
								esc_url( __( 'https://wordpress.org/support/article/comments-in-wordpress/', 'wporg' ) ),
								esc_html_x( 'Built-in Comments', 'Features page: Section Header', 'wporg' )
							);
							?>
							</strong><br>
							<?php esc_html_e( 'Your blog is your home, and comments provide a space for your friends and followers to engage with your content. WordPress&#8217;s comment tools give you everything you need to be a forum for discussion and to moderate that discussion.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Search Engine Optimized', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: Link to Plugin Directory search for SEO */
								wp_kses_post( __( 'WordPress is optimized for search engines right out of the box. For more fine-grained SEO control, there are plenty of <a href="%s">SEO plugins</a> to take care of that for you.', 'wporg' ) ),
								esc_url( home_url( '/plugins/search/seo/' ) )
							);
							?>
						</li>
						<li><strong>
							<?php
							printf(
								'<a href="%s">%s</a>',
								esc_url( __( 'https://wordpress.org/support/article/installing-wordpress-in-your-language/' ) ),
								esc_html_x( 'Use WordPress in Your Language', 'Features page: Section Header', 'wporg' )
							);
							?>
							</strong><br>
							<?php
							printf(
								/* translators: Link to Polyglots teams */
								wp_kses_post( __( 'WordPress is available in more than 70 languages. If you or the person you&#8217;re building the website for would prefer to use WordPress in a language other than English, <a href="%s">that&#8217;s easy to do</a>.', 'wporg' ) ),
								'https://make.wordpress.org/polyglots/teams/'
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Easy Installation and Upgrades', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: Link to "Automated Installation" support article */
								wp_kses_post( __( 'WordPress has always been easy to install and upgrade. Plenty of web hosts offer one-click <a href="%s">WordPress installers</a> that let you install WordPress with, well, just one click! Or, if you&#8217;re happy using an FTP program, you can create a database, upload WordPress using FTP, and run the installer.', 'wporg' ) ),
								esc_url( __( 'https://wordpress.org/support/article/automated-installation/', 'wporg' ) )
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Importers', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: Link to "Importing Content" support article */
								wp_kses_post( __( 'Using blog or website software that you aren&#8217;t happy with? Running your blog on a hosted service that&#8217;s about to shut down? WordPress comes with importers for Blogger, LiveJournal, Movable Type, TypePad, Tumblr, and WordPress. If you&#8217;re ready to make the move, <a href="%s">we&#8217;ve made it easy for you</a>.', 'wporg' ) ),
								esc_url( __( 'https://wordpress.org/support/article/importing-content/', 'wporg' ) )
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Own Your Data', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'Hosted services come and go. If you&#8217;ve ever used a service that disappeared, you know how traumatic that can be. If you&#8217;ve ever seen adverts appear on your website, you&#8217;ve probably been pretty annoyed. Using WordPress means no one has access to your content. Own your data, all of it &mdash; your website, your content, your data.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Freedom', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php esc_html_e( 'WordPress is licensed under the GPL which was created to protect your freedoms. You are free to use WordPress in any way you choose: install it, use it, modify it, distribute it. Software freedom is the foundation that WordPress is built on.', 'wporg' ); ?>
						</li>
						<li><strong><?php echo esc_html_x( 'Community', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: 1: Link to support forums; 2: Link to WordCamp Central */
								wp_kses_post( __( 'As the most popular open source CMS on the web, WordPress has a vibrant and supportive community. Ask a question on the <a href="%1$s">support forums</a> and get help from a volunteer, attend a <a href="%2$s">WordCamp</a> or Meetup to learn more about WordPress, read blogs posts and tutorials about WordPress. Community is at the heart of WordPress, making it what it is today.', 'wporg' ) ),
								esc_url( __( 'https://wordpress.org/support/forums/', 'wporg' ) ),
								'https://central.wordcamp.org/'
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Contribute', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: %s: https://make.wordpress.org/ */
								wp_kses_post( __( 'You can be WordPress too! Help to build WordPress, answer questions on the support forums, write documentation, translate WordPress into your language, speak at a WordCamp, write about WordPress on your blog. Whatever your skill, <a href="%s">we&#8217;d love to have you</a>!', 'wporg' ) ),
								'https://make.wordpress.org/'
							);
							?>
						</li>
					</ul>

					<h2 id="developer" ><?php echo esc_html_x( 'Developer Features', 'Features page: Section Header', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'For developers, we&#8217;ve got lots of goodies packed under the hood that you can use to extend WordPress in whatever direction takes your fancy.', 'wporg' ); ?></p>

					<ul>
						<li><strong><?php echo esc_html_x( 'Plugin System', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: 1: Link to Developer Hub; 2: Link to Plugin Directory */
								wp_kses_post( __( 'The <a href="%1$s">WordPress APIs</a> make it possible for you to create plugins to extend WordPress. WordPress&#8217;s extensibility lies in the thousands of hooks at your disposal. Once you&#8217;ve created your plugin, we&#8217;ve even got a <a href="%2$s">plugin repository</a> for you to host it on.', 'wporg' ) ),
								'https://developer.wordpress.org/',
								esc_url( home_url( '/plugins/' ) )
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Theme System', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: 1: Link to Theme Developer Handbook; 2: Link to Theme Directory */
								wp_kses_post( __( 'Create WordPress themes for clients, other WordPress users, or yourself. WordPress provides the extensibility to <a href="%1$s">create themes</a> as simple or as complex as you wish. If you want to give your theme away for free you can give it to users in the <a href="%2$s">theme repository</a>.', 'wporg' ) ),
								'https://developer.wordpress.org/themes/',
								esc_url( home_url( '/themes/' ) )
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Application Framework', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: %s: Link to REST API Handbook */
								wp_kses_post( __( 'If you want to build an application, WordPress can help with that too. WordPress provides a lot of the features under the hood that your app will need: translations, user management, HTTP requests, databases, URL routing and much, much more. You can also use our <a href="%s">REST API</a> to interact with it.', 'wporg' ) ),
								'https://developer.wordpress.org/rest-api/'
							);
							?>
						</li>
						<li><strong><?php echo esc_html_x( 'Custom Content Types', 'Features page: Section Header', 'wporg' ); ?></strong><br>
							<?php
							printf(
								/* translators: 1: Link to Plugin Handbook page about custom post types; 2: Link to Plugin Handbook page about custom taxonomies; 3: Link to Plugin Handbook page about metadata */
								wp_kses_post( __( 'WordPress comes with default content types, but for more flexibility you can add a few lines of code to create your own <a href="%1$s">custom post types</a>, <a href="%2$s">taxonomies</a>, and <a href="%3$s">metadata</a>. Take WordPress in whatever direction you wish.', 'wporg' ) ),
								'https://developer.wordpress.org/plugins/post-types/',
								'https://developer.wordpress.org/plugins/taxonomies/',
								'https://developer.wordpress.org/plugins/metadata/'
							);
							?>
						</li>
					</ul>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
