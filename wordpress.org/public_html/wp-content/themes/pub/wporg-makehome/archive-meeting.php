<?php get_header(); ?>

<table class="wrapper">
	<tr>
		<th>Team</th>
		<th>Name</th>
		<th>Next Meeting Date</th>
		<th>Time</th>
		<th>Location</th>
	</tr>
	<?php while( have_posts() ): the_post(); ?>
		<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<td><?php echo $post->team; ?></td>
			<td><a href="<?php echo $post->link; ?>"><?php the_title(); ?></a></td>
			<td><?php echo $post->next_date; ?></td>
			<td><?php echo $post->time; ?></td>
			<td><?php echo $post->location; ?></td>
		</tr>

	<?php endwhile; ?>
</table><!-- /wrapper -->

<?php get_footer(); ?>
