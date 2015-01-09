<?php
/**
 * Template file for the Upload page.
 *
 * @package wporg-themes
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'wrap' ); ?>>
			<header class="entry-header">
				<h2 class="entry-title"><?php _e( 'Add Your Theme to the Directory', 'wporg-themes' ); ?></h2>
			</header><!-- .entry-header -->

			<div class="entry-content">
				<p><?php printf( __( 'Now that your theme is ready for prime time, give it plenty of attention by <a href="%s">uploading</a> it to the WordPress.org Theme Directory. By hosting it here you&rsquo;ll get:', 'wporg-themes' ), '/themes/upload/' ); ?></p>
				<ul>
					<li><?php _e( 'Stats on how many times your theme has been downloaded', 'wporg-themes' ); ?></li>
					<li><?php _e( 'User feed back in the forums', 'wporg-themes' ); ?></li>
					<li><?php _e( 'Ratings, to see how your theme is doing compared to others', 'wporg-themes' ); ?></li>
				</ul>
				<p>
					<?php _e( 'The goal of our themes directory isn&rsquo;t to have every theme in the world, it&rsquo;s to have the best.', 'wporg-themes' ); ?>
					<?php _e( 'WordPress is Open Source, and all the themes we host here are Open Source.', 'wporg-themes' ); ?>
					<?php _e( 'If you want your theme to be proprietary or promote things that violate WordPress&rsquo; license on your site, the directory probably isn&rsquo;t the best home for your work.', 'wporg-themes' ); ?>
				</p>

				<h2 name="requirements"><?php _e( 'Guidelines', 'wporg-themes' ); ?></h2>
				<p>
					<?php printf( __( 'Resources for theme authors are available in the Codex on the <a href="%s">Theme Development</a>, <a href="%s">Theme Review</a>, and <a href="%s">Theme Unit Test</a> pages.', 'wporg-themes' ),
						'http://codex.wordpress.org/Theme_Development',
						'http://codex.wordpress.org/Theme_Review',
						'http://codex.wordpress.org/Theme_Unit_Test'
					); ?>
					<?php printf(
						__( 'For questions about Theme development please use the <a href="%s">Themes and Templates forum</a>.', 'wporg-themes' ),
						'//wordpress.org/support/forum/5'
					); ?>
					<?php _e( 'Please make sure to review the guidelines before uploading your Theme file.', 'wporg-themes' ); ?>
				</p>
				<p>
					<?php _e( 'All themes are subject to review. Themes from sites that support non-GPL (or compatible) themes or violate the WordPress community guidelines themes will not be approved.', 'wporg-themes' ); ?>
					<?php printf(
						__( 'We will be reviewing your Theme using the sample data available in the WordPress export file available at <a href="%s">Theme Unit Test</a>.', 'wporg-themes' ),
						'http://codex.wordpress.org/Theme_Unit_Test'
					); ?>
					<?php _e( 'Before uploading your Theme file please test it with this sample export data.', 'wporg-themes' ); ?>
				</p>

				<?php the_content(); ?>
			</div><!-- .entry-content -->

			<?php edit_post_link( __( 'Edit', 'wporg-themes' ), '<footer class="entry-footer"><span class="edit-link">', '</span></footer><!-- .entry-footer -->' ); ?>
		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
