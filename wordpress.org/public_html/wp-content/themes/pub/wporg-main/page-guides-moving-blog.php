<?php
/**
 * Template Name: Guides -> Moving a Blog
 *
 * Page template for displaying the Moving a Blog page.
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
				<h1 class="entry-title col-8"><?php _esc_html_e( 'Moving a Blog', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php _esc_html_e( 'We are very excited you have been successful with your blog and are ready for the next step! Many very successful bloggers have started on other platforms and ultimately outgrow those tools or the &#8220;box&#8221; that goes along with it.', 'wporg' ); ?></p>

					<p><?php _esc_html_e( 'How to move your SquareSpace blog to WordPress', 'wporg' ); ?></p>
					<ul>
						<li><?php _esc_html_e( 'Log into your SquareSpace account and in the Home sidebar menu, click &#8220;Settings&#8221;. Once you&#8217;re in the settings menu, click &#8220;Advanced&#8221;, then &#8220;Import / Export&#8221;.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'In the pop-up modal, click &#8220;WordPress&#8221;. Wait for the progress bar on the left sidebar to complete, then click the &#8220;Download&#8221; button. Save the XML file to a place on your computer that you&#8217;ll remember.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Log into your existing WordPress blog and click &#8220;Tools&#8221; from the left sidebar to expand the tools menu. Click &#8220;Import&#8221;.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'On the Import page, click the &#8220;Install Now&#8221; link under the &#8220;WordPress&#8221; option. When the WordPress importer is finished installing, the link will change to &#8220;Run Importer&#8221;. Click this link to bring up the WordPress import page.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'On the import page, click the &#8220;Choose File&#8221; button, navigate to where you saved the XML file, select it and click &#8220;Open&#8221;. Then, hit the &#8220;Upload file and Import&#8221; button.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You&#8217;ll have to assign an author to your new content. Either create a new user by entering a username in the &#8220;&hellip;as a new user&#8221; input box, or assign the posts to an existing user by using the corresponding input box. Click submit and you&#8217;re done!', 'wporg' ); ?></li>
					</ul>

					<p><?php _esc_html_e( 'How to move your Wix blog to WordPress', 'wporg' ); ?></p>
					<ul>
						<li><?php echo wp_kses_post( ___( 'In your browser&#8217;s address bar, enter the URL to your Wix website, then add <code>/feed.xml</code> to the end of it. This will load your blog&#8217;s RSS feed.', 'wporg' ) ); ?></li>
						<li><?php _esc_html_e( 'Right click anywhere on the page and click &#8220;Save as&hellip;&#8221; then save the XML file to a location on your computer that you will remember.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Log into your existing WordPress blog and click &#8220;Tools&#8221; from the left sidebar to expand the tools menu. Click &#8220;Import&#8221;.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'On the Import page, click the &#8220;Install Now&#8221; link under the &#8220;WordPress&#8221; option. When the WordPress importer is finished installing, the link will change to &#8220;Run Importer&#8221;. Click this link to bring up the WordPress import page.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'On the import page, click the &#8220;Choose File&#8221; button, navigate to where you saved the XML file, select it and click &#8220;Open&#8221;. Then, hit the &#8220;Upload file and Import&#8221; button.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You&#8217;ll have to assign an author to your new content. Either create a new user by entering a username in the &#8220;&hellip;as a new user&#8221; input box, or assign the posts to an existing user by using the corresponding input box. Click submit and you&#8217;re done!', 'wporg' ); ?></li>
					</ul>

					<p><?php _esc_html_e( 'How to move your Blogger blog posts to Self-Hosted WordPress', 'wporg' ); ?></p>
					<ul>
						<li><?php _esc_html_e( 'First, log into your Blogger account.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'In the upper left corner, you&#8217;ll notice a small &#8220;down arrow&#8221; next to your blog name. Click the arrow to reveal a dropdown menu of all your blogs are that are hosted on Blogger.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Click the name of the blog you want to backup.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Click the &#8220;Settings&#8221; option on the left sidebar to expand the settings menu, then click the &#8220;Other&#8221; option.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Click the &#8220;Backup Content&#8221; button located near the top of the page to bring up a pop-up window. In the pop-up window, click the &#8220;Save to your computer&#8221; button and choose where you want to save the XML file that contains all your backup.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'Log into your existing WordPress blog and click &#8220;Tools&#8221; from the left sidebar to expand the tools menu. Click &#8220;Import&#8221;.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'On the Import page, click the &#8220;Install Now&#8221; link under the &#8220;WordPress&#8221; option. When the WordPress importer is finished installing, the link will change to &#8220;Run Importer&#8221;. Click this link to bring up the WordPress import page.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'On the import page, click the &#8220;Choose File&#8221; button, navigate to where you saved the XML file, select it and click &#8220;Open&#8221;. Then, hit the &#8220;Upload file and Import&#8221; button.', 'wporg' ); ?></li>
						<li><?php _esc_html_e( 'You&#8217;ll have to assign an author to your new content. Either create a new user by entering a username in the &#8220;&hellip;as a new user&#8221; input box, or assign the posts to an existing user by using the corresponding input box. Click submit and you&#8217;re done!', 'wporg' ); ?></li>
					</ul>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

	<?php
get_footer();
