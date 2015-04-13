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
									<img src="<?php site_screenshot_src( 340 ); ?>"  class='site-screenshot' title='<?php the_title(); ?>' width='340' height='255' />
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

							<?php edit_post_link('Edit This', '<div class="meta">', '</div>'); ?>

						</div><!-- .storycontent -->
						<?php comments_template(); ?>
					</div><!-- .col-5 -->
					<div class="col-13">
						<p class="button"><a href="http://<?php get_site_domain( false ); ?>">Visit Site</a></p>

						<h4>Rating</h4>
						<?php the_ratings(); ?>
						<p class='rating-descrip'>Rate this site based on their implementation and use of WordPress.</p>

						<?php wp_flavors(); ?>
						<br />
						<?php tags_with_count( 'list', '<h4>Tags</h4><ul>', '', '</ul>' ); ?>
					</div>

					<?php get_sidebar( 'right' ) ?>

				<?php endwhile; // have_posts ?>
			<?php endif; // have_posts ?>

	</div><!-- .wrapper -->
</div><!-- #pagebody -->
<?php get_footer(); ?>
