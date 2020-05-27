<?php get_header(); ?>

<?php // 404, so let's choose a random site from the showcase and show that instead
$wp_query = new WP_Query( array( 'no_found_rows' => true, 'post_type' => 'post', 'post_status'=> 'publish', 'cache_results' => false, 'orderby' => 'rand', 'posts_per_page' => 1 ) );
?>

<div id="pagebody" class="post">
	<div class="wrapper">
		<?php get_sidebar( 'left' ); ?>

			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>

					<div class="col-5">
						<div class="storycontent">
								<h2><?php _e( 'Page Not Found', 'wporg-showcase' ); ?></h2>
								<p><?php _e( 'Sorry, we could not not find that site in the Showcase. We do have many others available though. Here&#8217;s one chosen at random!', 'wporg-showcase' ); ?></p>
								<?php //breadcrumb(); ?>
								<h2><?php the_title(); ?></h2>
								<a href=' http://<?php get_site_domain( false ); ?>'>
									<?php site_screenshot_tag( 518, 'screenshot site-screenshot'); ?>
								</a>
								<?php the_content(); ?>

								<?php
									$screenshots = get_post_custom_values( 'image' );

									if ( !empty($screenshots) ) {
										$output = "
											<script src='" . get_template_directory_uri() . "/js/fancyzoom.js' type='text/javascript'></script>
											<script src='" . get_template_directory_uri() . "/js/fancyzoomhtml.js' type='text/javascript'></script>
											<script type='text/javascript'>onload = function() { setupZoom(); }</script>

											<div class='gallery'>";

										foreach ( $screenshots as $key => $value ) {
											$space = strpos($value, ' ');
											$image_src = substr($value, 0, $space);
											$image_desc = substr($value, $space+1);

											$output .= "<dl class='gallery-item'>";
											$output .= "
												<dt class='gallery-icon'>
													<a href='$image_src' title='$image_desc'><img src='$image_src?w=155&h=155' /></a>
												</dt>";
											$output .= "</dl>";
											if ( $key > 0 && $key+1 % 2 == 0 )
												$output .= "<br style='clear: both' />";
										}

										$output .= "
												<br style='clear: both;' />
											</div>\n";

										echo $output;
									}
								?>

							<?php edit_post_link( __( 'Edit This', 'wporg-showcase' ), '<div class="meta">', '</div>'); ?>

						</div><!-- .storycontent -->
						<?php comments_template(); ?>
					</div><!-- .col-5 -->

					<?php get_sidebar( 'right' ) ?>

				<?php endwhile; // have_posts ?>
			<?php endif; // have_posts ?>

	</div><!-- .wrapper -->
</div><!-- #pagebody -->
<?php get_footer(); ?>
