<?php
/**
 * Single WordCamp (post type) template.
 */

if ( get_query_var( 'wcorg-wordcamp-info' ) ) {
	return get_template_part( 'single-wordcamp-info' );
}

get_header(); the_post();
$wordcamp_title = wcpt_get_wordcamp_title();
?>

		<div id="container">
			<div id="content" role="main">

				<h1 class="entry-title"><?php wcpt_wordcamp_title(); ?></h1>

				<?php if ( has_post_thumbnail() ) : ?>
					<div class="wc-banner">
					<?php the_post_thumbnail( 'wccentral-thumbnail-large', array( 'class' => 'wc-image' ) ); ?>
					<?php $thumb = get_post( get_post_thumbnail_id() ); ?>
					<?php if ( ! empty( $thumb->post_excerpt ) ) : ?>
						<span class="caption"><?php echo esc_html( $thumb->post_excerpt ); ?></span>
					<?php endif; ?>
					</div><!-- .wc-banner -->
				<?php endif; ?>

				<?php if ( wcpt_get_wordcamp_url() ) : ?>
					<a href="<?php wcpt_wordcamp_url(); ?>" class="wc-single-website">
						<?php $shot_url = add_query_arg( array( 'w' => 205, 'h' => 148 ), 'http://s.wordpress.com/mshots/v1/' . urlencode( wcpt_get_wordcamp_url() ) ); ?>
						<img src="<?php echo esc_url( $shot_url ); ?>" />
						Visit Website &rarr;
					</a>
				<?php endif; ?>

				<div class="wc-single-info">

					<?php if ( wcpt_get_wordcamp_start_date( 0, 'F' ) ) : ?>
						<strong class="wc-single-label">Date</strong>
						<?php WordCamp_Central_Theme::the_wordcamp_date(); ?>,
						<?php wcpt_wordcamp_start_date( 0, 'Y' ); ?>
					<?php endif; ?>

					<?php if ( wcpt_get_wordcamp_physical_address() || wcpt_get_wordcamp_venue_name() ) : ?>
						<strong class="wc-single-label">Location</strong>
						<?php if ( wcpt_get_wordcamp_physical_address() ) : ?>

							<?php
								$address = urlencode( implode( " ", explode( "\n", wcpt_get_wordcamp_physical_address() ) ) );
								$map_url = 'http://maps.googleapis.com/maps/api/staticmap?center=' . $address . '&zoom=14&size=130x70&maptype=roadmap&markers=color:blue%7Clabel:A%7C' . $address . '&sensor=false';
								$map_link = 'http://maps.google.com/maps?q=' . $address;
								$venue_link = wcpt_get_wordcamp_venue_url();
							?>
							<a href="<?php echo esc_url( $map_link ); ?>"><img src="<?php echo esc_url( $map_url ); ?>" class="wc-single-map"/></a>

							<?php if ( $venue_link ) : ?>
								<a href="<?php echo esc_url( $venue_link ); ?>"><?php wcpt_wordcamp_venue_name(); ?></a><br />
							<?php else : ?>
								<strong><?php wcpt_wordcamp_venue_name(); ?></strong><br />
							<?php endif; ?>

							<?php echo nl2br( wcpt_get_wordcamp_physical_address() ); ?><br />
						<?php else: ?>
							<strong><?php wcpt_wordcamp_venue_name(); ?></strong><br />
							<?php wcpt_wordcamp_location(); ?>
						<?php endif; // physical_address ?>

					<?php else : // no physical address or venue ?>
						<strong class="wc-single-label">Location</strong>
						<?php wcpt_wordcamp_location(); ?>
					<?php endif; // physical_address || venue_name ?>

					<?php if ( get_the_content() ) : ?>
						<strong class="wc-single-label">About</strong>
						<?php the_content(); ?><br />
					<?php endif; ?>

				</div><!-- .wc-single-info -->

				<?php
				// Search for WordCamps with a similar title

				$wordcamps = get_posts( array(
					'posts_per_page' => 30,
					'post_type' => 'wordcamp',
					'post_status' => WordCamp_Loader::get_public_post_statuses(),
					'orderby' => 'ID',
					's' => $wordcamp_title,
				) );

				// Since search can look in content too, remove the ones with a different title
				foreach ( $wordcamps as $key => $post )
					if ( wcpt_get_wordcamp_title( $post->ID ) != $wordcamp_title )
						unset( $wordcamps[ $key ] );

				if ( ! empty( $wordcamps ) && function_exists( 'wcpt_has_wordcamps' ) &&
					wcpt_has_wordcamps( array(
						'posts_per_page' => 30,
						'order'          => 'ASC',
						'post_status'    => WordCamp_Loader::get_public_post_statuses(),
						'post__in'       => wp_list_pluck( $wordcamps, 'ID' ),
					) )
				) :
				?>

				<div class="wc-single-past">

					<h3><?php echo $wordcamp_title; ?></h3>
					<ul>

					<?php while ( wcpt_wordcamps() ) : wcpt_the_wordcamp(); ?>
						<li>
							<a href="<?php wcpt_wordcamp_permalink(); ?>">
								<strong><?php wcpt_wordcamp_start_date( 0, 'Y' ); ?></strong>
								<?php WordCamp_Central_Theme::the_wordcamp_date(); ?>
							</a>
						</li>

					<?php endwhile; // wordcamps ?>

					</ul>

				</div> <!-- .wc-single-past -->
				<?php endif; // has_wordcamps, function_exists ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>