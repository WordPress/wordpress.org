<?php
/**
 * WordCamp.tv Archives
 *
 * Yearly, monthly, daily, author and whatever falls back to archive.php.
 *
 * @global WP_Query          $wp_query
 * @global WP_Post           $post
 * @global WordPressTV_Theme $wptv
 */

get_header();
global $wp_query, $post, $wptv;
?>
<div class="wptv-hero">
	<h2 class="page-title"><?php
		if ( is_category() ) :
			/* translators: %s: Category name. */
			printf( esc_html__( '&#8216;%s&#8217; Videos', 'wptv' ), esc_html( single_cat_title( '', false ) ) );

		elseif ( is_tag() ) :
			/* translators: %s: Tag name. */
			printf( esc_html__( '&#8216;%s&#8217; Videos', 'wptv' ), esc_html( single_tag_title( '', false ) ) );

		elseif ( is_day() ) :
			$archive_date = get_the_time( __( 'F jS, Y', 'wptv' ) );
			/* translators: %s: Date. */
			printf( esc_html_x( 'Archive for %s', 'Daily archive page', 'wptv' ), esc_html( $archive_date ) );

		elseif ( is_month() ) :
			$archive_date = get_the_time( __( 'F, Y', 'wptv' ) );
			/* translators: %s: Month. */
			printf( esc_html_x( 'Archive for %s', 'Monthly archive page', 'wptv' ), esc_html( $archive_date ) );

		elseif ( is_year() ) :
			$archive_date = get_the_time( __( 'Y', 'wptv' ) );
			/* translators: %s: Year. */
			printf( esc_html_x( 'Archive for %s', 'Yearly archive page', 'wptv' ), esc_html( $archive_date ) );

		elseif ( is_author() ) :
			esc_html_e( 'Author Archive', 'wptv' );

		elseif ( isset( $wp_query->query_vars['taxonomy'] ) ) :
			$tax   = get_taxonomy( $wp_query->query_vars['taxonomy'] );
			$terms = get_term_by( 'slug', $wp_query->query_vars['term'], $wp_query->query_vars['taxonomy'] );
			print( "$tax->label: $terms->name" );

		elseif ( is_search() ) :
			/* translators: %s: Search query. */
			printf( esc_html__( 'Search Results for &#8216;%s&#8217;', 'wptv' ), '<span>' . get_search_query() . '</span>' );

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
						$speakers = get_the_terms( $post->ID, 'speakers' );
						if ( $speakers ) {
							$label = _n( 'Speaker:', 'Speakers:', count( $speakers ), 'wptv' );
							$wptv->the_terms( 'speakers', '<span class="video-speakers"><strong>' . $label . '</strong> ', ', ', '</span>', false );
						}
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