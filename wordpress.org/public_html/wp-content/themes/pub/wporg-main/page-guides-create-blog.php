<?php
/**
 * Template Name: Guides -> Create a Blog
 *
 * Page template for displaying the Create a Blog page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'guides/create-blog' => _x( 'Create a Blog', 'Page title', 'wporg' ),
	//'guides/first-time'  => _x( 'First Time', 'Page title', 'wporg' ),
	'guides/moving-blog' => _x( 'Moving a Blog', 'Page title', 'wporg' ),
	'guides/adding-blog' => _x( 'Adding a Blog', 'Page title', 'wporg' ),
];

$plugin_search_terms = [
	___( 'Sliders and Galleries', 'wporg' ),
	___( 'Social Sharing', 'wporg' ),
	___( 'Search Engine Optimization', 'wporg' ),
	___( 'Drag and Drop Editors', 'wporg' ),
	___( 'Forms', 'wporg' ),
	___( 'Backup and Upgrade', 'wporg' ),
	___( 'Ecommerce', 'wporg' ),
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
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php _esc_html_e( 'Create a Blog', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php _esc_html_e( 'Welcome to our create a blog guide. If you haven&#8217;t used WordPress before, we are really happy you are giving us a chance. A few things you may want to know about this software and the community behind it:', 'wporg' ); ?></p>

					<ul>
						<li><?php _esc_html_e( 'Consistently chosen by the world&#8217;s top bloggers.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Easy to get started but also easy to extend when you are ready.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Open Source&mdash;you retain complete ownership of your designs and content.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Backed by an enormous community including hosts, developers, artists, content creators, and more.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Unlimited growth, small blogs and huge blogs run on WordPress.', 'wporg' ); ?></li>
						<li>
							<?php
							printf(
								/* translators: WordPress market share: 30 - Note: The following percent sign is '%%' for escaping purposes; */
								esc_html___( 'Over %s%% of the web trusts WordPress to power its web presence including millions of blogs.', 'wporg' ),
								number_format_i18n( WP_MARKET_SHARE )
							);
							?>
						</li>
					</ul>

					<p><?php _esc_html_e( 'With that out of the way, let&#8217;s get you going on creating a blog. Different goals and situations may take you down a different path, but we have the following sections to help address the most common situations.', 'wporg' ); ?></p>
				</section>

				<section class="areas row gutters between">
					<div class="col-4">
						<p><?php _esc_html_e( 'First time making a blog? Let&#8217;s get you up and running on WordPress.', 'wporg' ); ?></p>
						<a href="<?php echo esc_url( site_url( '/' ) ); ?>"><?php _esc_html_e( 'Learn more', 'wporg' ); ?></a>
					</div>
					<div class="col-4">
						<p><?php _esc_html_e( 'Moving your blog over to WordPress? Welcome to being out of the box!', 'wporg' ); ?></p>
						<a href="<?php echo esc_url( site_url( '/' ) ); ?>"><?php _esc_html_e( 'Learn more', 'wporg' ); ?></a>

					</div>
					<div class="col-4">
						<p><?php _esc_html_e( 'Already running a site and needing to add a blog to an existing site? Easy.', 'wporg' ); ?></p>
						<a href="<?php echo esc_url( site_url( '/' ) ); ?>"><?php _esc_html_e( 'Learn more', 'wporg' ); ?></a>
					</div>
				</section>

				<section class="col-8">
					<h2><?php _esc_html_e( 'First time making a blog', 'wporg' ); ?></h2>
					<p><?php _esc_html_e( 'WordPress blog sites are made up of the look of the site (called Themes), the individual blog Posts, the Categories of those posts, and additional functionality you might want, called Plugins. This is all controlled inside the WordPress &#8220;admin panel&#8221; that is running on a host.', 'wporg' ); ?></p>
					<p>
						<?php
						printf(
							/* translators: URLs to setup guide */
							wp_kses_post( ___( 'If you don&#8217;t have a WordPress yet, the first step is to get one from your host or to get a host. If you have a modern host, they will have a WordPress auto installer or, if you are a bit technical, <a href="%s">you can install it yourself with this guide</a>. If you need hosting there are three types of hosting plans you will see.', 'wporg' ) ),
							esc_url( '' )
						);
						?>
					</p>

					<ul>
						<li><?php _esc_html_e( 'General Hosting - these plans can run lots of software and will have many different ways of editing a website or a blog. If you really want to learn the ins and outs of blogging and running websites, you probably want a general hosting account.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'WordPress specific hosting plans - these plans will be all about WordPress. A good host will likely have a set of free themes, recommended Plugins to help you with your blog, and will have people on staff ready to help you. These plans are usually best if you are really focused on launching your blog and you have some budget.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Free WordPress providers - these providers give you access to WordPress in a more limited fashion, but are great if you really don&#8217;t have a budget and want to quickly get a simple blog up and running.', 'wporg' ); ?></li>
					</ul>

					<p>
						<?php
						printf(
						/* translators: URLs to setup guide */
							wp_kses_post( ___( 'Visit our <a href="%s">web hosting page</a> for more info. If you are really pressed for time or you are just checking out WordPress, look for hosts that offer a free trial.', 'wporg' ) ),
							esc_url( 'https://wordpress.org/hosting/' )
						);
						?>
					</p>
					<p><?php _esc_html_e( 'Now that is taken care of, let&#8217;s move on to the next step, selecting a theme. WordPress will come preloaded with a couple of blogging oriented themes. These may fit your needs but under the Add Theme section is a world of options.', 'wporg' ); ?></p>

					<ul>
						<li><?php _esc_html_e( 'Under the themes panel, click the &#8220;Add New&#8221; button at the top, or the &#8220;Add New Theme&#8221; area on the page.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You will see the WordPress theme repository, where you can search through thousands of free, beautiful templates to use. Or, if you&#8217;ve purchased a theme or acquired it through some other means, use the &#8220;Upload Theme&#8221; button at the top of the page to install it on your WordPress installation.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Selecting the &#8220;perfect&#8221; theme may seem daunting due to the sheer amount of options, but you can easily switch between themes if you find one that better meets your needs.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Once you&#8217;ve chosen a theme, move your cursor over the theme&#8217;s preview image to reveal the &#8220;Install&#8221; and &#8220;Preview&#8221; buttons. If you want to see what the theme will look like in a live setting, select &#8220;Preview&#8221;. Otherwise, click the &#8220;Install&#8221; button.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Once the theme is installed, you still have to activate it to make it live. Simply return to the &#8220;Themes&#8221; sub-option under &#8220;Appearance&#8221;, find your newly installed theme and click &#8220;Activate&#8221;.', 'wporg' ); ?></li>
					</ul>

					<p><?php _esc_html_e( 'Next, you&#8217;ll want to customize the look and feel of your website, including adding titles:', 'wporg' ); ?></p>

					<ul>
						<li><?php _esc_html_e( 'The WordPress Customizer is a powerful tool that allows you to easily make changes to your website and see the immediate results in the preview window.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You can access the Customizer by clicking on the Appearance &rarr; Customize option from your WordPress&#8217;s administration panel, or navigating to any page while logged in as an administrator and clicking the &#8220;Customize&#8221; option on the top bar.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Once you’re in the Customizer, you can easily change your site title and site tagline by clicking the &#8220;Site Identity&#8221; option on the left sidebar.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'From there, you can navigate and experiment with the other customizable options and see your changes in the right preview panel. For example, if you want to change the fonts across your entire website, click the &#8220;Typography&#8221; option.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Advanced users can also add custom CSS styling to your website under the &#8220;Custom CSS&#8221; option and preview how those changes will affect your website before committing.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'When you’re finished, click the &#8220;Publish&#8221; button at the top of the sidebar. Or, if you change your mind, simply click the &#8220;X&#8221; button in the upper left corner.', 'wporg' ); ?></li>
					</ul>

					<p><?php _esc_html_e( 'Almost done here. Next, you will want to create some categories for all the content you will be creating.', 'wporg' ); ?></p>
					<ul>
						<li><?php _esc_html_e( 'Categories are assigned to blog posts for organizational purposes and help to provide your readers with a general idea of the post&#8217;s content.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Blog posts can be filtered and displayed by their categories, making it easy show the right content to your readers.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Categories can be assigned at the moment of creation of the post, no need to create them ahead of time.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You can create menu items that link to your individual categories to make navigation for your users.', 'wporg' ); ?></li>
					</ul>

					<p><?php _esc_html_e( 'Next, you&#8217;ll create your first post! The WordPress post editor is quick and easy to use.', 'wporg' ); ?></p>
					<ul>
						<li><?php _esc_html_e( 'If you&#8217;ve used a word processing program before, you&#8217;ll most likely find the WordPress visual editor&#8217;s interface very familiar.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Just like your favorite word processing program, you can type in the text entry pane and easily style your post using the editor controls in the toolbar at the top of the editor interface.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'If you want to add, edit and resize images (and image galleries) or embed sounds simply click the &#8220;Add Media&#8221; button and upload your files to your media library, select it, then click the &#8220;Insert into post&#8221; button on the bottom right of the interface.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'If you&#8217;re familiar with HTML, you can easily switch to the raw text editor by clicking the &#8220;Text&#8221; tab on the right side of the editor interface.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'When you&#8217;re finished editing your post, click the &#8220;Publish&#8221; button on the right sidebar to make it live. If you need to work on it later, click the &#8220;Save Draft&#8221; button. To preview your post, click the &#8220;Preview&#8221; button.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You can also change the status of your post, or schedule the post to automatically publish at a later date by using the options in the &#8220;Publish&#8221; section of the right sidebar.', 'wporg' ); ?></li>
					</ul>

					<p>
						<?php
						printf(
							/* translators: URL to WordCamp Central */
							wp_kses_post( ___( 'Lastly, remember to have fun and bring your friends in on it. If you are really excited and want to go to the next level, come out to meet some other bloggers in person at one of the many <a href="%s">WordCamps</a> around the world.', 'wporg' ) ),
							esc_url( 'https://central.wordcamp.org/' )
						);
						?>
					</p>

					<h3><?php _esc_html_e( 'Starting your blog in 6 easy steps', 'wporg' ); ?></h3>
					<p><?php _esc_html_e( 'To summarize, here is a quick list on how to get a blog started:', 'wporg' ); ?></p>
					<ol>
						<li><?php _esc_html_e( 'Get hosting from a modern host and use their auto installer for WordPress.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Select a blogging theme from Add Theme.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Customize the Site Title and the Header.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Click on Posts Add New and create a new blog post.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Create some categories so it is easy to organize your content.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Have fun and tell your friends!', 'wporg' ); ?></li>
					</ol>

					<p>
						<?php
						printf(
							/* translators: URL to Plugin Directory */
							wp_kses_post( ___( 'Once you are feeling confident, the real power of WordPress comes in with using Plugins to <a href="%s">add functionality</a>.', 'wporg' ) ),
							esc_url( home_url( '/plugins/' ) )
						);
						?>
					</p>
					<h2><?php _esc_html_e( 'Adding Functionality with Plugins', 'wporg' ); ?></h2>
					<p><?php _esc_html_e( 'One of the most powerful parts of WordPress is the ability to add selected functionality or change how WordPress works through Plugins. Just look for &#8220;Add New&#8221; under &#8220;Plugins&#8221; in your WordPress admin menu. There are thousands of plugins though, so if you are just getting started the following categories are often added right away.', 'wporg' ); ?></p>
					<ul>
						<?php foreach ( $plugin_search_terms as $plugins_search_term ) : ?>
						<li><a href="<?php echo esc_url( home_url( sprintf( '/plugins/search/%s/', urlencode( esc_attr( $plugins_search_term ) ) ) ) ); ?>"><?php echo esc_html( $plugins_search_term ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

	<?php
get_footer();
