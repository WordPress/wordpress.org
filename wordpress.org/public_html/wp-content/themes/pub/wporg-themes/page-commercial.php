<?php
/**
 * Template file for the Commercial page.
 *
 * @package wporg-themes
 */

$theme_shops = new WP_Query( array(
	'post_type'      => 'theme_shop',
	'posts_per_page' => -1,
	'orderby'        => 'rand',
) );

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
					<?php _e( 'The GPL doesn&rsquo;t say that everything must be zero-cost, just that when you receive the software it must not restrict your freedoms in how you use it.', 'wporg-themes' ); ?>
				</p>
				<p>
					<?php _e( 'With that in mind, here are a collection of folks who provide GPL themes with extra paid services available around them.', 'wporg-themes' ); ?>
					<?php _e( 'Some of them you may pay to access, some of them are membership sites, some may give you the theme for zero-cost and just charge for support.', 'wporg-themes' ); ?>
					<?php _e( 'What they all have in common is people behind them who support open source, WordPress, and its GPL license.', 'wporg-themes' ); ?>
				</p>

				<div id="themes">
					<div class="theme-browser content-filterable">
						<div class="themes">
							<?php
								while ( $theme_shops->have_posts() ) :
									$theme_shops->the_post();

									if ( ! $image_url = post_custom( 'image_url' ) ) :
										$image_url = sprintf( '//s0.wp.com/mshots/v1/%s?w=572', urlencode( post_custom( 'url' ) ) );
									endif;
							?>
							<article id="post-<?php the_ID(); ?>" <?php post_class( array( 'theme', 'hentry' ) ); ?>>
								<div class="theme-screenshot">
									<img src="<?php echo esc_url( $image_url ); ?>" alt="">
								</div>
								<a class="more-details url" href="<?php echo esc_url( post_custom( 'url' ) ); ?>" rel="bookmark"><?php the_content(); ?></a>
								<h3 class="theme-name entry-title"><?php the_title(); ?></h3>
							</article>
							<?php
								endwhile;
								wp_reset_postdata();
							?>
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

			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
