<?php get_header(); ?>

	<div id="pagebody">
		<div class="wrapper">
			<div class="col-9" role="main">
				<?php the_archive_title( '<h2 class="fancy">', '</h2>' ); ?>

				<?php
				$blog_url = home_url( '/#blog' );
				if ( 'page' === get_option( 'show_on_front' ) ) {
					$blog_url = get_permalink( get_option('page_for_posts' ) );
				}
				?>
				<p><a href="<?php echo esc_url( $blog_url ); ?>"><?php _e( '&laquo; Back to blog', 'rosetta' ); ?></a></p>

				<table class="widefat" >
					<?php
					$i = 0;
					if ( have_posts() ) :
						while ( have_posts() ) : the_post();
							$i++;
							?>
							<tr <?php if ( $i % 2 ) echo ' class="alt" '; ?>>
								<th>
									<?php the_date( '','<span class="date">', '</span>' ); ?>
								</th>
								<td>
									<a href="<?php the_permalink() ?>"><?php the_title(); ?></a>
								</td>
							</tr>
							<?php
						endwhile;
					else :
						?>
						<p><?php _e( 'Sorry, no posts matched your criteria.', 'rosetta' ); ?></p>
						<?php
					endif;
					?>
				</table>

				<nav class="posts-navigation">
					<?php posts_nav_link( ' &#8212; ', __( '&laquo; Newer Posts', 'rosetta' ), __( 'Older Posts &raquo;', 'rosetta' ) ); ?>
				</nav>
			</div>
		</div>
	</div>

<?php get_footer();
