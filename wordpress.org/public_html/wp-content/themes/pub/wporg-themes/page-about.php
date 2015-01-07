<?php
/**
 * Template file for the About page.
 *
 * @package wporg-themes
 */

get_header();

if ( have_posts() ) :
	while ( have_posts() ) :
		the_post();
		?>

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'wrap' ); ?>>
			<div class="entry-content">
				<h2><?php _e( 'Hosting your theme at WordPress.org', 'wporg-themes' ); ?></h2>
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
				</p>
				<p><?php _e( 'All themes are subject to review. Themes from sites that support non-GPL (or compatible) themes or violate the WordPress community guidelines themes will not be approved.', 'wporg-themes' ); ?></p>

				<h2><?php _e( 'Theme Tags', 'wporg-themes' ); ?></h2>
				<p><?php printf( __( 'Tags used to describe the themes. If you&rsquo;d like to suggest new ones please <a href="%s">contact us</a>.', 'wporg-themes' ), '/themes/contact/' ); ?></p>

				<?php
					if ( ! function_exists( 'get_theme_feature_list' ) ) {
						include_once ABSPATH . 'wp-admin/includes/theme.php';
					}
					foreach( get_theme_feature_list() as $tag_cat => $tags ) :
						echo '<h4>' . esc_html( str_replace( '-', '', $tag_cat ) ) . '</h4>';
						echo '<ul>';

						foreach( $tags as $tag_name ) :
							echo '<li>' . esc_html( $tag_name ) . '</li>';
						endforeach;

						echo '</ul>';
					endforeach;
				?>
				<h2><?php _e( 'Contact Us', 'wporg' ); ?></h2>
				<p><?php printf( __( 'For WordPress support issues please use the <a href="%s">forums</a>.', 'wporg-themes' ), '/support/' ); ?></p>
				<p>
					<?php _e( 'If you have questions or suggestions about the features at wordpress.org/themes, join our mailing list to talk about it.', 'wporg-themes' ); ?>
					<?php printf( __( 'The mailing list can be found at <a href="%1$s">%1$s</a>.', 'wporg-themes' ),
						'http://lists.wordpress.org/mailman/listinfo/theme-reviewers'
					); ?>
				</p>

				<?php the_content(); ?>
			</div><!-- .entry-content -->

			<?php edit_post_link( __( 'Edit', 'wporg-themes' ), '<footer class="entry-footer"><span class="edit-link">', '</span></footer><!-- .entry-footer -->' ); ?>
		</article><!-- #post-## -->

	<?php
	endwhile;
endif;

get_footer();
