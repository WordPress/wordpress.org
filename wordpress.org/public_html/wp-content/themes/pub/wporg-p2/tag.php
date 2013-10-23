<?php
/**
 * Tag Archive Template.
 *
 * @package P2
 */
?>
<?php get_header(); ?>
<?php $tag_obj = $wp_query->get_queried_object(); ?>

<div class="sleeve_main">

	<div id="main">
		<h2><?php printf( __( 'Tagged: %s', 'p2' ), single_tag_title( '', false) ); ?>
			<span class="controls">
				<a href="#" id="togglecomments"> <?php _e( 'Toggle Comment Threads', 'p2' ); ?></a> | <a href="#directions" id="directions-keyboard"><?php _e( 'Keyboard Shortcuts', 'p2' ); ?></a>
			</span>
		</h2>

		<?php if ( 30 == get_current_blog_id() ) : // 30 is /community ?>
			<div id="tag-description"><?php echo tag_description(); ?></div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>

			<ul id="postlist">
			<?php while ( have_posts() ) : the_post(); ?>

				<?php p2_load_entry(); ?>

			<?php endwhile; ?>
			</ul>

		<?php else : ?>

			<div class="no-posts">
			    <h3><?php _e( 'No posts found!', 'p2' ); ?></h3>
			</div>

		<?php endif ?>

		<div class="navigation">
			<p class="nav-older"><?php next_posts_link( __( '&larr; Older posts', 'p2' ) ); ?></p>
			<p class="nav-newer"><?php previous_posts_link( __( 'Newer posts &rarr;', 'p2' ) ); ?></p>
		</div>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>