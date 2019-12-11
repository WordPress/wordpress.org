<?php
/**
 * Template Name: About -> Philosophy
 *
 * Page template for displaying the Philosophy page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/philosophy'   => _x( 'Philosophy', 'Page title', 'wporg' ),
	'about/etiquette'    => _x( 'Etiquette', 'Page title', 'wporg' ),
	'about/swag'         => _x( 'Swag', 'Page title', 'wporg' ),
	'about/logos'        => _x( 'Graphics &amp; Logos', 'Page title', 'wporg' ),
	'about/testimonials' => _x( 'Testimonials', 'Page title', 'wporg' ),
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
					<h2 id="box"><?php esc_html_e( 'Out of the Box', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Great software should work with little configuration and setup. WordPress is designed to get you up and running and fully functional in no longer than five minutes. You shouldn&#8217;t have to battle to use the standard functionality of WordPress.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'We work hard to make sure that every release is in keeping with this philosophy. We ask for as few technical details as possible during the setup process as well as providing full explanations of anything we do ask.', 'wporg' ); ?></p>

					<h2 id="majority"><?php esc_html_e( 'Design for the Majority', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Many end users of WordPress are non-technically minded. They don&#8217;t know what AJAX is, nor do they care about which version of PHP they are using. The average WordPress user simply wants to be able to write without problems or interruption. These are the users that we design the software for as they are ultimately the ones who are going to spend the most time using it for what it was built for.', 'wporg' ); ?></p>

					<h2 id="decisions"><?php esc_html_e( 'Decisions, not Options', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'When making decisions these are the users we consider first. A great example of this consideration is software options. Every time you give a user an option, you are asking them to make a decision. When a user doesn&#8217;t care or understand the option this ultimately leads to frustration. As developers we sometimes feel that providing options for everything is a good thing, you can never have too many choices, right? Ultimately these choices end up being technical ones, choices that the average end user has no interest in. It&#8217;s our duty as developers to make smart design decisions and avoid putting the weight of technical choices on our end users.', 'wporg' ); ?></p>

					<h2 id="clean"><?php esc_html_e( 'Clean, Lean, and Mean', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'The core of WordPress will always provide a solid array of basic features. It&#8217;s designed to be lean and fast and will always stay that way. We are constantly asked &#8220;when will X feature be built&#8221; or &#8220;why isn&#8217;t X plugin integrated into the core&#8221;. The rule of thumb is that the core should provide features that 80% or more of end users will actually appreciate and use. If the next version of WordPress comes with a feature that the majority of users immediately want to turn off, or think they&#8217;ll never use, then we&#8217;ve blown it. If we stick to the 80% principle then this should never happen.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'We are able to do this because we have a very capable theme and plugin system and a fantastic developer community. Different people have different needs, and having the sheer number of quality WordPress plugins and themes allows users to customize their installations to their taste. That should allow all users to find the remaining 20% and make all WordPress features those they appreciate and use.', 'wporg' ); ?></p>

					<h2 id="simplicity"><?php esc_html_e( 'Striving for Simplicity', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'We&#8217;re never done with simplicity. We want to make WordPress easier to use with every single release. We&#8217;ve got a good track record of this, if you don&#8217;t believe us then just take a look back at some older versions of WordPress!', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'In past releases we&#8217;ve taken major steps to improve ease of use and ultimately make things simpler to understand. One great example of this is core software updates. Updating used to be a painful manual task that was too tricky for a lot of our users. We decided to focus on this and simplified it down to a single click. Now anyone with a WordPress installation can perform one click upgrades on both the core of WordPress and plugins and themes.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'We love to challenge ourselves and simplify tasks in ways that are positive for the overall WordPress user experience. Every version of WordPress should be easier and more enjoyable to use than the last.', 'wporg' ); ?></p>

					<h2 id="deadlines"><?php esc_html_e( 'Deadlines Are Not Arbitrary', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'Deadlines are not arbitrary, they&#8217;re a promise we make to ourselves and our users that helps us rein in the endless possibilities of things that could be a part of every release. We aspire to release three major versions a year because through trial and error we&#8217;ve found that to be a good balance between getting cool stuff in each release and not so much that we end up breaking more than we add.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'Good deadlines almost always make you trim something from a release. This is not a bad thing, it&#8217;s what they&#8217;re supposed to do.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'The route of delaying a release for that one-more-feature is a rabbit hole. We did that for over a year once, and it wasn&#8217;t pleasant for anybody.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'The more frequent and regular releases are, the less important it is for any particular feature to be in this release. If it doesn&#8217;t make it for this one, it&#8217;ll just be a few months before the next one. When releases become unpredictable or few and far between, there&#8217;s more pressure to try and squeeze in that one more thing because it&#8217;s going to be so long before the next one. Delay begets delay.', 'wporg' ); ?></p>

					<h2 id="minority"><?php esc_html_e( 'The Vocal Minority', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'There&#8217;s a good rule of thumb within internet culture called the 1% rule. It states that &#8220;the number of people who create content on the internet represents approximately 1% (or less) of the people actually viewing that content&#8221;.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'So while we consider it really important to listen and respond to those who post feedback and voice their opinions on forums, they only represent a tiny fraction of our end users. When making decisions on how to move forward with future versions of WordPress, we look to engage more of those users who are not so vocal online. We do this by meeting and talking to users at WordCamps across the globe, this gives us a better balance of understanding and ultimately allows us to make better decisions for everyone moving forward.', 'wporg' ); ?></p>

					<h2 id="gpl"><?php esc_html_e( 'Our Bill of Rights', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'WordPress is licensed under the General Public License (GPLv2 or later) which provides four core freedoms, consider this as the WordPress &#8220;bill of rights&#8221;:', 'wporg' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'The freedom to run the program, for any purpose.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The freedom to study how the program works, and change it to make it do what you wish.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The freedom to redistribute.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The freedom to distribute copies of your modified versions to others.', 'wporg' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Part of those licensing requirements include licensing derivative works or things that link core WordPress functions (like themes, plugins, etc.) under the GPL as well, thereby passing on the freedom of use for these works as well.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'Obviously there are those who will try to get around these ideals and restrict the freedom of their users by trying to find loopholes or somehow circumvent the intention of the WordPress licensing, which is to ensure freedom of use. We believe that the community as a whole will reward those who focus on supporting these licensing freedoms instead of trying to avoid them.', 'wporg' ); ?></p>
					<p><?php esc_html_e( 'The WordPress community should emphasize that the freedoms in the GPL help provide high quality software.', 'wporg' ); ?></p>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
