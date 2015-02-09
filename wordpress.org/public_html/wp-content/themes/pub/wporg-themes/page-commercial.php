<?php
/**
 * Template file for the Commercial page.
 *
 * @package wporg-themes
 */


/**
 * Filter to get an array of data for theme shops to be listed.
 *
 * Expected structure for each element in the commercial theme shops array:
 *
 * array(
 *    'name'        => '', // Theme shop name.
 *    'url'         => '', // Company URL.
 *    'description' => "", // Haiku.
 *    'user_id'     => '', // User ID of the WordPress.org user associated with the theme shop.
 *    'image'       => '', // Theme shop site screenshot URL. Optional; only if overriding default, automatic image retrieval.
 * )
 *
 * @param array $commercial Array of commercial theme shops data array. Default empty array.
 */
$commercial = (array) apply_filters( 'wporg_themes_commercial', array() );

shuffle( $commercial );

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( array( 'wrap', 'commercial' ) ); ?>>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'Commercially Supported GPL Themes', 'wporg-themes' ); ?></h2>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p>
					<?php _e( 'While our directory is full of fantastic themes, sometimes people want to use something that they know has support behind it, and don&rsquo;t mind paying for that.', 'wporg-themes' ); ?>
					<?php _e( 'Contrary to popular belief, GPL doesn&rsquo;t say that everything must be zero-cost, just that when you receive the software or theme that it not restrict your freedoms in how you use it.', 'wporg-themes' ); ?>
				</p>
				<p>
					<?php _e( 'With that in mind, here are a collection of folks who provide GPL themes with extra paid services available around them.', 'wporg-themes' ); ?>
					<?php _e( 'Some of them you may pay for access, some of them are membership sites, some may give you the theme for zero-cost and just charge for support.', 'wporg-themes' ); ?>
					<?php _e( 'What they all have in common is people behind them who support open source, WordPress, and its GPL license.', 'wporg-themes' ); ?>
				</p>

				<div id="themes">
					<div class="theme-browser content-filterable">
						<div class="themes">
							<?php foreach ( $commercial as $theme ) : ?>
								<article id="post-<?php echo sanitize_title_with_dashes( $theme['name'] ); ?>" class="theme hentry">
									<div class="theme-screenshot">
										<img src="<?php echo esc_url( '//s0.wp.com/mshots/v1/' . urlencode( $theme['url'] ) . '?w=572' ); ?>" alt="">
									</div>
									<a class="more-details url" href="<?php echo esc_url( $theme['url'] ); ?>" rel="bookmark"><?php echo nl2br( $theme['description'] ); ?></a>
									<h3 class="theme-name entry-title"><?php echo $theme['name']; ?></h3>
								</article>
							<?php endforeach; ?>
						</div>
					</div>
				</div>

				<p><?php _e( 'If you would like to be included in this list please send your info to themes at wordpress dot org. To be included, you should:', 'wporg-themes' ); ?></p>

				<ul>
					<li><?php _e( 'Distribute 100% GPL themes, including artwork and CSS.', 'wporg-themes' ); ?></li>
					<li><?php printf( __( 'Have at least one theme in the WordPress.org <a href="%s">Themes Directory</a> that is actively maintained (i.e. updated within the last year).', 'wporg-themes' ), '//wordpress.org/themes/' ); ?></li>
					<li><?php _e( 'Have professional support options, and optionally customization.', 'wporg-themes' ); ?></li>
					<li><?php _e( 'Your site should be complete, well-designed, up to date, and professional looking.', 'wporg-themes' ); ?></li>
					<li><?php _e( 'Provide and keep us up-to-date with a contact email address in the event we need to reach you.', 'wporg-themes' ); ?></li>
					<li><?php printf( __( 'Provide a <a href="%s">haiku</a> (5-7-5) about yourself to be included.', 'wporg-themes' ), 'http://en.wikipedia.org/wiki/Haiku_in_English' ); ?></li>
				</ul>

				<?php the_content(); ?>
			</div><!-- .entry-content -->

			<?php edit_post_link( __( 'Edit', 'wporg-themes' ), '<footer class="entry-footer"><span class="edit-link">', '</span></footer><!-- .entry-footer -->' ); ?>
		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
