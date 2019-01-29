<?php
/**
 * Template Name: Download -> Source Code
 *
 * Page template for displaying the Source Code page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'download/releases'     => _x( 'Releases', 'Page title', 'wporg' ),
	'download/beta-nightly' => _x( 'Beta/Nightly', 'Page title', 'wporg' ),
	'download/counter'      => _x( 'Counter', 'Page title', 'wporg' ),
	'download/source'       => _x( 'Source Code', 'Page title', 'wporg' ),
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
							/* translators: URLs to documentation */
							wp_kses_post( __( 'If you&#8217;d like to browse the WordPress source and inline documentation, we have a <a href="%1$s">convenient developer reference</a> and a <a href="%2$s">code browser</a>. We also have guides for <a href="%3$s">contributing with Subversion</a> and <a href="%4$s">contributing with Git</a>.', 'wporg' ) ),
							'https://developer.wordpress.org/reference/',
							'https://core.trac.wordpress.org/browser/',
							'https://make.wordpress.org/core/handbook/contribute/svn/',
							'https://make.wordpress.org/core/handbook/contribute/git/'
						);
						?>
					</p>

					<p>
						<?php
						printf(
							/* translators: URLs to documentation */
							wp_kses_post( __( 'The built WordPress source, <a href="%1$s">licensed</a> under the GNU General Public License version 2 (or later), can be <a href="%2$s">browsed online</a> or checked out locally with Subversion or Git:', 'wporg' ) ),
							esc_url( home_url( '/about/license/' ) ),
							'https://build.trac.wordpress.org/browser'
						);
						?>
					</p>

					<ul>
						<li>
							<?php
							printf(
								/* translators: URL to subversion repository */
								esc_html__( 'Subversion: %s', 'wporg' ),
								'<code>https://core.svn.wordpress.org/</code>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: URL to Git repository */
								esc_html__( 'Git mirror: %s', 'wporg' ),
								'<code>git://core.git.wordpress.org/</code>'
							);
							?>
						</li>
					</ul>

					<p>
						<?php
						printf(
							/* translators: URLs to documentation */
							wp_kses_post( __( 'WordPress minifies core JavaScript files using UglifyJS and CSS using clean-css, all via the <a href="%1$s">Grunt</a> JavaScript-based task runner. The development source that includes un-minified versions of these files, along with the build scripts, can be <a href="%2$s">browsed online</a> or checked out locally with Subversion or Git:', 'wporg' ) ),
							'https://gruntjs.com/',
							'https://core.trac.wordpress.org/browser'
						);
						?>
					</p>

					<ul>
						<li>
							<?php
							printf(
								/* translators: URL to subversion repository */
								esc_html__( 'Subversion: %s', 'wporg' ),
								'<code>https://develop.svn.wordpress.org/</code>'
							);
							?>
						</li>
						<li>
							<?php
							printf(
								/* translators: URL to Git repository */
								esc_html__( 'Git mirror: %s', 'wporg' ),
								'<code>git://develop.git.wordpress.org/</code>'
							);
							?>
						</li>
					</ul>

					<p>
						<?php
						printf(
							/* translators: URLs to documentation */
							wp_kses_post( __( 'The source code for any program binaries or minified external scripts that are included with WordPress can be freely obtained from our <a href="%s">sources repository</a>.', 'wporg' ) ),
							'https://code.trac.wordpress.org/browser/wordpress-sources'
						);
						?>
					</p>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
