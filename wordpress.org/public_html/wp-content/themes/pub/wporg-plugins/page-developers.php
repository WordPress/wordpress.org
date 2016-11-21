<?php
/**
 * The template for the Developers page.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<?php while ( have_posts() ) : the_post(); ?>

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _e( 'Developer Information', 'wporg-plugins' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<section>
					<div class="container">
						<p><?php printf( __( 'You&#8217;ve got a cool new plugin and are hoping to give it some exposure. You&#8217;re in the right place. Just <a href="%s">ask us to host it for you</a>. You&#8217;ll be able to:', 'wporg-plugins' ), esc_url( home_url( 'developers/add' ) ) ); ?></p>
						<ul>
							<li><?php _e( 'Keep track of how many people have downloaded it.', 'wporg-plugins' ); ?></li>
							<li><?php _e( 'Let people leave comments about your plugin.', 'wporg-plugins' ); ?></li>
							<li><?php _e( 'Get your plugin rated against all the other cool WordPress plugins.', 'wporg-plugins' ); ?></li>
							<li><?php _e( 'Give your plugin lots of exposure in this centralized repository.', 'wporg-plugins' ); ?></li>
						</ul>

						<h3><?php _e( 'There are some restrictions', 'wporg-plugins' ); ?></h3>
						<ul>
							<li><?php printf( __( 'Your plugin must be compatible with the <a href="%s">GNU General Public License v2</a>, or any later version. We strongly recommend using the same license as WordPress — &#8220;GPLv2 or later.&#8221;', 'wporg-plugins' ), esc_url( 'http://www.gnu.org/licenses/license-list.html#GPLCompatibleLicenses' ) ); ?></li>
							<li><?php _e( 'The plugin must not do anything illegal or be morally offensive (that&#8217;s subjective, we know).', 'wporg-plugins' ); ?></li>
							<li><?php printf( __( 'You have to actually use the <a href="%s">Subversion</a> repository we give you in order for your plugin to show up on this site. The WordPress Plugin Directory is a hosting site, not a listing site.', 'wporg-plugins' ), esc_url( 'http://subversion.tigris.org/' ) ); ?></li>
							<li><?php _e( 'The plugin must not embed external links on the public site (like a &#8220;powered by&#8221; link) without explicitly asking the user&#8217;s permission.', 'wporg-plugins' ); ?></li>
							<li><?php printf( __( 'Your plugin must abide by our list of <a href="%s">detailed guidelines</a>, which include not being a spammer and not abusing the systems.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/' ) ); ?></li>
						</ul>

						<h3><?php _e( 'Submission is Simple', 'wporg-plugins' ); ?></h3>
						<ol>
						    <li><?php printf( __( '<a href="%s">Sign up</a> for an account on WordPress.org.', 'wporg-plugins' ), esc_url( 'https://wordpress.org/support/register.php' ) ); ?></li>
						    <li><?php printf( __( '<a href="%s">Submit your plugin for review</a>.', 'wporg-plugins' ), esc_url( home_url( 'developers/add' ) ) ); ?></li>
						    <li><?php printf( __( 'After your plugin is <a href="%s">manually reviewed</a>, you will either be approved or emailed and asked to provide more information and/or make corrections.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#questions-about-submissions-and-approval' ) ); ?></li>
						    <li><?php printf( __( 'Once approved, you&#8217;ll be given access to a <a id="subversion" href="%s">Subversion Repository</a> where you&#8217;ll store your plugin.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/' ) ); ?></li>

						    <li>
						        <?php
							    /* translators: 1: URL to readme section; 2: URL to home page; */
							    printf( __( 'Shortly after you upload your plugin (and a <a href="%1$s">readme file</a>!) to that repository, it will be automatically displayed in the <a href="%2$s">plugins browser</a>.', 'wporg-plugins' ), '#readme', esc_url( home_url( '/' ) ) );
							    ?>
							 </li>
						    <li><?php printf( __( 'Check out the <strong><a href="%s">FAQ</a> </strong>for more information.', 'wporg-plugins' ), esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' ) ); ?></li>
						</ol>

						<h3 id="readme"><?php _e( 'Readme files', 'wporg-plugins' ); ?></h3>
						<p>
							<?php
							/* translators: 1: URL to readme file; 2: URL to readme validator; */
							printf( __( 'To make your entry in the plugin browser most useful, each plugin should have a readme file named <code>readme.txt</code> that adheres to the <a href="%1$s">WordPress plugin readme file standard</a>. You can put your readme file through the <a href="%2$s">readme validator</a> to check it.', 'wporg-plugins' ), esc_url( home_url( 'files/2016/06/readme.txt' ) ), esc_url( home_url( 'wp-admin/edit.php?post_type=plugin&page=readme_validator' ) ) );
							?>
						</p>
					</div>
				</section>
			</div><!-- .entry-content -->

			<footer class="entry-footer">
				<?php
				edit_post_link(
					sprintf(
					/* translators: %s: Name of current post */
						esc_html__( 'Edit %s', 'wporg-plugins' ),
						the_title( '<span class="screen-reader-text">"', '"</span>', false )
					),
					'<span class="edit-link">',
					'</span>'
				);
				?>
			</footer><!-- .entry-footer -->
		</article><!-- #post-## -->

		<?php endwhile;	?>

	</main><!-- #main -->

<?php
get_footer();
