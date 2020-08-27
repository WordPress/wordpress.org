<?php
/**
 * Static page template.
 *
 * @package P2
 */
?>
<?php get_header(); ?>

<?php if ( 'handbook' !== wporg_get_current_handbook() ) { ?>
<div class="handbook-name"><span><a href="<?php echo esc_url( get_post_type_archive_link( wporg_get_current_handbook() ) ); ?>"><?php echo esc_html( wporg_get_current_handbook_name() ); ?></a></span></div>
<?php } ?>

<!-- Also called on in footer but will not display the second time. -->
<?php get_sidebar( 'handbook' ); ?>

<div class="sleeve_main">

	<div id="main">
		<h1 class="handbook-page-title"><?php the_title(); ?></h1>
		<?php if ( have_posts() ) : ?>

			<ul id="postlist">
			<?php while ( have_posts() ) : the_post(); ?>
				<?php get_template_part( 'entry', 'handbook' ); ?>
			<?php endwhile; ?>
			</ul>

			<?php \WPorg_Handbook_Navigation::show_nav_links(); ?>

		<?php endif; ?>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
