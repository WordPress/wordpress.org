<?php
/**
 * Template Name: Guides -> Adding a Blog
 *
 * Page template for displaying the Adding a Blog page.
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
				<h1 class="entry-title col-8"><?php _esc_html_e( 'Adding a Blog', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php _esc_html_e( 'Adding a blog can be done in a couple ways. If you are lucky enough to already be running WordPress, then adding a blog is going to take just a few minutes.', 'wporg' ); ?></p>
					<h2><?php _esc_html_e( 'Adding a Blog to an Existing WordPress Site', 'wporg' ); ?></h2>

					<ul>
						<li><?php _esc_html_e( 'In your Admin, under Posts, go to Categories. Create a Category with a name that will fit for your site&#8217;s navigation. Most people just call it Blog, but News, Events, or Press Releases are also popular. Blog will probably already exist and that can be used as is.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Create your first Blog post by going under Posts and clicking on Add New. You can add an image easily and text inside the Editor area. Check the box under Categories on the right side for the Category you selected above. Click Publish/Update.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Next you need to add a menu item that leads to your blog. Go into the Customizer and under Menus select the Menu (likely primary) where you want to have the Blog. Click Add Items, select Category and then the category name you created before. Then click the Add Items again to close the options and you should now see your Blog menu item in your Menu.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Once you are happy, click Publish.', 'wporg' ); ?></li>
					</ul>

					<p><?php _esc_html_e( 'If you are adding a blog to an already running site that is not WordPress, you have a couple of options.', 'wporg' ); ?></p>
					<h2><?php _esc_html_e( 'Setup WordPress on a subdomain with its own navigation - Easiest', 'wporg' ); ?></h2>
					<p><?php _esc_html_e( 'Sometimes you want to separate your blog from your main website by using a subdomain and some websites use this for organizational purposes.', 'wporg' ); ?></p>
					<p><?php _esc_html_e( 'For example, the website cleverdomain.com offers a lot of great content and you want to add blog functionality complete with its own navigation.', 'wporg' ); ?></p>

					<ul>
						<li><?php _esc_html_e( 'First they create the subdomain blog.cleverdomain.com on their server and install WordPress on the new subdomain the same way they did for their primary domain.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'As is the case with most WordPress installations, the default configuration for their new subdomain is already in blog format with their homepage showing a feed of all the latest posts.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Choosing a theme for the blog site is the next big step. This can be used to create a separate but similar identity for the blog site if desired.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'The next step is to create some blog categories that will be used as navigation items for the blog site.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'One final navigation item that needs to be included is a link back to the main domain.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Now that the blog is setup in its own subdomain, it&8217;s time to start making those blog posts!', 'wporg' ); ?></li>
					</ul>

					<h2><?php _esc_html_e( 'Setup WordPress as a subdirectory with integrated navigation  - Recommended', 'wporg' ); ?></h2>
					<ul>
						<li><?php _esc_html_e( 'Creating a separate blog installation in a subdirectory follows a very similar path to creating one in a subdomain.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'The first step is to create the subdirectory for your new WordPress installation. Some software packages, like Softaculous, will create the subdirectory automatically during the installation process and might allow you to skip this step.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Next install WordPress like you would normally, but make sure that you are creating the new WordPress instance in the new subdirectory.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Once the installation is complete you can start building out your separate blog like you would any other WordPress website.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'A key difference to note between the subdirectory and subdomain installation options is the configuration for the Site and Home URLs. The subdirectory installation method requires that the primary domain be used followed by the blog subdirectory, where the subdomain method will simply use the subdomain URL and will not require the subdirectory to be specified.', 'wporg' ); ?></li>
						<li><?php /* @todo */ _esc_html_e( 'TODO: Need the hardest step&mdash;bring over your current sites navigation to the theme', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'The advantage to using this method over the subdomain one is that is maintains consistency with your domain naming conventions. Any traffic to your blog will also help improve the standing of your main domain unlike the subdomain method which will be ranked separately.', 'wporg' ); ?></li>
					</ul>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

	<?php
get_footer();
