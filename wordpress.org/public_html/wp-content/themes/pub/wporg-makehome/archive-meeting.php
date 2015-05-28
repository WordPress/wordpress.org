<?php get_header(); ?>

<div class="wrapper">
	<h2 class="title">Upcoming WordPress Meetings</h2>
<table class="schedule">
	<thead>
		<tr>
			<th>Team</th>
			<th>Name</th>
			<th>Next Meeting Date</th>
			<th>Time</th>
			<th>Location</th>
		</tr>
	</thead>
	<tbody>
	<?php while( have_posts() ): the_post(); ?>
		<tr id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<td><?php echo $post->team; ?></td>
			<td><a href="<?php echo $post->link; ?>"><?php the_title(); ?></a></td>
			<td><?php echo $post->next_date; ?></td>
			<td><?php echo $post->time; ?></td>
			<td><?php echo $post->location; ?></td>
		</tr>
	<?php endwhile; ?>
	</tbody>
</table>
</div><!-- /wrapper -->

<?php get_footer(); ?>
