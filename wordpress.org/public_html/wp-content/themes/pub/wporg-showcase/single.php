<?php get_header(); ?>

<div id="pagebody" class="post">
	<div class="wrapper">
		<?php get_sidebar( 'left' ); ?>

			<?php if ( have_posts() ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>

					<div class="col-5">
						<div class="storycontent">
								<?php breadcrumb(); ?>
								<a href=' http://<?php get_site_domain( false ); ?>'>
									<?php site_screenshot_tag( 340, 'screenshot site-screenshot'); ?>
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
					<div class="col-13">
						<p class="button"><a href="http://<?php get_site_domain( false ); ?>"><?php _e( 'Visit Site', 'wporg-showcase' ); ?></a></p>

						<h4><?php _e( 'Rating', 'wporg-showcase' ); ?></h4>
						<?php the_ratings(); ?>
						<p class='rating-descrip'><?php _e( 'Rate this site based on their implementation and use of WordPress.', 'wporg-showcase' ); ?></p>

						<?php wp_flavors(); ?>
						<br />
						<?php tags_with_count( 'list', '<h4>' . __( 'Tags', 'wporg-showcase' ) . '</h4><ul>', '', '</ul>' ); ?>
					</div>

					<?php get_sidebar( 'right' ) ?>

				<?php endwhile; // have_posts ?>
			<?php endif; // have_posts ?>

	</div><!-- .wrapper -->
</div><!-- #pagebody -->
<?php get_footer(); ?>
