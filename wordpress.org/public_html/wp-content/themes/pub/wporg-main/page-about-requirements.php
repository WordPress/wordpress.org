<?php
/**
 * Template Name: Requirements
 *
 * Page template for displaying the Requirements page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) ) {
	return get_template_part( 'page' );
}

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

add_filter( 'jetpack_open_graph_tags', function( $tags ) {
	$tags['og:title']            = _esc_html__( 'Hosting Requirements for WordPress', 'wporg' );
	$tags['og:description']      = _esc_html__( 'Running WordPress doesn&#8217;t require a lot, but your host will still need to meet a few minimum requirements. Learn about the website hosting requirements to run WordPress, including our recommendation to support PHP 7.2+ and HTTPS. Not sure how to ask your host for these details? Use the sample email we include.', 'wporg' );
	$tags['twitter:text:title']  = $tags['og:title'];
	$tags['twitter:description'] = $tags['og:description'];

	return $tags;
} );

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _esc_html_e( 'Requirements', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<h3><?php _esc_html_e( 'To run WordPress we recommend your host supports:', 'wporg' ); ?></h3>

					<ul>
						<li>
							<?php
							/* translators: URL to PHP */
							printf( wp_kses_post( ___( '<a href="%s">PHP</a> version 7.2 or greater.', 'wporg' ) ), esc_url( 'http://www.php.net/' ) );
							?>
						</li>
						<li>
							<?php
							/* translators: 1: URL to MySQL; 2: URL to MariaDB */
							printf( wp_kses_post( ___( '<a href="%1$s">MySQL</a> version 5.6 or greater <em>OR</em> <a href="%2$s">MariaDB</a> version 10.0 or greater.', 'wporg' ) ), esc_url( 'https://www.mysql.com/' ), esc_url( 'https://mariadb.org/' ) );
							?>
						</li>
						<li>
							<?php
							/* translators: URL to news post */
							printf( wp_kses_post( ___( '<a href="%s">HTTPS</a> support', 'wporg' ) ), esc_url( 'https://wordpress.org/news/2016/12/moving-toward-ssl/' ) );
							?>
						</li>
					</ul>

					<p>
						<?php
						/* translators: 1: URL to Apache; 2: URL to Nginx; 3: URL to hosting page */
						printf( wp_kses_post( ___( 'That&#8217;s really it. We recommend <a href="%1$s">Apache</a> or <a href="%2$s">Nginx</a> as the most robust and featureful server for running WordPress, but any server that supports PHP and MySQL will do. That said, we can&#8217;t test every possible environment and <a href="%3$s">each of the hosts on our hosting page</a> supports the above and more with no problems.', 'wporg' ) ), esc_url( 'https://httpd.apache.org/' ), esc_url( 'https://nginx.org/' ), esc_url( home_url( '/hosting/' ) ) );
						?>
					</p>

					<p><?php echo wp_kses_post( ___( 'Note: If you are in a legacy environment where you only have older PHP or MySQL versions, WordPress also works with PHP 5.2.4+ and MySQL 5.0+, but these versions have reached official End Of Life and as such <strong>may expose your site to security vulnerabilities</strong>.', 'wporg' ) ); ?></p>

					<h3><?php _esc_html_e( 'Ask for it', 'wporg' ); ?></h3>

					<p><?php _esc_html_e( 'Here&#8217;s a letter you can send to your host; copy and paste!', 'wporg' ); ?></p>

					<blockquote>
						<p><?php _esc_html_e( 'I&#8217;m interested in running the open-source WordPress &lt;https://wordpress.org/&gt; web software and I was wondering if my account supported the following:', 'wporg' ); ?></p>

						<ul>
							<li><?php _esc_html_e( 'PHP 7.2 or greater', 'wporg' ); ?></li>
							<li><?php _esc_html_e( 'MySQL 5.6 or greater OR MariaDB 10.0 or greater', 'wporg' ); ?></li>
							<li><?php _esc_html_e( 'Nginx or Apache with mod_rewrite module', 'wporg' ); ?></li>
							<li><?php _esc_html_e( 'HTTPS support', 'wporg' ); ?></li>
						</ul>

						<p><?php _esc_html_e( 'Thanks!', 'wporg' ); ?></p>
					</blockquote>

					<h3><?php _esc_html_e( 'Not required, but recommended for better security', 'wporg' ); ?></h3>

					<p><?php _esc_html_e( 'Hosting is more secure when PHP applications, like WordPress, are run using your account&#8217;s username instead of the server&#8217;s default shared username. Ask your potential host what steps they take to ensure the security of your account.', 'wporg' ); ?></p>
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
