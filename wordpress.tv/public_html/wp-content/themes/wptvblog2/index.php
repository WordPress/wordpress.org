<?php
/**
 * The main template file.
 *
 * @package WordPressTV_Blog
 */

get_header();
?>

<div class="wptv-hero">
	<h2 class="page-title">
	<?php
	if ( is_home() || is_front_page() ) {
		bloginfo( 'name' );
	} elseif ( is_category() ) {
		single_term_title();
	} elseif ( is_tag() ) {
		/* translators: Tag name. */
		printf( esc_html__( 'Tag Archives: &#8216;%s&#8217;', 'wptv' ), single_term_title( '', false ) );
	} elseif ( is_day() ) {
		/* translators: Date. */
		printf( esc_html_x( 'Archive for %s', 'Daily archive page', 'wptv' ), esc_html( get_the_time( __( 'F jS, Y', 'wptv' ) ) ) );
	} elseif ( is_month() ) {
		/* translators: Date. */
		printf( esc_html_x( 'Archive for %s', 'Monthly archive page', 'wptv' ), esc_html( get_the_time( __( 'F Y', 'wptv' ) ) ) );
	} elseif ( is_year() ) {
		/* translators: Date. */
		printf( esc_html_x( 'Archive for %s', 'Yearly archive page', 'wptv' ), esc_html( get_the_time( __( 'Y', 'wptv' ) ) ) );
	} else {
		esc_html_e( 'WordPress.tv Blog' );
	}
	?>
	</h2>
</div>
<div class="container">
	<div class="primary-content">

		<?php
		if ( have_posts() ) :
			while ( have_posts() ) :
				the_post();
		?>

			<div <?php post_class(); ?>>
				<div class="avatar"><?php echo get_avatar( $post->post_author, 110 ); ?></div>

				<div class="entry">
					<h3 class="post-title">
						<a href="<?php the_permalink(); ?>" rel="bookmark">
							<?php the_title(); ?>
						</a>
					</h3>

					<p class="post-meta">
						Posted on <?php the_time( 'l, F jS, Y' ); ?> at <?php the_time(); ?>
						<?php edit_post_link( __( 'Edit&nbsp;this&nbsp;entry.' ), ' |', '' ); ?>
						<span class="post-author">by <?php the_author(); ?></span>
					</p>

					<div class="entry-content">
					<?php the_content(); ?>
					</div>

				</div><!-- .entry.excerpt -->
			</div><!-- post_class() -->

		<?php
			endwhile;
		else :
			?>
			<div class="entry">
				<p><?php esc_html_e( 'We couldn&rsquo;t find anything like that. Try searching for something else:' ); ?></p>
				<p><?php echo get_search_form(); ?></p>
			</div>
			<?php
		endif;

		get_template_part( 'pagination' );
		?>
	</div><!-- .primary-content -->

	<?php get_sidebar(); ?>
</div><!-- .container -->
<?php
get_footer();
