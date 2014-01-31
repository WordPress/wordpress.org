<?php
/**
 * For the /reports/ page
 */

if ( isset( $_GET['from-trac'] ) ) {
	send_origin_headers();
	while ( have_posts() ) {
		the_post();
		p2_load_entry();
	}
	return;
}
?>

<?php get_header(); ?>

<style>
/* changes to P2 */
#main ul#postlist li {
	border: 0;
}
#main h4 {
	display: none;
}
#main .postcontent h4 {
	display: block;
}
#main .postcontent .trac-only {
	display: none;
}
#main .ticket-reports .report {
	margin-right: 15px;
}
.ticket-reports .very-narrow {
	margin-right: -10px;
}
#main .ticket-reports .narrow .report {
	margin-right: 40px;
}
</style>
<script>
$(document).on( 'ready', function() {
	$( '.tickets-by-topic' ).on( 'change', function() {
		var topic = $(this).val();
		if ( ! topic ) {
			return;
		}
		window.location.href = $(this).data( 'location' ) + topic;
		return false;
	});
});
</script>

<div class="sleeve_main">

	<div id="main">
		<h2><?php the_title(); ?></h2>

		<ul id="postlist">
		<?php if ( have_posts() ) : ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<?php p2_load_entry(); ?>
			<?php endwhile; ?>

		<?php endif; ?>
		</ul>

	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer(); ?>
