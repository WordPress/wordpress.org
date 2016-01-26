<?php
/*
Template Name: Home
*/

get_header();
?>
<div id="pagebody" class="home">
	<?php query_posts( array( 'cat' => 4, 'posts_per_page' => 9 ) ); ?>
	<?php if ( have_posts() ) : ?>

		<div class="wpsc-hero group">
			<div class="wpsc-hero-slide-container no-js">

				<?php while ( have_posts() ) : the_post(); ?>

					<div class="wpsc-hero-slide">
						<div class="wpsc-hero-slide-content">
							<a href="<?php the_permalink(); ?>" class="wpsc-hero-slide-img">
								<?php site_screenshot_tag( 457 ); ?>
							</a>
							<h3><a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h3>

							<?php $wpsc_url = esc_url( get_post_meta( $post->ID, 'domain', true ) ); ?>
							<?php if ( $wpsc_url ) : // make sure the URL is valid (esc_url will return an empty string if not) ?>
							<a href="<?php echo $wpsc_url; ?>" class="wpsc-linkout">
								<?php echo str_replace( parse_url( $wpsc_url, PHP_URL_SCHEME ) . '://', '', untrailingslashit( $wpsc_url ) ); ?>
								<span class="linkout-symbol"><?php _ex( '&#10162;', 'linkout symbol', 'wporg-showcase' ); ?></span>
							</a>
							<?php endif; // $wpsc_url ?>

							<?php
								the_tags( '<ul class="wpsc-tags"><li>','</li><li>','</li></ul>' );
								the_excerpt();
							?>
							<a class="wpsc-hero-learnmore" href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
								<?php _e( 'Learn More &rarr;', 'wporg-showcase' ); ?>
							</a>
						</div><!-- .wpsc-hero-slide-content -->
					</div><!-- .wpsc-hero-slide -->

				<?php endwhile; ?>

			</div>
			<div class="wpsc-slide-nav"></div>
		</div> <!-- .wpsc-hero -->

	<?php endif; ?>

	<div class="wrapper">

		<?php get_sidebar( 'left' ); ?>

		<div class="col-7 main-content">

			<?php query_posts( array( 'cat' => 4, 'posts_per_page' => 3, 'tag' => 'business', 'orderby' => 'rand' ) ); ?>
			<?php if ( have_posts() ) : ?>
			<h3><?php _e( 'Featured Business Sites', 'wporg-showcase' ); ?></h3>
			<ul class="wpsc-recent">

				<?php while ( have_posts() ) : the_post(); ?>

					<li>
						<a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>">
							<?php site_screenshot_tag( 215 ); ?>
						</a>
						<h5><a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h5>
						<?php
							the_content_limit( 90 );
							the_tags( '<ul class="wpsc-tags"><li>', '</li><li>', '</li></ul>' );
						?>
					</li>

				<?php endwhile; // have_posts ?>
			</ul>
			<?php endif; // have_posts ?>

			<?php query_posts( array( 'posts_per_page' => 9 ) ); ?>
			<?php if ( have_posts() ) : ?>

			<h3><?php _e( 'Recently Added Sites', 'wporg-showcase' ); ?></h3>
			<ul class="wpsc-recent">

				<?php while ( have_posts() ) : the_post(); ?>

				<li>
					<a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>">
						<?php site_screenshot_tag( 215 ); ?>
					</a>
					<h5><a href="<?php the_permalink() ?>" title="<?php the_title_attribute(); ?>"><?php the_title(); ?></a></h5>
					<?php
						the_tags( '<ul class="wpsc-tags"><li>', '</li><li>', '</li></ul>' );
						if ( function_exists( 'the_ratings' ) ) the_ratings();
					?>
				</li>

				<?php endwhile; // have_posts ?>
			</ul>
			<a href="<?php echo home_url( '/archives/' ); ?>" class="wpsc-view-all"><?php _e( 'View All Showcase Sites &rarr;', 'wporg-showcase' ); ?></a>

			<?php endif; // have_posts ?>

		</div>
	</div>
</div>
<?php get_footer(); ?>
