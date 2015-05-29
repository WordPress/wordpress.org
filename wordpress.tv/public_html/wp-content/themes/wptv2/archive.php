<?php
/*
 * WordCamp.tv Archives
 *
 * Yearly, monthly, daily, author and whatever falls back to archive.php.
 */

get_header();
global $wp_query, $post, $wptv;
?>
<div class="wptv-hero">
	<h2 class="page-title"><?php
		if ( is_category() ) :
			printf( __( '&#8216;%s&#8217; Videos', 'wptv' ), single_cat_title( '', false ) );

		elseif ( is_tag() ) :
			printf( __( '&#8216;%s&#8217; Videos', 'wptv' ), single_tag_title( '', false ) );

		elseif ( is_day() ) :
			printf( _x( 'Archive for %s', 'Daily archive page', 'wptv' ), get_the_time( __( 'F jS, Y', 'wptv' ) ) );

		elseif ( is_month() ) :
			printf( _x( 'Archive for %s', 'Monthly archive page', 'wptv' ), get_the_time( __( 'F, Y', 'wptv' ) ) );

		elseif ( is_year() ) :
			printf( _x( 'Archive for %s', 'Yearly archive page', 'wptv' ), get_the_time( __( 'Y', 'wptv' ) ) );

		elseif ( is_author() ) :
			esc_html_e( 'Author Archive', 'wptv' );

		elseif ( isset( $wp_query->query_vars['taxonomy'] ) ) :
			$tax   = get_taxonomy( $wp_query->query_vars['taxonomy'] );
			$terms = get_term_by( 'slug', $wp_query->query_vars['term'], $wp_query->query_vars['taxonomy'] );
			print( "$tax->label: $terms->name" );

		elseif ( is_search() ) :
			printf( __( 'Search Results for &#8216;%s&#8217;', 'wptv' ), '<span>' . get_search_query() . '</span>' );

		else :
			esc_html_e( 'Archives', 'wptv' );

		endif;
	?>
	</h2>
</div>
<div class="container">
	<div class="primary-content">

		<?php if ( have_posts() ) : ?>
		<ul class="archive video-list">

			<?php while ( have_posts() ) : the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>" class="video-thumbnail">
					<?php $wptv->the_video_image( 50, null, false ); ?>
				</a>
				<div class="video-description">
					<h4 class="video-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h4>
					<?php
						$wptv->the_terms( 'event', '<span class="video-events">', ', ', '</span>', false );
						$label = _n( 'Speaker:', 'Speakers:', count( get_the_terms( $post->ID, 'speakers' ) ), 'wptv' );
						$wptv->the_terms( 'speakers', '<span class="video-speakers"><strong>' . $label . '</strong> ', ', ', '</span>', false );
					?>
					<span class="video-excerpt">
						<?php
							$excerpt = get_the_time( get_option( 'date_format' ) );
							if ( has_excerpt() ) {
								$excerpt .= ' &#8212; ' . get_the_excerpt();
							}
							echo apply_filters( 'the_excerpt', $excerpt );
						?>
					</span>
				</div>
			</li>
			<?php endwhile; // have_posts ?>

		</ul><!-- .archive.video-list -->
		<?php else: // have_posts ?>

			<h3><?php esc_html_e( 'No videos found.', 'wptv' ); ?></h3>

		<?php endif; // have_posts ?>

		<?php get_template_part( 'pagination' ); ?>

	</div><!-- primary-content -->
	<?php get_sidebar(); ?>
</div><!-- .container -->
<?php
get_footer();