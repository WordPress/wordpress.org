<?php
/**
 * WordPress.tv Blog Single.
 *
 * @package WordPressTV_Blog
 */

get_header();
the_post();
?>

<div class="wptv-hero">
	<div class="container">
		<div class="breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php bloginfo( 'name' ); ?></a>
		</div>

		<?php the_title( '<h2 class="page-title">', '</h2>' ); ?>
	</div>
</div>

<div class="container">
	<div class="primary-content">
		<div <?php post_class(); ?>>
			<div class="entry">

				<?php the_content(); ?>

				<div id="wpcom-author">
					<div class="postmetadata">
							<p>Author: <a href="<?php the_author_meta( 'url' ); ?>"><?php the_author(); ?></a> |
							Posted: <?php the_time( 'l, F jS, Y' ); ?> at <?php the_time(); ?> |
							Filed in: <?php the_category( ', ' ); ?> |
							<?php the_tags(); ?></p>
					</div><!-- .postmetadata -->
				</div><!-- #wpcom-author -->

				<div id="comments">
					<?php
					wp_link_pages();
					comments_template();
					?>
				</div><!-- #comments -->

			</div><!-- .entry -->
		</div><!-- post_class() -->
	</div><!-- .primary-content -->
	<?php get_sidebar(); ?>
</div><!-- .container -->
<?php
get_footer();
