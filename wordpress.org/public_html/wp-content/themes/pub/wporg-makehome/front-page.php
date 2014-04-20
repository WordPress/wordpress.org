<?php get_header(); ?>

<?php get_template_part( 'masthead' ); ?>

<?php get_template_part( 'subhead' ); ?>

<section class="get-involved">
	<div class="wrapper">
		<h2 class="section-title"><?php _e( 'There are many different ways for you to get involved with WordPress:', 'make-wporg' ); ?></h2>
		<div class="js-masonry" data-masonry-options='{ "itemSelector": ".make_site" }'>
		<?php 
			$sites_query = new WP_Query( 'post_type=make_site&posts_per_page=-1&order=ASC' );
			$makesites = make_site_get_network_sites();
		?>
		<?php while( $sites_query->have_posts() ) : $sites_query->the_post(); ?>
		<?php 
			$make_site_id = get_post_meta( $post->ID, 'make_site_id', true );
			$url = $makesites[$make_site_id];
		?>	
			<article id="site-<?php the_ID(); ?>" <?php post_class(); ?>>
				<h2>
					<?php if ( $url ) : ?>
						<a href="<?php echo esc_url( $url ); ?>"><?php the_title(); ?></a>
					<?php else : ?>
						<?php the_title(); ?>
					<?php endif; ?>
				</h2>
				
				<div class="team-description">
					<?php the_content(); ?>
					<?php if ( $url ) : ?>
						<p><a href="<?php echo esc_url( $url ); ?>">Learn more about <?php the_title(); ?> &raquo;</a></p>
					<?php endif; ?>
				</div>
				
				<?php  if ( '1' == get_post_meta( get_the_ID(), 'weekly_meeting', true ) ) : ?>
					<small>
						<p><?php printf( __( 'Weekly IRC chats: %s', 'make-wporg' ), get_post_meta( get_the_ID(), 'weekly_meeting_when', true ) ); ?></p>
						<p><?php echo get_post_meta( get_the_ID(), 'weekly_meeting_where', true ); ?></p>
					</small>
				<?php endif; /**/ ?>
			</article>
		<?php endwhile; ?>
		</div>
	</div>
</section>

<?php get_footer(); ?>
