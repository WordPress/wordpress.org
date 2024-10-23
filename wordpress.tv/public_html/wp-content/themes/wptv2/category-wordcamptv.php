<?php
/**
 * WordCampTV Category Archives
 *
 * Requires a special display, hence the template.
 *
 * @global WordPressTV_Theme $wptv
 */

$featured = new WP_Query( array(
	'posts_per_page' => 6,
	'category_name'  => 'wordcamptv',
	'tag'            => 'featured',
) );

get_header();
global $wptv;
?>
<div class="wptv-hero group">
	<div class="container">

		<h1 class="page-title"><?php single_term_title(); ?></h1>
		<div class="desc"><?php echo category_description(); ?></div>

		<?php if ( $featured->have_posts() ) : $featured->the_post(); ?>
			<div class="main-video">
				<?php $wptv->the_video(); ?>
				<h3>
					<a href="<?php the_permalink(); ?>" rel="bookmark" class="video-title"><?php the_title(); ?></a>
					<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
				</h3>
			</div><!-- .main-video -->
		<?php endif; // have_posts ?>

		<div class="secondary-videos">
			<ul>

				<?php while ( $featured->have_posts() ) : $featured->the_post(); ?>
				<li class="group">
					<a href="<?php the_permalink(); ?>" rel="bookmark">
						<span class="video-thumbnail">
							<?php $wptv->the_video_image( 50, null, false ); ?>
						</span>
						<span class="video-title"><?php the_title(); ?></span>
					</a>
					<?php $wptv->the_event( '<strong class="video-event">', '</strong>' ); ?>
				</li>
				<?php endwhile; // have_posts ?>

			</ul>
		</div><!-- .secondary-videos -->
	</div><!-- .container -->
</div><!-- .wptv-hero -->

<div class="container">
	<div class="primary-content">

		<?php
			/**
			 * The following is a very special navigation menu. It
			 * should contain Event taxonomy items only, and will list
			 * them with videos from the chosen event.
			 */
			wp_nav_menu( array(
				'theme_location' => 'featured_wordcamps',
				'depth'          => 1,
				'walker'         => new WordCampTV_Walker_Nav_Menu,
			) );
		?>

	</div><!-- .primary-content -->
	<?php get_sidebar( 'wordcamptv' ); ?>
</div><!-- container -->

<?php
get_footer();
